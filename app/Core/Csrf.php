<?php
/**
 * Csrf - Cross-Site Request Forgery protection.
 *
 * The old staff-dashboard POST handlers (save settings, save/delete districts)
 * and several API actions had NO CSRF protection - only role checks. This class
 * provides the standard mitigating pattern:
 *
 *   - one random token per session, embedded in every form as a hidden field,
 *   - verified with hash_equals() on every state-changing request,
 *   - also accepted via an X-CSRF-Token header for AJAX/fetch calls.
 *
 * Usage in a template:  <?= Csrf::field() ?>
 * Usage in a handler:   Csrf::verifyOrDie();   // or Csrf::check($token)
 */

declare(strict_types=1);

namespace PPC\Core;

final class Csrf
{
    /** Form field / header names. */
    public const FIELD  = '_csrf';
    public const HEADER = 'X-CSRF-Token';

    /**
     * Get (or lazily create) this session's CSRF token.
     */
    public static function token(): string
    {
        $t = Session::get('_csrf_token');
        if (!is_string($t) || strlen($t) < 32) {
            $t = bin2hex(random_bytes(32)); // 64 hex chars, cryptographically random
            Session::put('_csrf_token', $t);
        }
        return $t;
    }

    /**
     * Render the hidden form field to embed in every POST form.
     */
    public static function field(): string
    {
        return '<input type="hidden" name="' . self::FIELD . '" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Validate a supplied token against the session token (constant-time).
     */
    public static function check(?string $token): bool
    {
        if (!is_string($token) || $token === '') {
            return false;
        }
        $expected = Session::get('_csrf_token');
        if (!is_string($expected)) {
            return false;
        }
        return hash_equals($expected, $token);
    }

    /**
     * Pull the token from the request (POST body first, then header for AJAX).
     */
    public static function fromRequest(): ?string
    {
        if (!empty($_POST[self::FIELD])) {
            return (string) $_POST[self::FIELD];
        }
        // AJAX: check the header (HTTP_X_CSRF_TOKEN).
        $hdr = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        return $hdr ? (string) $hdr : null;
    }

    /**
     * Verify the current request's token; abort with 419 if invalid.
     * Call at the top of every state-changing handler (POST/PUT/DELETE).
     */
    public static function verifyOrDie(): void
    {
        if (!self::check(self::fromRequest())) {
            Logger::warning('CSRF check failed', [
                'path' => $_SERVER['REQUEST_URI'] ?? '',
                'ip'   => $_SERVER['REMOTE_ADDR'] ?? '',
            ]);
            http_response_code(419);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid or missing CSRF token. Please refresh and retry.']);
            exit;
        }
    }
}
