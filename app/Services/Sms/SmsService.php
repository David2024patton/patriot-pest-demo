<?php
/**
 * SmsService - Facebook lead SMS dispatch via email-to-SMS carrier gateway.
 *
 * Path: Titan SMTP (smtp.titan.email:587, PPC_Info@patriotpest.pro, STARTTLS)
 *       -> Verizon gateway (5096664121@vtext.com).
 *
 * This is a standalone SMTP sender, deliberately isolated from Mailer so the
 * lead pipeline can use Titan regardless of what MAIL_HOST is configured to.
 *
 * SMS_ENABLED (LEAD_SMS_ENABLED) gates the entire dispatch. When false, the
 * pipeline stores the lead but skips notification. Flip to true when A2P 10DLC
 * comes online and you want to swap from email-to-SMS to direct Twilio SMS.
 *
 * DESIGN (David simplification order): primary SMS attempt, email fallback
 * to LEAD_EMAIL_TO on failure. Two paths max. No escalation tree.
 */

declare(strict_types=1);

namespace PPC\Services\Sms;

use PPC\Core\Config;
use PPC\Core\Logger;

final class SmsService
{
    /**
     * Send a lead notification via email-to-SMS carrier gateway.
     *
     * @param string $leadName  Lead's full name (used in subject line).
     * @param array  $leadData  Associative array with lead fields.
     * @return array{success:bool, method:string, error?:string}
     */
    public static function sendLeadNotification(string $leadName, array $leadData): array
    {
        // Gate: SMS_ENABLED
        if (!Config::bool('LEAD_SMS_ENABLED', false)) {
            Logger::info('Lead SMS skipped: LEAD_SMS_ENABLED=false', ['lead' => $leadName]);
            return self::sendEmailFallback($leadName, $leadData);
        }

        // Primary: email-to-SMS via carrier gateway
        $gateway = Config::get('LEAD_SMS_TO', '5096664121@vtext.com');
        $subject = 'New Lead: ' . $leadName;
        $body    = self::buildSmsBody($leadData);

        Logger::info('Lead SMS dispatching', ['gateway' => $gateway, 'lead' => $leadName]);

        $result = self::smtpSend($gateway, $subject, $body);

        if ($result['success']) {
            return ['success' => true, 'method' => 'sms'];
        }

        // Fallback: email
        Logger::warning('Lead SMS failed, falling back to email', [
            'lead'  => $leadName,
            'error' => $result['error'] ?? 'unknown',
        ]);

        return self::sendEmailFallback($leadName, $leadData);
    }

    /**
     * Email fallback: sends the lead to LEAD_EMAIL_TO via Titan SMTP.
     */
    private static function sendEmailFallback(string $leadName, array $leadData): array
    {
        $to      = Config::get('LEAD_EMAIL_TO', 'PPC_Info@patriotpest.pro');
        $subject = 'Lead Fallback: ' . $leadName;
        $body    = self::buildEmailBody($leadData);

        Logger::info('Lead email fallback dispatching', ['to' => $to, 'lead' => $leadName]);

        $result = self::smtpSend($to, $subject, $body);

        if ($result['success']) {
            return ['success' => true, 'method' => 'email_fallback'];
        }

        Logger::error('Lead email fallback also failed', [
            'lead'  => $leadName,
            'error' => $result['error'] ?? 'unknown',
        ]);

        return ['success' => false, 'method' => 'none', 'error' => $result['error'] ?? 'Both SMS and email fallback failed'];
    }

    /**
     * Build a concise SMS body for the carrier gateway.
     * Carriers chunk long messages; keep it tight.
     */
    private static function buildSmsBody(array $lead): string
    {
        $lines = [];
        $lines[] = 'New Lead: ' . ($lead['full_name'] ?? 'Unknown');
        if (!empty($lead['phone'])) {
            $lines[] = 'Phone: ' . $lead['phone'];
        }
        if (!empty($lead['email'])) {
            $lines[] = 'Email: ' . $lead['email'];
        }
        if (!empty($lead['city']) || !empty($lead['state'])) {
            $loc = trim(($lead['city'] ?? '') . ', ' . ($lead['state'] ?? ''), ', ');
            if ($loc !== '') {
                $lines[] = 'Location: ' . $loc;
            }
        }
        $lines[] = '';
        $lines[] = '-- Patriot Pest Control Lead Pipeline';
        return implode("\n", $lines);
    }

    /**
     * Build an HTML email body for the fallback path.
     */
    private static function buildEmailBody(array $lead): string
    {
        $rows = '';
        $fields = [
            'Name'     => $lead['full_name'] ?? '',
            'Email'    => $lead['email'] ?? '',
            'Phone'    => $lead['phone'] ?? '',
            'City'     => $lead['city'] ?? '',
            'State'    => $lead['state'] ?? '',
            'ZIP'      => $lead['zip'] ?? '',
            'Ad ID'    => $lead['ad_id'] ?? '',
            'Campaign' => $lead['campaign_id'] ?? '',
        ];
        foreach ($fields as $label => $value) {
            if ($value !== '') {
                $rows .= '<tr><td style="padding:4px 12px 4px 0;color:#c9c6b6;font-weight:600">' . htmlspecialchars($label, ENT_QUOTES) . '</td>'
                    . '<td style="padding:4px 0;color:#e8e6da">' . htmlspecialchars($value, ENT_QUOTES) . '</td></tr>';
            }
        }

        return '<div style="font-family:Arial,Helvetica,sans-serif;background:#12140d;padding:24px">'
            . '<div style="max-width:560px;margin:0 auto;background:#1b1e14;border:1px solid #3a3f2c;border-radius:6px;padding:32px;color:#e8e6da">'
            . '<div style="font-size:13px;letter-spacing:2px;color:#c8a24a;font-weight:bold;text-transform:uppercase">★ Patriot Pest Control</div>'
            . '<h1 style="font-size:22px;color:#f2efe2;margin:18px 0 10px">New Facebook Lead</h1>'
            . '<p style="font-size:14px;color:#c9c6b6;margin:0 0 18px">A new lead was received from Facebook Lead Ads. SMS delivery was attempted first.</p>'
            . '<table cellpadding="0" cellspacing="0" style="border-collapse:collapse">' . $rows . '</table>'
            . '<p style="font-size:12px;color:#8a8f7a;margin-top:28px">This is an automated lead notification. '
            . 'Log into the Patriot Pest dashboard to manage this lead.</p>'
            . '</div></div>';
    }

    /**
     * Minimal SMTP send via STARTTLS (port 587).
     *
     * Uses LEAD_SMTP_* config keys so it is independent of the app's MAIL_*
     * config. Titan credentials never leave this call path.
     */
    private static function smtpSend(string $to, string $subject, string $body): array
    {
        $host     = Config::get('LEAD_SMTP_HOST', 'smtp.titan.email');
        $port     = Config::int('LEAD_SMTP_PORT', 587);
        $security = Config::get('LEAD_SMTP_SECURITY', 'tls');
        $user     = Config::get('LEAD_SMTP_USERNAME', 'PPC_Info@patriotpest.pro');
        $pass     = Config::get('LEAD_SMTP_PASSWORD', '');
        $from     = Config::get('LEAD_SMS_FROM', 'PPC_Info@patriotpest.pro');
        $fromName = Config::get('MAIL_FROM_NAME', 'Patriot Pest Control');

        $remote = $host . ':' . $port;
        $sock   = @stream_socket_client('tcp://' . $remote, $errno, $errstr, 15);
        if (!$sock) {
            Logger::error('Lead SMTP connect failed', ['host' => $host, 'port' => $port, 'error' => $errstr]);
            return ['success' => false, 'error' => "SMTP connect failed: $errstr"];
        }
        stream_set_timeout($sock, 15);

        $read = function () use ($sock): string {
            $data = '';
            while (($line = fgets($sock, 515)) !== false) {
                $data .= $line;
                if (strlen($line) < 4 || $line[3] !== '-') {
                    break;
                }
            }
            return $data;
        };
        $send = function (string $cmd) use ($sock, $read): string {
            fwrite($sock, $cmd . "\r\n");
            return $read();
        };

        try {
            $read(); // greeting

            $send('EHLO patriotpest.pro');
            if ($security === 'tls') {
                $send('STARTTLS');
                if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    fclose($sock);
                    Logger::error('Lead SMTP STARTTLS failed');
                    return ['success' => false, 'error' => 'STARTTLS negotiation failed'];
                }
                $send('EHLO patriotpest.pro');
            }

            if ($user !== '' && $pass !== '') {
                $send('AUTH LOGIN');
                $send(base64_encode($user));
                $send(base64_encode($pass));
            }

            $send("MAIL FROM:<$from>");
            $send("RCPT TO:<$to>");
            $send('DATA');

            $headers  = "From: $fromName <$from>\r\n"
                . "To: <$to>\r\n"
                . "Subject: $subject\r\n"
                . "Date: " . date('r') . "\r\n"
                . "MIME-Version: 1.0\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n"
                . "Message-ID: <" . bin2hex(random_bytes(12)) . "@patriotpest.pro>\r\n";

            fwrite($sock, $headers . "\r\n" . $body . "\r\n.\r\n");
            $resp = $read();
            $send('QUIT');
            fclose($sock);

            $ok = str_starts_with(trim($resp), '250');
            if (!$ok) {
                Logger::error('Lead SMTP DATA rejected', ['to' => $to, 'response' => trim($resp)]);
                return ['success' => false, 'error' => 'SMTP DATA rejected: ' . trim($resp)];
            }

            Logger::info('Lead SMTP sent', ['to' => $to, 'subject' => $subject]);
            return ['success' => true];
        } catch (\Throwable $e) {
            Logger::error('Lead SMTP send failed', ['to' => $to, 'error' => $e->getMessage()]);
            @fclose($sock);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
