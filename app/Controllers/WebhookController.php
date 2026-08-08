<?php
/**
 * WebhookController - Twilio webhook receiver + public unsubscribe endpoint.
 *
 * All Twilio webhook POSTs land here. Every handler FIRST validates the
 * X-Twilio-Signature header (HMAC-SHA256 of the canonical URL+params with the
 * auth token) and rejects unsigned/spoofed payloads with 401. This closes the
 * spoofed-webhook vector that the original audit flagged.
 *
 * Endpoints:
 *   POST /webhooks/twilio/sms       inbound SMS + STOP keyword handling
 *   POST /webhooks/twilio/status    delivery status updates
 *   POST /webhooks/twilio/voice     call status updates
 *   POST /webhooks/twilio/voicemail voicemail delivery
 *   GET  /unsubscribe?token=...     one-click opt-out (CSRF-exempt by design:
 *                                   the HMAC-signed token IS the consent proof)
 *
 * Webhook events are stored in webhook_events (audit) and processed through
 * Twilio::processPendingWebhooks() so the existing processing pipeline owns
 * the domain logic.
 */

declare(strict_types=1);

namespace PPC\Controllers;

use PPC\Core\Config;
use PPC\Core\Compliance;
use PPC\Core\Logger;
use PPC\Integrations\Twilio;

class WebhookController
{
    /** CTIA STOP keywords. Twilio auto-replies at the platform level; we record the opt-out locally. */
    private const STOP_KEYWORDS = ['STOP', 'STOPALL', 'STOP ALL', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT'];

    /** Re-subscribe keywords: clear a STOP-derived opt-out. */
    private const START_KEYWORDS = ['START', 'UNSTOP', 'YES'];

    /**
     * Validate the X-Twilio-Signature header per Twilio's published scheme:
     * base64(HMAC-SHA256(authToken, canonicalUrl + sortedParams)).
     * Rejects the request (401) when missing or mismatched.
     */
    private function requireValidSignature(): void
    {
        // Scheme detection must match the URL Twilio POSTs to. Behind the
        // Dokploy TLS terminator, nginx maps X-Forwarded-Proto to HTTPS.
        $fwdProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        $isHttps  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $fwdProto === 'https';
        $scheme    = $isHttps ? 'https' : 'http';
        $host      = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $url       = $scheme . '://' . $host . $requestUri;

        $signature = (string) ($_SERVER['HTTP_X_TWILIO_SIGNATURE'] ?? '');
        $params    = $_POST !== [] ? $_POST : $_GET;
        ksort($params);

        $data = $url;
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                continue; // flat form fields only; arrays cannot be signed
            }
            $data .= (string) $key . (string) $value;
        }

        $authToken = (string) Config::get('TWILIO_AUTH_TOKEN', '');
        if ($authToken === '') {
            Logger::error('Twilio webhook rejected: auth token not configured');
            http_response_code(401);
            exit;
        }

        $expected = base64_encode(hash_hmac('sha256', $data, $authToken, true));
        if (!hash_equals($expected, $signature)) {
            Logger::warning('Twilio webhook signature mismatch', [
                'path' => $requestUri,
                'ip'   => $_SERVER['REMOTE_ADDR'] ?? '',
            ]);
            http_response_code(401);
            exit;
        }
    }

    /** Shared: respond 200 JSON and stop. */
    private function ok(): void
    {
        http_response_code(200);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'ok']);
        exit;
    }

    /** POST /webhooks/twilio/sms - inbound SMS. Handles STOP/START keywords. */
    public function sms(): void
    {
        $this->requireValidSignature();

        $from = trim((string) ($_POST['From'] ?? ''));
        $body = trim((string) ($_POST['Body'] ?? ''));
        $sid  = (string) ($_POST['MessageSid'] ?? '');

        if ($from === '' || $body === '') {
            Logger::warning('Twilio SMS webhook missing From/Body', ['sid' => $sid]);
            $this->ok();
        }

        $upper = strtoupper($body);

        // STOP handling: durable opt-out before anything else. We do NOT send
        // a reply here - Twilio auto-responds at the platform level, and an
        // outbound SMS to an opted-out number would itself violate DNC.
        if (in_array($upper, self::STOP_KEYWORDS, true)) {
            Compliance::recordUnsubscribe(null, $from, 'sms', 'STOP keyword via Twilio webhook', true);
            Logger::info('SMS STOP received', ['from' => $from, 'sid' => $sid]);
            Twilio::logWebhook('sms.incoming', $sid, ['From' => $from, 'Body' => $body, 'MessageSid' => $sid, 'stop' => true]);
            Twilio::processPendingWebhooks();
            $this->ok();
        }

        // START handling: clear a STOP-derived opt-out so the customer can
        // receive messages again (Twilio platform behavior mirrored locally).
        if (in_array($upper, self::START_KEYWORDS, true)) {
            $this->clearStopOptOut($from);
            Logger::info('SMS START received', ['from' => $from, 'sid' => $sid]);
            Twilio::logWebhook('sms.incoming', $sid, ['From' => $from, 'Body' => $body, 'MessageSid' => $sid, 'start' => true]);
            Twilio::processPendingWebhooks();
            $this->ok();
        }

        Twilio::logWebhook('sms.incoming', $sid, ['From' => $from, 'Body' => $body, 'MessageSid' => $sid]);
        Twilio::processPendingWebhooks();
        $this->ok();
    }

    /** POST /webhooks/twilio/status - SMS delivery status updates. */
    public function status(): void
    {
        $this->requireValidSignature();
        Twilio::logWebhook('sms.status', (string) ($_POST['MessageSid'] ?? ''), $_POST);
        Twilio::processPendingWebhooks();
        $this->ok();
    }

    /** POST /webhooks/twilio/voice - call status updates. */
    public function voice(): void
    {
        $this->requireValidSignature();
        Twilio::logWebhook('voice.status', (string) ($_POST['CallSid'] ?? ''), $_POST);
        Twilio::processPendingWebhooks();
        $this->ok();
    }

    /** POST /webhooks/twilio/voicemail - voicemail recordings. */
    public function voicemail(): void
    {
        $this->requireValidSignature();
        Twilio::logWebhook('voicemail', (string) ($_POST['CallSid'] ?? ''), $_POST);
        Twilio::processPendingWebhooks();
        $this->ok();
    }

    /** GET /unsubscribe?token=... - one-click opt-out, HMAC-signed. */
    public function unsubscribe(): void
    {
        $token   = (string) ($_GET['token'] ?? '');
        $payload = Compliance::verifyToken($token);

        if ($payload === null) {
            http_response_code(400);
            header('Content-Type: text/html; charset=utf-8');
            echo $this->page('Unsubscribe link invalid',
                '<p>This unsubscribe link is invalid or has expired.</p>'
                . '<p>If you keep getting messages, reply STOP to any text or email '
                . '<a href="mailto:no-reply@patriotpest.pro">no-reply@patriotpest.pro</a>.</p>');
            exit;
        }

        $email   = isset($payload['e']) && $payload['e'] !== '' ? (string) $payload['e'] : null;
        $phone   = isset($payload['p']) && $payload['p'] !== '' ? (string) $payload['p'] : null;
        $channel = in_array($payload['c'] ?? '', ['email', 'sms', 'all'], true) ? (string) $payload['c'] : 'all';

        Compliance::recordUnsubscribe($email, $phone, $channel, 'unsubscribed via signed link', true);
        Logger::info('Unsubscribe confirmed', ['email' => $email, 'phone' => $phone, 'channel' => $channel]);

        header('Content-Type: text/html; charset=utf-8');
        echo $this->page('You are unsubscribed',
            '<p>Your opt-out has been recorded. You will not receive further marketing '
            . 'messages on ' . ($channel === 'all' ? 'any channel' : 'this channel') . '.</p>'
            . '<p>It may take a day or two for in-flight campaigns to stop. '
            . 'If you change your mind, text START to opt back in.</p>');
        exit;
    }

    /** Remove STOP-derived opt-outs for a phone (START keyword re-subscribe). */
    private function clearStopOptOut(string $phone): void
    {
        try {
            $db = \PPC\Core\Database::instance();
            $rows = $db->fetchAll(
                "SELECT id FROM unsubscribes WHERE phone = ? AND (channel = 'sms' OR channel = 'all') AND reason LIKE '%STOP%'",
                [$phone]
            );
            foreach ($rows as $row) {
                $db->execute('DELETE FROM unsubscribes WHERE id = ?', [(int) $row['id']]);
            }
            $db->execute(
                "UPDATE customers SET is_no_call = 0, dnc_reason = NULL, updated_at = datetime('now')
                 WHERE phone = ? AND dnc_reason LIKE '%STOP%'",
                [$phone]
            );
            Logger::info('SMS re-subscribe cleared opt-out', ['phone' => $phone]);
        } catch (\Throwable $e) {
            Logger::warning('START re-subscribe cleanup failed', ['phone' => $phone, 'error' => $e->getMessage()]);
        }
    }

    /** Minimal branded HTML page for the unsubscribe endpoint. */
    private function page(string $title, string $innerHtml): string
    {
        return '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . htmlspecialchars($title, ENT_QUOTES) . ' | Patriot Pest Control</title>'
            . '<style>body{font-family:system-ui,sans-serif;background:#12140d;color:#e8e6da;'
            . 'display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}'
            . '.card{background:#1b1e14;border:1px solid #3a3f2c;border-radius:6px;padding:32px;max-width:480px}'
            . 'h1{font-size:22px;color:#f2efe2;margin:0 0 12px}p{font-size:14px;line-height:1.6;color:#c9c6b6}'
            . 'a{color:#c8a24a}</style></head><body><div class="card">'
            . '<h1>' . htmlspecialchars($title, ENT_QUOTES) . '</h1>' . $innerHtml
            . '</div></body></html>';
    }
}
