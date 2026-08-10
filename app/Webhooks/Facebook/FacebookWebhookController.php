<?php
/**
 * FacebookWebhookController — Facebook Lead Ads webhook receiver.
 *
 * Two endpoints:
 *   GET  /webhooks/facebook  — subscription verification (hub.mode=subscribe handshake)
 *   POST /webhooks/facebook  — lead event processing
 *
 * POST flow (bare pipe, David simplification order):
 *   1. Validate X-Hub-Signature-256 (HMAC-SHA256 of raw body vs FACEBOOK_APP_SECRET).
 *   2. Parse JSON, iterate entries → changes → leadgen field.
 *   3. Dedup: leadgen_id UNIQUE constraint + 24h fingerprint window.
 *   4. Graph API fetch: GET /v21.0/{leadgen_id}?access_token={PAGE_TOKEN}
 *   5. Store in facebook_leads.
 *   6. Dispatch: primary SMS (email-to-SMS via Verizon gateway), email fallback on failure.
 *   7. Log everything.
 */

declare(strict_types=1);

namespace PPC\Webhooks\Facebook;

use PPC\Core\Config;
use PPC\Core\Logger;
use PPC\Models\FacebookLead;
use PPC\Services\Sms\SmsService;

final class FacebookWebhookController
{
    /**
     * GET /webhooks/facebook — verify Facebook's webhook subscription challenge.
     *
     * Facebook sends: hub.mode=subscribe, hub.verify_token=<token>, hub.challenge=<int>
     * We compare hub.verify_token against FACEBOOK_HUB_VERIFY_TOKEN and echo hub.challenge.
     */
    public function verify(): void
    {
        $mode         = $_GET['hub_mode'] ?? '';
        $verifyToken  = $_GET['hub_verify_token'] ?? '';
        $challenge    = $_GET['hub_challenge'] ?? '';

        if ($mode !== 'subscribe') {
            Logger::warning('Facebook webhook verify: bad mode', ['mode' => $mode]);
            http_response_code(400);
            exit;
        }

        $expectedToken = Config::get('FACEBOOK_HUB_VERIFY_TOKEN', '');
        if ($expectedToken === '' || !hash_equals($expectedToken, $verifyToken)) {
            Logger::warning('Facebook webhook verify: token mismatch');
            http_response_code(403);
            exit;
        }

        Logger::info('Facebook webhook verified');
        header('Content-Type: text/plain; charset=utf-8');
        echo $challenge;
        exit;
    }

    /**
     * POST /webhooks/facebook — process incoming lead event.
     *
     * Reads raw POST body, validates signature, iterates leadgen changes,
     * deduplicates, fetches lead data from Graph API, stores, and dispatches
     * notification (SMS → email fallback).
     */
    public function receive(): void
    {
        // --- 1. Read raw body + validate signature ---
        $rawBody = file_get_contents('php://input') ?: '';
        if ($rawBody === '') {
            Logger::warning('Facebook webhook: empty body');
            http_response_code(400);
            echo 'empty body';
            exit;
        }

        if (!$this->verifySignature($rawBody)) {
            http_response_code(401);
            echo 'invalid signature';
            exit;
        }

        // --- 2. Parse JSON ---
        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            Logger::warning('Facebook webhook: bad JSON');
            http_response_code(400);
            echo 'bad json';
            exit;
        }

        Logger::info('Facebook webhook received', ['payload_size' => strlen($rawBody)]);

        $entries = $payload['entry'] ?? [];
        if (!is_array($entries)) {
            $this->ok();
            return;
        }

        $processed = 0;
        foreach ($entries as $entry) {
            $pageId  = (string) ($entry['id'] ?? '');
            $changes = $entry['changes'] ?? [];
            if (!is_array($changes)) {
                continue;
            }
            foreach ($changes as $change) {
                if (($change['field'] ?? '') !== 'leadgen') {
                    continue;
                }
                $value    = $change['value'] ?? [];
                $leadgenId = (string) ($value['leadgen_id'] ?? '');
                $formId    = (string) ($value['form_id'] ?? '');
                $adgroupId = (string) ($value['adgroup_id'] ?? '');
                $adId      = (string) ($value['ad_id'] ?? '');
                $campaignId = (string) ($value['campaign_id'] ?? '');

                if ($leadgenId === '') {
                    Logger::warning('Facebook webhook: leadgen_id missing in change');
                    continue;
                }

                // --- 3. Dedup ---
                if (FacebookLead::findByLeadgenId($leadgenId) !== null) {
                    Logger::info('Facebook webhook: duplicate leadgen_id', ['leadgen_id' => $leadgenId]);
                    continue;
                }

                // --- 4. Graph API fetch ---
                $leadFields = $this->fetchLeadFromGraph($leadgenId);
                if ($leadFields === null) {
                    Logger::error('Facebook webhook: Graph API fetch failed', ['leadgen_id' => $leadgenId]);
                    continue;
                }

                $fullName = $this->extractField($leadFields, 'full_name');
                $email    = $this->extractField($leadFields, 'email');
                $phone    = $this->extractField($leadFields, 'phone_number')
                         ?: $this->extractField($leadFields, 'phone');
                $city     = $this->extractField($leadFields, 'city');
                $state    = $this->extractField($leadFields, 'state');
                $zip      = $this->extractField($leadFields, 'zip')
                         ?: $this->extractField($leadFields, 'zip_code');

                // Fingerprint: SHA256 of name|email|phone for 24h window dedup
                $fingerprint = hash('sha256', implode('|', [$fullName, $email, $phone]));

                if (FacebookLead::fingerprintExists($fingerprint)) {
                    Logger::info('Facebook webhook: duplicate fingerprint', [
                        'leadgen_id' => $leadgenId,
                        'name'       => $fullName,
                    ]);
                    continue;
                }

                // --- 5. Store ---
                try {
                    $leadId = FacebookLead::insert([
                        'leadgen_id'  => $leadgenId,
                        'page_id'     => $pageId,
                        'form_id'     => $formId,
                        'ad_id'       => $adId,
                        'adgroup_id'  => $adgroupId,
                        'campaign_id' => $campaignId,
                        'full_name'   => $fullName,
                        'email'       => $email,
                        'phone'       => $phone,
                        'city'        => $city,
                        'state'       => $state,
                        'zip'         => $zip,
                        'raw_payload' => json_encode($leadFields),
                        'fingerprint' => $fingerprint,
                        'created_at'  => gmdate('Y-m-d H:i:s'),
                    ]);
                } catch (\Throwable $e) {
                    // UNIQUE constraint violation on leadgen_id (race)
                    Logger::warning('Facebook webhook: insert failed (likely duplicate)', [
                        'leadgen_id' => $leadgenId,
                        'error'      => $e->getMessage(),
                    ]);
                    continue;
                }

                // --- 6. Dispatch notification ---
                $leadData = [
                    'full_name'   => $fullName,
                    'email'       => $email,
                    'phone'       => $phone,
                    'city'        => $city,
                    'state'       => $state,
                    'zip'         => $zip,
                    'ad_id'       => $adId,
                    'campaign_id' => $campaignId,
                ];

                $notifyResult = SmsService::sendLeadNotification($fullName ?: 'Unknown', $leadData);

                // --- 7. Update status ---
                $smsOk    = $notifyResult['method'] === 'sms';
                $emailOk  = $notifyResult['method'] === 'email_fallback';
                $smsError = $notifyResult['error'] ?? null;
                FacebookLead::updateNotificationStatus($leadId, $smsOk, $smsError, $emailOk);

                Logger::info('Facebook lead processed', [
                    'leadgen_id'   => $leadgenId,
                    'name'         => $fullName,
                    'notify_method' => $notifyResult['method'],
                    'success'      => $notifyResult['success'] ? 'yes' : 'no',
                ]);

                $processed++;
            }
        }

        Logger::info('Facebook webhook batch complete', ['processed' => $processed]);
        $this->ok();
    }

    /**
     * Verify X-Hub-Signature-256 header against FACEBOOK_APP_SECRET.
     *
     * Facebook sends: sha256=<hex-hmac>
     * We compute: HMAC-SHA256(raw_body, app_secret) and compare with hash_equals.
     */
    private function verifySignature(string $rawBody): bool
    {
        $appSecret = Config::get('FACEBOOK_APP_SECRET', '');
        if ($appSecret === '') {
            Logger::error('Facebook webhook: FACEBOOK_APP_SECRET not configured');
            return false;
        }

        $header = (string) ($_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '');
        if ($header === '' || !str_starts_with($header, 'sha256=')) {
            Logger::warning('Facebook webhook: missing or malformed X-Hub-Signature-256');
            return false;
        }

        $expected = substr($header, 7); // strip "sha256=" prefix
        $computed = hash_hmac('sha256', $rawBody, $appSecret);

        if (!hash_equals($expected, $computed)) {
            Logger::warning('Facebook webhook: signature mismatch');
            return false;
        }

        return true;
    }

    /**
     * Fetch lead data from Facebook Graph API.
     *
     * GET https://graph.facebook.com/v21.0/{leadgen_id}?access_token={PAGE_TOKEN}
     * Returns the field_data array or null on failure.
     */
    private function fetchLeadFromGraph(string $leadgenId): ?array
    {
        $accessToken = Config::get('FACEBOOK_PAGE_ACCESS_TOKEN', '');
        if ($accessToken === '') {
            Logger::error('Facebook webhook: FACEBOOK_PAGE_ACCESS_TOKEN not configured');
            return null;
        }

        $url = 'https://graph.facebook.com/v21.0/' . $leadgenId
            . '?access_token=' . urlencode($accessToken);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);

        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($resp === false) {
            Logger::error('Facebook Graph API: cURL error', ['leadgen_id' => $leadgenId, 'error' => $err]);
            return null;
        }

        if ($code < 200 || $code >= 300) {
            Logger::error('Facebook Graph API: HTTP error', [
                'leadgen_id' => $leadgenId,
                'http_code'  => $code,
                'response'   => substr($resp, 0, 500),
            ]);
            return null;
        }

        $data = json_decode($resp, true);
        if (!is_array($data)) {
            Logger::error('Facebook Graph API: bad JSON', ['leadgen_id' => $leadgenId]);
            return null;
        }

        // Graph API returns { field_data: [{ name: "...", values: ["..."] }, ...] }
        return $data['field_data'] ?? $data;
    }

    /**
     * Extract a named field from Facebook's field_data array.
     *
     * field_data looks like:
     *   [{"name": "full_name", "values": ["Jane Doe"]}, ...]
     */
    private function extractField(array $fieldData, string $name): string
    {
        foreach ($fieldData as $field) {
            if (!is_array($field)) {
                continue;
            }
            if (($field['name'] ?? '') === $name) {
                $values = $field['values'] ?? [];
                return is_array($values) && isset($values[0]) ? (string) $values[0] : '';
            }
        }
        return '';
    }

    /** Respond 200 OK. Facebook requires a timely 200 to avoid retries. */
    private function ok(): void
    {
        http_response_code(200);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'ok';
        exit;
    }
}
