<?php
/**
 * Mailer - outbound email (login codes, reactivation campaigns, notifications).
 *
 * Two transports:
 *   - local / debug: writes the full message to storage/logs/mail-YYYY-MM-DD.log
 *     so OTP codes and campaigns can be inspected without a real mailbox
 *     (this is an intentional debugging affordance - codes are visible in dev).
 *   - production: sends over SMTP (SSL/TLS) using the MAIL_* config.
 *
 * The SMTP client here is deliberately small (EHLO/AUTH LOGIN/MAIL/RCPT/DATA).
 * If MAIL_HOST is empty or APP_ENV is local, it logs instead of sending.
 */

declare(strict_types=1);

namespace PPC\Auth;

use PPC\Core\Config;
use PPC\Core\Logger;

final class Mailer
{
    /**
     * Send an email (or log it, when local/unconfigured).
     *
     * @param string $to      Recipient address.
     * @param string $subject Subject line.
     * @param string $body    Body (HTML allowed; a plain-text alt is derived).
     * @return bool True if sent (or logged in dev), false on failure.
     */
    public static function send(string $to, string $subject, string $body): bool
    {
        $from     = Config::get('MAIL_FROM_ADDRESS', 'no-reply@patriotpest.pro');
        $fromName = Config::get('MAIL_FROM_NAME', 'Patriot Pest Control');
        $host     = Config::get('MAIL_HOST', '');

        // Always keep a local copy for debugging/auditing.
        self::log($to, $subject, $body);

        // In local dev (or with no SMTP configured) we stop at the log - the
        // code/campaign is readable in storage/logs/mail-*.log.
        if (Config::isLocal() || $host === '') {
            Logger::info('Mail logged (dev mode, not sent)', ['to' => $to, 'subject' => $subject]);
            return true;
        }

        return self::smtp($host, $to, $from, $fromName, $subject, $body);
    }

    /** Append the message to today's mail log. */
    private static function log(string $to, string $subject, string $body): void
    {
        $dir = BASE_PATH . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $entry = str_repeat('=', 70) . "\n"
            . '[' . date('c') . "] TO: $to\nSUBJECT: $subject\n"
            . str_repeat('-', 70) . "\n$body\n\n";
        @file_put_contents($dir . '/mail-' . date('Y-m-d') . '.log', $entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Minimal SMTP send over SSL. Returns true on success.
     */
    private static function smtp(string $host, string $to, string $from, string $fromName, string $subject, string $body): bool
    {
        $port     = Config::int('MAIL_PORT', 465);
        $user     = Config::get('MAIL_USERNAME', '');
        $pass     = Config::get('MAIL_PASSWORD', '');
        $security = Config::get('MAIL_SECURITY', 'ssl');

        $remote = ($security === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $sock   = @stream_socket_client($remote, $errno, $errstr, 15);
        if (!$sock) {
            Logger::error('SMTP connect failed', ['host' => $host, 'error' => $errstr]);
            return false;
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
            if ($user !== '') {
                $send('AUTH LOGIN');
                $send(base64_encode($user));
                $send(base64_encode($pass));
            }
            $send("MAIL FROM:<$from>");
            $send("RCPT TO:<$to>");
            $send('DATA');

            // Build headers + body.
            $boundary = 'PPC-' . bin2hex(random_bytes(8));
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
                Logger::error('SMTP DATA rejected', ['to' => $to, 'response' => trim($resp)]);
            }
            return $ok;
        } catch (\Throwable $e) {
            Logger::error('SMTP send failed', ['to' => $to, 'error' => $e->getMessage()]);
            @fclose($sock);
            return false;
        }
    }

    /**
     * Wrap a message body in the branded email template (used for OTP + campaigns).
     */
    public static function template(string $heading, string $innerHtml, ?string $unsubscribeUrl = null): string
    {
        $unsub = $unsubscribeUrl
            ? '<p style="font-size:12px;color:#8a8f7a;margin-top:28px"><a href="' . htmlspecialchars($unsubscribeUrl, ENT_QUOTES) . '" style="color:#8a8f7a">Unsubscribe from these messages</a></p>'
            : '';
        return '<div style="font-family:Arial,Helvetica,sans-serif;background:#12140d;padding:24px">'
            . '<div style="max-width:560px;margin:0 auto;background:#1b1e14;border:1px solid #3a3f2c;border-radius:6px;padding:32px;color:#e8e6da">'
            . '<div style="font-size:13px;letter-spacing:2px;color:#c8a24a;font-weight:bold;text-transform:uppercase">★ Patriot Pest Control</div>'
            . '<h1 style="font-size:22px;color:#f2efe2;margin:18px 0 10px">' . $heading . '</h1>'
            . $innerHtml
            . $unsub
            . '<p style="font-size:12px;color:#8a8f7a;margin-top:28px">Veteran-owned · WA / ID / OR / AZ · (509) 471-5767</p>'
            . '</div></div>';
    }
}
