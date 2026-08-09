<?php
/**
 * OtpAuth — passwordless one-time-code authentication.
 *
 * This is the heart of the login system (no passwords anywhere):
 *   1. issue()  — generate a 6-digit code, store a HASH of it (never plaintext),
 *                 with expiry + single-use flag, and return the plaintext code
 *                 so the caller can email/text it.
 *   2. verify() — constant-time compare against the stored hash, enforcing:
 *                 expiry, single-use, and a brute-force attempt cap (via
 *                 RateLimiter). On success the code is consumed.
 *
 * Security properties (vs. the old implementation):
 *   - codes are hashed with password_hash() at rest,
 *   - attempts are counted and lock out after OTP_MAX_ATTEMPTS,
 *   - codes expire after OTP_TTL seconds,
 *   - verification uses hash_equals() (timing-safe),
 *   - each code is single-use (used_at set on success).
 */

declare(strict_types=1);

namespace PPC\Auth;

use PPC\Core\Config;
use PPC\Core\Database;
use PPC\Core\Logger;
use PPC\Core\RateLimiter;

final class OtpAuth
{
    /**
     * Issue a new code for $identity. Invalidates any prior unused codes for
     * the same identity+purpose (so only the latest works).
     *
     * @param string $identity Email or phone the code is bound to.
     * @param string $purpose  e.g. 'staff_login', 'customer_login', 'sms_verify'.
     * @return string The plaintext 6-digit code (hand this to the Mailer/SMS).
     */
    public static function issue(string $identity, string $purpose, int $length = 6): string
    {
        $db  = Database::instance();
        $ttl = Config::int('OTP_TTL', 600);

        // Invalidate older unused codes for this identity+purpose.
        $db->execute(
            "UPDATE otp_codes SET used_at = datetime('now')
             WHERE identity = ? AND purpose = ? AND used_at IS NULL",
            [$identity, $purpose]
        );

        // Cryptographically random N-digit code ($length defaults to 6 for backward compat).
        $min = 10 ** ($length - 1);
        $max = (10 ** $length) - 1;
        $code = (string) random_int($min, $max);

        $db->insert('otp_codes', [
            'identity'   => $identity,
            'purpose'    => $purpose,
            'code_hash'  => password_hash($code, PASSWORD_DEFAULT),
            'attempts'   => 0,
            'expires_at' => date('Y-m-d H:i:s', time() + $ttl),
            'ip'         => RateLimiter::clientIp(),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        Logger::info('OTP issued', ['identity' => $identity, 'purpose' => $purpose]);
        return $code;
    }

    /**
     * Verify a submitted code. Returns true only if it matches an unexpired,
     * unused code AND the identity isn't rate-limited. Consumes the code on
     * success and clears the rate-limit counter.
     *
     * @param string $identity Email/phone the code was issued to.
     * @param string $purpose  Must match the purpose it was issued for.
     * @param string $code     The user-submitted code.
     */
    public static function verify(string $identity, string $purpose, string $code): bool
    {
        $db          = Database::instance();
        $maxAttempts = Config::int('OTP_MAX_ATTEMPTS', 5);
        $window      = Config::int('OTP_TTL', 600);
        $limitKey    = "otp:$purpose:$identity";

        // Brute-force guard: too many recent failures → reject outright.
        if (RateLimiter::tooMany($limitKey, $maxAttempts, $window)) {
            Logger::warning('OTP verify blocked by rate limit', ['identity' => $identity, 'purpose' => $purpose]);
            return false;
        }

        // Find the latest unused, unexpired code for this identity+purpose.
        $row = $db->fetch(
            "SELECT id, code_hash, attempts FROM otp_codes
             WHERE identity = ? AND purpose = ? AND used_at IS NULL AND expires_at > datetime('now')
             ORDER BY id DESC LIMIT 1",
            [$identity, $purpose]
        );

        if ($row === null) {
            RateLimiter::hit($limitKey, false);
            return false;
        }

        // Timing-safe comparison against the stored hash.
        $ok = password_verify($code, $row['code_hash']);

        if (!$ok) {
            // Count the miss (both on the row and the rate limiter).
            $db->execute('UPDATE otp_codes SET attempts = attempts + 1 WHERE id = ?', [$row['id']]);
            RateLimiter::hit($limitKey, false);
            Logger::warning('OTP verify failed', ['identity' => $identity, 'purpose' => $purpose]);
            return false;
        }

        // Success: consume the code (single-use) and clear the limiter.
        $db->execute("UPDATE otp_codes SET used_at = datetime('now') WHERE id = ?", [$row['id']]);
        RateLimiter::clear($limitKey);
        RateLimiter::hit($limitKey, true); // record the success for audit
        Logger::info('OTP verified', ['identity' => $identity, 'purpose' => $purpose]);
        return true;
    }

    /**
     * Seconds remaining before a locked-out identity can retry (0 = not locked).
     */
    public static function retryAfter(string $identity, string $purpose): int
    {
        $maxAttempts = Config::int('OTP_MAX_ATTEMPTS', 5);
        $window      = Config::int('OTP_TTL', 600);
        return RateLimiter::retryAfter("otp:$purpose:$identity", $maxAttempts, $window);
    }
}
