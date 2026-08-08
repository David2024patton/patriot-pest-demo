<?php
/**
 * RateLimiter — sliding-window attempt limiting.
 *
 * The old OTP flow had NO brute-force protection: a 6-digit code could be
 * guessed by hammering the endpoint. This limiter counts recent attempts per
 * key (email/phone and/or IP) in the login_attempts table and blocks once a
 * threshold is crossed within a time window.
 *
 * Usage:
 *   if (RateLimiter::tooMany("otp:$email", 5, 900)) { show lockout; }
 *   RateLimiter::hit("otp:$email", $ip, false);   // record a failed try
 *   RateLimiter::clear("otp:$email");             // on success
 */

declare(strict_types=1);

namespace PPC\Core;

final class RateLimiter
{
    /**
     * Has $key exceeded $maxAttempts within the last $window seconds?
     *
     * @param string $key         Identifier being limited (e.g. "otp:user@x.com").
     * @param int    $maxAttempts Allowed attempts in the window.
     * @param int    $window      Window length in seconds.
     */
    public static function tooMany(string $key, int $maxAttempts = 5, int $window = 900): bool
    {
        $db    = Database::instance();
        $since = date('Y-m-d H:i:s', time() - $window);
        $count = (int) $db->scalar(
            'SELECT COUNT(*) FROM login_attempts WHERE identity = ? AND created_at > ? AND success = 0',
            [$key, $since]
        );
        return $count >= $maxAttempts;
    }

    /**
     * Best-effort real client IP. Behind the Dokploy reverse proxy, REMOTE_ADDR
     * is the proxy itself for every request, which would collapse all rate
     * limit keys into one. Trust the leftmost X-Forwarded-For entry the proxy
     * appends; fall back to REMOTE_ADDR when no proxy header exists.
     */
    public static function clientIp(): string
    {
        $xff = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
        if ($xff !== '') {
            $first = trim(explode(',', $xff)[0] ?? '');
            if ($first !== '') {
                return $first;
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Record an attempt for $key.
     *
     * @param string $key     Identifier.
     * @param bool   $success Whether the attempt succeeded.
     * @param string|null $ip Client IP (for correlation/auditing).
     */
    public static function hit(string $key, bool $success = false, ?string $ip = null): void
    {
        try {
            Database::instance()->insert('login_attempts', [
                'identity'   => $key,
                'ip'         => $ip ?? self::clientIp(),
                'user_agent' => mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                'success'    => $success ? 1 : 0,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // Rate limiting must never break the request; log and continue.
            Logger::warning('RateLimiter hit failed', ['key' => $key, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Clear recorded failures for $key (called after a successful auth).
     */
    public static function clear(string $key): void
    {
        try {
            Database::instance()->execute('DELETE FROM login_attempts WHERE identity = ? AND success = 0', [$key]);
        } catch (\Throwable $e) {
            Logger::warning('RateLimiter clear failed', ['key' => $key]);
        }
    }

    /**
     * Seconds until $key is allowed to retry (0 if not locked).
     */
    public static function retryAfter(string $key, int $maxAttempts = 5, int $window = 900): int
    {
        if (!self::tooMany($key, $maxAttempts, $window)) {
            return 0;
        }
        $db   = Database::instance();
        $last = $db->scalar(
            'SELECT MAX(created_at) FROM login_attempts WHERE identity = ? AND success = 0',
            [$key]
        );
        if (!$last) {
            return 0;
        }
        $unlock = strtotime((string) $last) + $window;
        return max(0, $unlock - time());
    }
}
