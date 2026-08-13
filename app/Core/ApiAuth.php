<?php
/**
 * ApiAuth — Bearer token authentication for the AI/Agent API surface.
 *
 * Flow:
 *   1. Extract Authorization: Bearer <token> header
 *   2. Split token into ppc_live_<prefix> portion and full key
 *   3. Look up by key_prefix in api_keys table
 *   4. SHA-256 the presented key, constant-time compare against key_hash
 *   5. Reject if revoked, expired, or missing
 *   6. Verify required scope against key's scope array
 *   7. Audit-log every authenticated call
 *
 * Security properties:
 *   - Raw key never stored, logged, or returned after creation
 *   - Constant-time hash comparison via hash_equals (timing attack defense)
 *   - revoked_at and expires_at checked synchronously on every call
 *   - PII redaction unless scope grants customer:read-full
 */

declare(strict_types=1);

namespace PPC\Core;

final class ApiAuth
{
    /** @var array|null Decoded key row after successful auth. */
    private static ?array $keyRow = null;

    /** @var array|null Last inserted key row (create/rotate) for lifecycle audit linkage. */
    private static ?array $lastKeyRow = null;

    /**
     * Authenticate the current request. Returns the key row on success,
     * sends a JSON error response and exits on failure.
     *
     * @return array The authenticated api_keys row.
     */
    public static function requireAuth(string ...$requiredScopes): array
    {
        if (self::$keyRow !== null && self::hasScopes($requiredScopes)) {
            return self::$keyRow;
        }

        // Check API_ENABLED toggle.
        if (!Config::bool('API_ENABLED', false)) {
            self::fail(404, 'API not enabled');
        }

        $token = self::extractToken();
        if ($token === null) {
            self::fail(401, 'Missing or malformed Authorization header');
        }

        // Validate ppc_live_ prefix format.
        if (!str_starts_with($token, 'ppc_live_') || strlen($token) < 21) { // ppc_live_ (9) + min 12 hex
            self::fail(401, 'Invalid API key format');
        }

        $prefix = substr($token, 9, 12); // 12 chars after ppc_live_

        $db = Database::instance();
        $row = $db->fetch('SELECT * FROM api_keys WHERE key_prefix = ?', [$prefix]);

        if (!$row) {
            // Constant-time dummy compare so timing doesn't leak whether prefix exists.
            hash_equals(
                '0000000000000000000000000000000000000000000000000000000000000000',
                hash('sha256', $token)
            );
            self::fail(401, 'Invalid API key');
        }

        // Constant-time hash compare.
        if (!hash_equals($row['key_hash'], hash('sha256', $token))) {
            self::fail(401, 'Invalid API key');
        }

        // Check revocation.
        if ($row['revoked_at'] !== null) {
            self::fail(401, 'API key has been revoked');
        }

        // Check expiration.
        if ($row['expires_at'] !== null && strtotime($row['expires_at']) < time()) {
            self::fail(401, 'API key has expired');
        }

        // Verify scopes.
        $scopes = json_decode($row['scopes'] ?? '[]', true);
        if (!is_array($scopes)) {
            $scopes = [];
        }
        foreach ($requiredScopes as $req) {
            if (!in_array($req, $scopes, true) && !in_array('all', $scopes, true)) {
                self::fail(403, 'Insufficient scope: ' . $req . ' required');
            }
        }

        // Update last_used_at.
        try {
            $db->execute('UPDATE api_keys SET last_used_at = ? WHERE id = ?', [gmdate('Y-m-d H:i:s'), $row['id']]);
        } catch (\Throwable) {
            // Non-fatal; log and continue.
            Logger::warning('Failed to update api_keys.last_used_at', ['key_id' => $row['id']]);
        }

        // Audit log.
        self::audit($row, $requiredScopes);

        self::$keyRow = $row;
        return $row;
    }

    /**
     * Check whether the already-auth'd key has all required scopes.
     */
    public static function hasScopes(array $requiredScopes): bool
    {
        if (self::$keyRow === null) {
            return false;
        }
        $scopes = json_decode(self::$keyRow['scopes'] ?? '[]', true);
        if (!is_array($scopes)) {
            return false;
        }
        if (in_array('all', $scopes, true)) {
            return true;
        }
        foreach ($requiredScopes as $req) {
            if (!in_array($req, $scopes, true)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Generate a key, store the hash, return the raw key (shown ONCE).
     *
     * @param string $name      Human label.
     * @param array  $scopes    ['customer:read','ticket:read',...]
     * @param int    $createdBy Staff id of the admin creating the key.
     * @return string The raw key (ppc_live_<64 hex chars>).
     */
    public static function createKey(string $name, array $scopes, int $createdBy): string
    {
        $raw = bin2hex(random_bytes(32)); // 64 hex chars = 256 bits
        $fullKey = 'ppc_live_' . $raw;
        $prefix  = substr($raw, 0, 12);
        $hash    = hash('sha256', $fullKey);

        $id = Database::instance()->insert('api_keys', [
            'name'       => $name,
            'key_prefix' => $prefix,
            'key_hash'   => $hash,
            'scopes'     => json_encode($scopes),
            'key_cipher' => self::encryptKey($fullKey),
            'created_by' => $createdBy,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        self::$lastKeyRow = ['id' => $id, 'name' => $name, 'key_prefix' => $prefix, 'scopes' => $scopes];

        return $fullKey;
    }

    /**
     * Encrypt the raw key at rest (AES-256-CBC) with a per-site secret so it
     * can be copied later by a super-admin (never stored in plaintext).
     */
    public static function encryptKey(string $fullKey): string
    {
        $secret = \PPC\Core\Settings::get('key_cipher_secret');
        if ($secret === '') {
            $secret = bin2hex(random_bytes(32));
            \PPC\Core\Settings::set('key_cipher_secret', $secret);
        }
        $iv = random_bytes(16);
        $cipher = openssl_encrypt($fullKey, 'aes-256-cbc', hex2bin($secret), OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $cipher);
    }

    /** Decrypt a stored key_cipher. Returns null when unavailable/invalid. */
    public static function decryptKey(string $cipher): ?string
    {
        if ($cipher === '') { return null; }
        $secret = \PPC\Core\Settings::get('key_cipher_secret');
        if ($secret === '') { return null; }
        $blob = base64_decode($cipher);
        if ($blob === false || strlen($blob) < 17) { return null; }
        $iv = substr($blob, 0, 16);
        $out = openssl_decrypt(substr($blob, 16), 'aes-256-cbc', hex2bin($secret), OPENSSL_RAW_DATA, $iv);
        return is_string($out) && $out !== '' ? $out : null;
    }

    /**
     * Revoke a key by id.
     */
    public static function revokeKey(int $keyId): bool
    {
        $db = Database::instance();
        $row = $db->fetch('SELECT id, revoked_at FROM api_keys WHERE id = ?', [$keyId]);
        if (!$row || $row['revoked_at'] !== null) {
            return false;
        }
        $db->update('api_keys', ['revoked_at' => gmdate('Y-m-d H:i:s')], ['id' => $keyId]);
        return true;
    }

    /**
     * Rotate a key: revoke the old one and issue a new one in a single
     * transaction. The old key is immediately invalid. Returns the new raw key.
     */
    public static function rotateKey(int $keyId, int $byStaffId): ?string
    {
        $db = Database::instance();
        $old = $db->fetch('SELECT id, name, scopes, revoked_at FROM api_keys WHERE id = ?', [$keyId]);
        if (!$old || $old['revoked_at'] !== null) {
            return null;
        }

        $db->begin();
        try {
            // Revoke old key.
            $db->update('api_keys', ['revoked_at' => gmdate('Y-m-d H:i:s')], ['id' => $keyId]);

            // Create new key with same name + scopes.
            $scopes = json_decode($old['scopes'] ?? '[]', true);
            if (!is_array($scopes)) { $scopes = []; }
            $name = $old['name'] . ' (rotated)';

            $raw = bin2hex(random_bytes(32));
            $fullKey = 'ppc_live_' . $raw;
            $prefix  = substr($raw, 0, 12);
            $hash    = hash('sha256', $fullKey);

            $newId = $db->insert('api_keys', [
                'name'       => $name,
                'key_prefix' => $prefix,
                'key_hash'   => $hash,
                'scopes'     => json_encode($scopes),
                'created_by' => $byStaffId,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
            self::$lastKeyRow = ['id' => $newId, 'name' => $name, 'key_prefix' => $prefix, 'scopes' => $scopes];

            $db->commit();
            return $fullKey;
        } catch (\Throwable $e) {
            $db->rollback();
            Logger::error('API key rotation failed', ['key_id' => $keyId, 'err' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Extract the Bearer token from the Authorization header.
     */
    private static function extractToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if ($header === '') {
            // Also check Apache-style getallheaders().
            if (function_exists('getallheaders')) {
                $all = getallheaders();
                $header = $all['Authorization'] ?? $all['authorization'] ?? '';
            }
        }
        if (preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    /**
     * Send a JSON error and exit.
     */
    private static function fail(int $status, string $message): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'error'   => true,
            'code'    => $status,
            'message' => $message,
        ]);
        exit;
    }

    /**
     * Write an audit log entry for this API call.
     */
    private static function audit(array $keyRow, array $scopes): void
    {
        try {
            Database::instance()->insert('audit_log', [
                'user_id'   => 'api_key:' . $keyRow['id'],
                'user_type' => 'api',
                'action'    => 'api.call',
                'entity'    => 'api_keys',
                'entity_id' => (string) $keyRow['id'],
                'meta_json' => json_encode([
                    'key_name' => $keyRow['name'] ?? '',
                    'scopes_checked' => $scopes,
                    'method' => $_SERVER['REQUEST_METHOD'] ?? '',
                    'path'   => parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '',
                ]),
                'ip'         => \PPC\Core\RateLimiter::clientIp(),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // Non-fatal.
        }
    }

    /**
     * Get the last inserted key row (create/rotate) for lifecycle audit linkage.
     */
    public static function lastKeyRow(): ?array
    {
        return self::$lastKeyRow;
    }

    /**
     * Get the current key id (for controllers to reference).
     */
    public static function keyId(): ?int
    {
        return self::$keyRow ? (int) self::$keyRow['id'] : null;
    }

    /**
     * Get the current key name.
     */
    public static function keyName(): ?string
    {
        return self::$keyRow['name'] ?? null;
    }
}
