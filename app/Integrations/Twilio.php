<?php
/**
 * Twilio — Full Twilio API integration service class.
 *
 * Complete Twilio API coverage for SMS, voice, voicemail, and webhooks.
 * Handles SMS send/receive, voice call logging, voicemail management, and
 * webhook event processing. All activities are logged to local database
 * for audit trail and offline access.
 *
 * Credentials from .env: TWILIO_ACCOUNT_SID, TWILIO_AUTH_TOKEN, TWILIO_PHONE_NUMBER,
 * TWILIO_API_KEY, TWILIO_API_SECRET.
 */

declare(strict_types=1);

namespace PPC\Integrations;

use PPC\Core\Config;
use PPC\Core\Database;
use PPC\Core\Logger;

final class Twilio
{
    private const TIMEOUT = 30;
    private const BASE_URL = 'https://api.twilio.com';
    private const API_VERSION = '2010-04-01';

    private static ?string $accountSid = null;
    private static ?string $authToken = null;
    private static ?string $phoneNumber = null;
    private static ?string $apiKey = null;
    private static ?string $apiSecret = null;

    /** Initialize credentials from config. */
    private static function init(): void
    {
        if (self::$accountSid !== null) {
            return;
        }
        self::$accountSid = trim((string) Config::get('TWILIO_ACCOUNT_SID', ''));
        self::$authToken = trim((string) Config::get('TWILIO_AUTH_TOKEN', ''));
        self::$phoneNumber = trim((string) Config::get('TWILIO_PHONE_NUMBER', ''));
        self::$apiKey = trim((string) Config::get('TWILIO_API_KEY', ''));
        self::$apiSecret = trim((string) Config::get('TWILIO_API_SECRET', ''));
    }

    /** True when Twilio is fully configured. */
    public static function isConfigured(): bool
    {
        self::init();
        return self::$accountSid !== '' && self::$authToken !== '';
    }

    /** ==================== SMS METHODS ==================== */
    /**
     * Send an SMS message.
     *
     * DNC gate: before sending, normalizes the phone to E.164 and checks
     * customers.is_no_call + the unsubscribes table. Hard-refuses with an
     * audit trail if the number is opted out. Gate is configurable via
     * TWILIO_DNC_CHECK_ENABLED (default true).
     *
     * @param string $to Phone number in E.164 format
     * @param string $message Message body (max 1600 chars)
     * @param string|null $mediaUrl Optional MMS media URL
     * @return array{success:bool, sid:string|null, error:string|null}
     */
    public static function sendSms(string $to, string $message, ?string $mediaUrl = null): array
    {
        self::init();
        if (!self::isConfigured()) {
            return ['success' => false, 'sid' => null, 'error' => 'Twilio not configured'];
        }

        // --- DNC gate ---
        if (Config::bool('TWILIO_DNC_CHECK_ENABLED', true)) {
            $normalized = \PPC\Integrations\FieldRoutes::normalizePhone($to);
            if ($normalized !== null) {
                $db = Database::instance();
                // Check customer opt-out flag.
                $dnc = $db->fetch(
                    'SELECT id, name, is_no_call, dnc_reason FROM customers WHERE phone = ? AND is_no_call = 1 LIMIT 1',
                    [$normalized]
                );
                if ($dnc) {
                    self::logDncBlock($normalized, 'customer_no_call', $dnc['id'] ?? null, $dnc['dnc_reason'] ?? 'Customer flagged no-call');
                    return ['success' => false, 'sid' => null, 'error' => 'DNC block: customer has opted out of calls/texts'];
                }
                // Check global unsubscribe list.
                $unsub = $db->fetch(
                    "SELECT id, customer_id, reason FROM unsubscribes WHERE phone = ? AND (channel = 'sms' OR channel = 'all') LIMIT 1",
                    [$normalized]
                );
                if ($unsub) {
                    self::logDncBlock($normalized, 'unsubscribe_list', $unsub['customer_id'] ?? null, $unsub['reason'] ?? 'Unsubscribed');
                    return ['success' => false, 'sid' => null, 'error' => 'DNC block: number is on the unsubscribe list'];
                }
            }
        }

        $params = [
            'From' => self::$phoneNumber,
            'To'   => $to,
            'Body' => substr($message, 0, 1600),
        ];
        if ($mediaUrl !== null) {
            $params['MediaUrl'] = $mediaUrl;
        }

        $response = self::apiRequest("Accounts/" . self::$accountSid . "/Messages.json", $params, 'POST');
        
        if ($response['success'] && isset($response['data']['sid'])) {
            self::logSms($to, $message, 'outbound', 'queued', $response['data']['sid'], null, $mediaUrl);
            return ['success' => true, 'sid' => $response['data']['sid'], 'error' => null];
        }

        $error = $response['error'] ?? 'Unknown error';
        self::logSms($to, $message, 'outbound', 'failed', null, $error, $mediaUrl);
        return ['success' => false, 'sid' => null, 'error' => $error];
    }

    /**
     * Log SMS message to database.
     */
    private static function logSms(string $phone, string $message, string $direction, string $status, ?string $twilioSid, ?string $error, ?string $mediaUrl): void
    {
        try {
            Database::instance()->insert('sms_logs', [
                'phone_number'  => $phone,
                'message'       => $message,
                'direction'     => $direction,
                'status'        => $status,
                'twilio_sid'    => $twilioSid,
                'error_message' => $error,
                'media_url'     => $mediaUrl,
                'created_at'    => gmdate('Y-m-d H:i:s'),
                'updated_at'    => gmdate('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            Logger::error('SMS log failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Log a DNC-blocked send attempt to the audit trail. Hard evidence
     * that the system respected an opt-out - essential for compliance.
     */
    private static function logDncBlock(string $phone, string $source, ?string $customerId, string $reason): void
    {
        try {
            Database::instance()->insert('audit_log', [
                'user_id'    => $customerId,
                'user_type'  => 'system',
                'action'     => 'sms.dnc_blocked',
                'entity'     => 'customer',
                'entity_id'  => $customerId,
                'meta_json'  => json_encode([
                    'phone'  => $phone,
                    'source' => $source,
                    'reason' => $reason,
                ]),
                'ip'         => null,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            Logger::error('DNC audit log failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Update SMS status from webhook.
     */
    public static function updateSmsStatus(string $twilioSid, string $status, ?string $errorMessage = null): void
    {
        try {
            Database::instance()->update('sms_logs', [
                'status'        => $status,
                'twilio_status' => $status,
                'error_message' => $errorMessage,
                'updated_at'    => gmdate('Y-m-d H:i:s'),
            ], ['twilio_sid' => $twilioSid]);
        } catch (\Throwable $e) {
            Logger::error('SMS status update failed', ['sid' => $twilioSid, 'error' => $e->getMessage()]);
        }
    }

    /** ==================== VOICE METHODS ==================== */

    /**
     * Initiate a voice call.
     *
     * @param string $to Phone number in E.164 format
     * @param string $url TwiML URL for call handling
     * @return array{success:bool, sid:string|null, error:string|null}
     */
    public static function initiateCall(string $to, string $url): array
    {
        self::init();
        if (!self::isConfigured()) {
            return ['success' => false, 'sid' => null, 'error' => 'Twilio not configured'];
        }

        $params = [
            'From' => self::$phoneNumber,
            'To'   => $to,
            'Url'  => $url,
        ];

        $response = self::apiRequest("Accounts/" . self::$accountSid . "/Calls.json", $params, 'POST');
        
        if ($response['success'] && isset($response['data']['sid'])) {
            self::logCall($to, 'outbound', 'queued', $response['data']['sid']);
            return ['success' => true, 'sid' => $response['data']['sid'], 'error' => null];
        }

        $error = $response['error'] ?? 'Unknown error';
        self::logCall($to, 'outbound', 'failed', null, $error);
        return ['success' => false, 'sid' => null, 'error' => $error];
    }

    /**
     * Log call to database.
     */
    private static function logCall(string $phone, string $direction, string $status, ?string $twilioSid, ?string $error = null): void
    {
        try {
            Database::instance()->insert('call_logs', [
                'phone_number'  => $phone,
                'direction'     => $direction,
                'duration'      => 0,
                'status'        => $status,
                'twilio_sid'    => $twilioSid,
                'twilio_status' => $status,
                'error_message' => $error,
                'created_at'    => gmdate('Y-m-d H:i:s'),
                'updated_at'    => gmdate('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            Logger::error('Call log failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Update call status from webhook.
     */
    public static function updateCallStatus(string $twilioSid, string $status, ?int $duration = null, ?string $recordingUrl = null, ?string $transcription = null): void
    {
        try {
            $update = [
                'status'        => $status,
                'twilio_status' => $status,
                'updated_at'    => gmdate('Y-m-d H:i:s'),
            ];
            if ($duration !== null) {
                $update['duration'] = $duration;
            }
            if ($recordingUrl !== null) {
                $update['recording_url'] = $recordingUrl;
            }
            if ($transcription !== null) {
                $update['transcription'] = $transcription;
            }
            Database::instance()->update('call_logs', $update, ['twilio_sid' => $twilioSid]);
        } catch (\Throwable $e) {
            Logger::error('Call status update failed', ['sid' => $twilioSid, 'error' => $e->getMessage()]);
        }
    }

    /** ==================== VOICEMAIL METHODS ==================== */

    /**
     * Log incoming voicemail.
     */
    public static function logVoicemail(string $phone, ?string $callSid, string $audioUrl, int $duration, ?string $transcription = null): void
    {
        try {
            Database::instance()->insert('voicemails', [
                'phone_number'  => $phone,
                'call_sid'      => $callSid,
                'audio_url'     => $audioUrl,
                'duration'      => $duration,
                'transcription' => $transcription,
                'status'        => 'new',
                'created_at'    => gmdate('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            Logger::error('Voicemail log failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Update voicemail status.
     */
    public static function updateVoicemailStatus(int $id, string $status): void
    {
        try {
            Database::instance()->update('voicemails', ['status' => $status], ['id' => $id]);
        } catch (\Throwable $e) {
            Logger::error('Voicemail status update failed', ['id' => $id, 'error' => $e->getMessage()]);
        }
    }

    /** ==================== WEBHOOK METHODS ==================== */

    /**
     * Log webhook event.
     */
    public static function logWebhook(string $eventType, ?string $twilioSid, array $payload): void
    {
        try {
            Database::instance()->insert('webhook_events', [
                'event_type' => $eventType,
                'twilio_sid' => $twilioSid,
                'payload'    => json_encode($payload),
                'processed'  => 0,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            Logger::error('Webhook log failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Process pending webhook events.
     */
    public static function processPendingWebhooks(): int
    {
        try {
            $db = Database::instance();
            $pending = $db->fetchAll("SELECT * FROM webhook_events WHERE processed = 0 ORDER BY created_at LIMIT 50");
            $processed = 0;

            foreach ($pending as $event) {
                $payload = json_decode($event['payload'], true);
                if (!is_array($payload)) {
                    continue;
                }

                switch ($event['event_type']) {
                    case 'sms.incoming':
                        self::handleIncomingSms($payload);
                        break;
                    case 'sms.status':
                        self::handleSmsStatus($payload);
                        break;
                    case 'voice.status':
                        self::handleVoiceStatus($payload);
                        break;
                    case 'voicemail':
                        self::handleVoicemail($payload);
                        break;
                }

                $db->update('webhook_events', ['processed' => 1, 'processed_at' => gmdate('Y-m-d H:i:s')], ['id' => $event['id']]);
                $processed++;
            }

            return $processed;
        } catch (\Throwable $e) {
            Logger::error('Webhook processing failed', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    private static function handleIncomingSms(array $payload): void
    {
        $from = $payload['From'] ?? '';
        $body = $payload['Body'] ?? '';
        $sid = $payload['MessageSid'] ?? '';
        
        if ($from && $body) {
            self::logSms($from, $body, 'inbound', 'received', $sid, null, null);
        }
    }

    private static function handleSmsStatus(array $payload): void
    {
        $sid = $payload['MessageSid'] ?? '';
        $status = $payload['MessageStatus'] ?? '';
        $errorCode = $payload['ErrorCode'] ?? null;
        
        if ($sid && $status) {
            $errorMessage = $errorCode ? "Error code: $errorCode" : null;
            self::updateSmsStatus($sid, $status, $errorMessage);
        }
    }

    private static function handleVoiceStatus(array $payload): void
    {
        $sid = $payload['CallSid'] ?? '';
        $status = $payload['CallStatus'] ?? '';
        $duration = isset($payload['CallDuration']) ? (int) $payload['CallDuration'] : null;
        $recordingUrl = $payload['RecordingUrl'] ?? null;
        
        if ($sid && $status) {
            self::updateCallStatus($sid, $status, $duration, $recordingUrl);
        }
    }

    private static function handleVoicemail(array $payload): void
    {
        $from = $payload['From'] ?? '';
        $callSid = $payload['CallSid'] ?? '';
        $url = $payload['RecordingUrl'] ?? '';
        $duration = isset($payload['RecordingDuration']) ? (int) $payload['RecordingDuration'] : 0;
        $transcription = $payload['TranscriptionText'] ?? null;
        
        if ($from && $url) {
            self::logVoicemail($from, $callSid, $url, $duration, $transcription);
        }
    }

    /** ==================== API REQUEST ==================== */

    /**
     * Make Twilio API request.
     *
     * @param string $endpoint API endpoint
     * @param array $params Request parameters
     * @param string $method HTTP method
     * @return array{success:bool, data:array, error:string|null}
     */
    private static function apiRequest(string $endpoint, array $params = [], string $method = 'GET'): array
    {
        self::init();
        $url = self::BASE_URL . '/' . self::API_VERSION . '/' . ltrim($endpoint, '/');

        if ($method === 'GET' && !empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $auth = base64_encode(self::$accountSid . ':' . self::$authToken);
        $headers = [
            'Authorization: Basic ' . $auth,
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ];

        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
        ];
        if (!Config::bool('TWILIO_SSL_VERIFY', true)) {
            $opts[CURLOPT_SSL_VERIFYPEER] = false;
            $opts[CURLOPT_SSL_VERIFYHOST] = 0;
        }
        curl_setopt_array($ch, $opts);

        if ($method === 'POST' && !empty($params)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        }

        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($resp === false) {
            Logger::error('Twilio request failed', ['error' => $err]);
            return ['success' => false, 'data' => [], 'error' => $err];
        }

        if ($code < 200 || $code >= 300) {
            Logger::error('Twilio HTTP error', ['code' => $code, 'response' => $resp]);
            return ['success' => false, 'data' => [], 'error' => "HTTP $code"];
        }

        $data = json_decode($resp, true);
        if (!is_array($data)) {
            Logger::error('Twilio bad JSON', ['response' => $resp]);
            return ['success' => false, 'data' => [], 'error' => 'Invalid JSON'];
        }

        return ['success' => true, 'data' => $data, 'error' => null];
    }

    /** ==================== UTILITY METHODS ==================== */

    /**
     * Get SMS logs from database.
     */
    public static function getSmsLogs(int $limit = 50, int $offset = 0): array
    {
        try {
            return Database::instance()->fetchAll(
                "SELECT * FROM sms_logs ORDER BY created_at DESC LIMIT $limit OFFSET $offset"
            );
        } catch (\Throwable $e) {
            Logger::error('SMS logs query failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get call logs from database.
     */
    public static function getCallLogs(int $limit = 50, int $offset = 0): array
    {
        try {
            return Database::instance()->fetchAll(
                "SELECT * FROM call_logs ORDER BY created_at DESC LIMIT $limit OFFSET $offset"
            );
        } catch (\Throwable $e) {
            Logger::error('Call logs query failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get voicemails from database.
     */
    public static function getVoicemails(int $limit = 50, int $offset = 0): array
    {
        try {
            return Database::instance()->fetchAll(
                "SELECT * FROM voicemails ORDER BY created_at DESC LIMIT $limit OFFSET $offset"
            );
        } catch (\Throwable $e) {
            Logger::error('Voicemails query failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get webhook events from database.
     */
    public static function getWebhookEvents(int $limit = 50, int $offset = 0): array
    {
        try {
            return Database::instance()->fetchAll(
                "SELECT * FROM webhook_events ORDER BY created_at DESC LIMIT $limit OFFSET $offset"
            );
        } catch (\Throwable $e) {
            Logger::error('Webhook events query failed', ['error' => $e->getMessage()]);
            return [];
        }
    }
}