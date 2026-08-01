<?php
/**
 * Session - hardened session management.
 *
 * Fixes the security debt in the old bootstrap:
 *   - use_strict_mode = 1  (rejects uninitialized session ids → session-fixation defense)
 *   - cookie_httponly + cookie_samesite=Lax (XSS/CSRF mitigation)
 *   - cookie_secure automatically on HTTPS
 *   - per-role idle timeouts (customer shorter than staff/admin)
 *   - regenerate() wrapper used at every privilege change (login, role bump)
 *
 * Sessions are the backbone of the passwordless-OTP auth: after a code is
 * verified we regenerate the id and store user_type + identity here.
 */

declare(strict_types=1);

namespace PPC\Core;

final class Session
{
    /** @var bool Guard so start() runs once per request. */
    private static bool $started = false;

    /**
     * Configure and start the session. Call early in the front controller.
     */
    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? 0) == 443;

        // Strict mode: PHP won't accept a session id it didn't create (fixation defense).
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        if ($isSecure) {
            ini_set('session.cookie_secure', '1');
        }
        // Don't leak the session id in URLs.
        ini_set('session.use_trans_sid', '0');
        ini_set('session.sid_length', '48');
        ini_set('session.sid_bits_per_character', '6');

        session_name('PPC_SESSION');
        session_start();
        self::$started = true;

        self::enforceIdleTimeout();
        // Touch last-activity on every request.
        $_SESSION['_last_activity'] = time();
    }

    /**
     * Log the user out if they've been idle longer than their role allows.
     * Customer sessions are short-lived; staff get a longer window.
     */
    private static function enforceIdleTimeout(): void
    {
        $last = $_SESSION['_last_activity'] ?? null;
        if ($last === null) {
            return; // fresh session, nothing to enforce yet
        }

        $type    = $_SESSION['user_type'] ?? 'guest';
        $timeout = $type === 'customer'
            ? Config::int('SESSION_LIFETIME_CUSTOMER', 900)
            : Config::int('SESSION_LIFETIME_STAFF', 7200);

        if (time() - (int) $last > $timeout) {
            self::destroy();
            self::start(); // start a clean session after expiry
        }
    }

    /**
     * Regenerate the session id (call on login / privilege change).
     * Keeps existing session data, issues a new id, invalidates the old one.
     */
    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /** Fully destroy the session (logout). */
    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        self::$started = false;
    }

    /** Set a value. */
    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /** Get a value (null if absent). */
    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    /** Remove a value (used for one-time data like pending OTP state). */
    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * One-shot flash data (survives exactly one subsequent request).
     * Useful for "Your code was sent" / error banners after a redirect.
     */
    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    /** Read + clear a flash value. */
    public static function pullFlash(string $key, mixed $default = null): mixed
    {
        $v = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $v;
    }

    /** Is anyone authenticated (any user_type)? */
    public static function authenticated(): bool
    {
        return !empty($_SESSION['user_id']) && !empty($_SESSION['user_type']);
    }

    /** Current user type ('staff' | 'customer' | null). */
    public static function userType(): ?string
    {
        return $_SESSION['user_type'] ?? null;
    }

    /** Current staff role (null if not staff). */
    public static function staffRole(): ?string
    {
        return ($_SESSION['user_type'] ?? null) === 'staff' ? ($_SESSION['staff_role'] ?? null) : null;
    }

    /** True if the current staff user is an admin. */
    public static function isAdmin(): bool
    {
        return self::staffRole() === 'admin';
    }
}
