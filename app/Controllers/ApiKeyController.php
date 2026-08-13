<?php
declare(strict_types=1);
namespace PPC\Controllers;
use PPC\Core\ApiAuth;
use PPC\Core\Database;
use PPC\Core\Session;
use PPC\Core\Csrf;
use PPC\Core\Logger;
use PPC\Core\RateLimiter;
use PPC\Core\Router;

class ApiKeyController
{
    /** Only super-admins manage API keys (regular admins get a 404). */
    private static function superAdminOnly(): void
    {
        if (Session::staffRole() !== 'super-user') {
            Router::notFound();
        }
    }

    /** List all API keys. */
    public function index(): void
    {
        self::superAdminOnly();
        $db = Database::instance();
        $keys = $db->fetchAll(
            'SELECT * FROM api_keys ORDER BY created_at DESC'
        );
        echo \PPC\Core\View::page('admin/api-keys', [
            'keys'    => $keys,
            'isAdmin' => Session::isAdmin(),
            'flash'   => Session::pullFlash('api_keys'),
        ], $this->meta('API Keys | Patriot Pest Control', 'Manage API keys for AI/Agent access.', '/admin/api-keys'));
    }

    /** Reveal a stored (encrypted-at-rest) key so its owner can copy it again. Audited. */
    public function reveal(string $id): void
    {
        self::superAdminOnly();
        Csrf::verifyOrDie();
        header('Content-Type: application/json');
        $row = Database::instance()->fetch('SELECT id, name, key_cipher, revoked_at FROM api_keys WHERE id = ?', [(int) $id]);
        if ($row === null) {
            echo json_encode(['ok' => false, 'error' => 'Key not found.']);
            exit;
        }
        if ($row['revoked_at'] !== null) {
            echo json_encode(['ok' => false, 'error' => 'Key is revoked — create a new one.']);
            exit;
        }
        $raw = ApiAuth::decryptKey((string) $row['key_cipher']);
        if ($raw === null) {
            echo json_encode(['ok' => false, 'error' => 'Key was created before encrypted storage; rotate it to enable copying.']);
            exit;
        }
        Logger::info('API key revealed for copy', ['key' => $row['name'], 'by' => Session::get('user_id')]);
        echo json_encode(['ok' => true, 'key' => $raw, 'name' => $row['name']]);
        exit;
    }

    /** Create a new API key. Returns a page showing the raw key ONCE. */
    public function create(): void
    { self::superAdminOnly();
        Csrf::verifyOrDie();
        $name   = trim((string) ($_POST['name'] ?? ''));
        $scopes = array_filter(array_map('trim', explode(',', (string) ($_POST['scopes'] ?? ''))), 'strlen');

        if ($name === '') {
            Session::flash('api_keys', ['errors' => ['name' => ['Name is required.']]]);
            header('Location: /admin/api-keys');
            exit;
        }
        if (!$scopes) {
            Session::flash('api_keys', ['errors' => ['scopes' => ['At least one scope is required.']]]);
            header('Location: /admin/api-keys');
            exit;
        }

        $key = ApiAuth::createKey($name, $scopes, (int) (Session::get('user_id') ?? 0));
        $created = ApiAuth::lastKeyRow() ?? [];

        self::auditLifecycle('api_key.create', isset($created['id']) ? (int) $created['id'] : null, [
            'name'   => $name,
            'prefix' => $created['key_prefix'] ?? '',
            'scopes' => $scopes,
        ]);

        // Show the raw key once.
        echo \PPC\Core\View::page('admin/api-key-show', [
            'rawKey'  => $key,
            'name'    => $name,
            'scopes'  => $scopes,
            'isAdmin' => Session::isAdmin(),
        ], $this->meta('New API Key | Patriot Pest Control', 'Copy this key now.', '/admin/api-keys'));
    }

    /** Revoke an API key. */
    public function revoke(string $id): void
    { self::superAdminOnly();
        Csrf::verifyOrDie();
        $row = Database::instance()->fetch('SELECT id, name, key_prefix, scopes FROM api_keys WHERE id = ?', [$id]);
        $ok = ApiAuth::revokeKey((int) $id);
        if ($ok) {
            Session::flash('api_keys', ['success' => 'API key revoked.']);
            self::auditLifecycle('api_key.revoke', (int)$id, [
                'name'          => $row['name'] ?? '',
                'prefix'        => $row['key_prefix'] ?? '',
                'scopes_before' => json_decode($row['scopes'] ?? '[]', true) ?: [],
            ]);
        } else {
            Session::flash('api_keys', ['errors' => ['key' => ['Key not found or already revoked.']]]);
        }
        header('Location: /admin/api-keys');
        exit;
    }

    /** Rotate an API key — creates a new one and revokes the old in one TX. */
    public function rotate(string $id): void
    { self::superAdminOnly();
        Csrf::verifyOrDie();
        $old = Database::instance()->fetch('SELECT id, name, key_prefix, scopes FROM api_keys WHERE id = ?', [$id]);
        $newKey = ApiAuth::rotateKey((int) $id, (int) (Session::get('user_id') ?? 0));
        if ($newKey) {
            $rotated = ApiAuth::lastKeyRow() ?? [];
            self::auditLifecycle('api_key.rotate', (int)$id, [
                'name'           => $old['name'] ?? '',
                'prefix'         => $old['key_prefix'] ?? '',
                'scopes_before'  => json_decode($old['scopes'] ?? '[]', true) ?: [],
                'new_key_id'     => $rotated['id'] ?? null,
                'new_key_prefix' => $rotated['key_prefix'] ?? '',
            ]);
            echo \PPC\Core\View::page('admin/api-key-show', [
                'rawKey'  => $newKey,
                'name'    => 'Rotated key',
                'scopes'  => [],
                'isAdmin' => Session::isAdmin(),
                'flash'   => ['success' => 'Key rotated. The old key is now revoked.'],
            ], $this->meta('Rotated API Key | Patriot Pest Control', 'Copy this key now.', '/admin/api-keys'));
            return;
        }
        Session::flash('api_keys', ['errors' => ['key' => ['Key not found, already revoked, or rotation failed.']]]);
        header('Location: /admin/api-keys');
        exit;
    }

    /** Update scopes for an API key. */
    public function updateScopes(string $id): void
    { self::superAdminOnly();
        Csrf::verifyOrDie();
        $db = Database::instance();
        $row = $db->fetch('SELECT id, name, key_prefix, scopes FROM api_keys WHERE id = ? AND revoked_at IS NULL', [$id]);
        if (!$row) {
            Session::flash('api_keys', ['errors' => ['key' => ['Key not found or revoked.']]]);
            header('Location: /admin/api-keys');
            exit;
        }

        $scopes = array_filter(array_map('trim', explode(',', (string) ($_POST['scopes'] ?? ''))), 'strlen');
        if (!$scopes) {
            Session::flash('api_keys', ['errors' => ['scopes' => ['At least one scope is required.']]]);
            header('Location: /admin/api-keys');
            exit;
        }

        $db->update('api_keys', ['scopes' => json_encode($scopes)], ['id' => $id]);
        self::auditLifecycle('api_key.scopes', (int)$id, [
            'name'          => $row['name'] ?? '',
            'prefix'        => $row['key_prefix'] ?? '',
            'scopes_before' => json_decode($row['scopes'] ?? '[]', true) ?: [],
            'scopes_after'  => $scopes,
        ]);
        Session::flash('api_keys', ['success' => 'Scopes updated.']);
        header('Location: /admin/api-keys');
        exit;
    }

    /** Audit trail for key lifecycle actions, filterable per key. */
    public function audit(): void
    { self::superAdminOnly();
        $db = Database::instance();
        $keyFilter = trim((string) ($_GET['key'] ?? ''));
        if ($keyFilter !== '' && !preg_match('/^[a-f0-9]{12}$/', $keyFilter)) {
            $keyFilter = '';
        }

        $sql = 'SELECT * FROM audit_log WHERE entity = ? AND action IN (?, ?, ?, ?)';
        $params = ['api_keys', 'api_key.create', 'api_key.revoke', 'api_key.rotate', 'api_key.scopes'];
        if ($keyFilter !== '') {
            $sql .= ' AND (entity_id = ? OR meta_json LIKE ?)';
            $params[] = $keyFilter;
            $params[] = '%"prefix":"' . $keyFilter . '"%';
        }
        $sql .= ' ORDER BY id DESC LIMIT 200';
        $rows = $db->fetchAll($sql, $params);

        foreach ($rows as &$row) {
            $meta = json_decode($row['meta_json'] ?? '{}', true) ?: [];
            $row['meta'] = $meta;
            $name = (string) ($meta['name'] ?? '');
            $prefix = (string) ($meta['prefix'] ?? '');
            $row['key_label'] = $name !== ''
                ? $name . ($prefix !== '' ? ' (ppc_live_' . $prefix . '...)' : '')
                : 'key #' . $row['entity_id'];
        }
        unset($row);

        $keys = $db->fetchAll('SELECT id, name, key_prefix FROM api_keys ORDER BY created_at DESC');

        echo \PPC\Core\View::page('admin/api-key-audit', [
            'rows'      => $rows,
            'keys'      => $keys,
            'keyFilter' => $keyFilter,
            'isAdmin'   => Session::isAdmin(),
        ], $this->meta('API Key Audit Trail | Patriot Pest Control', 'Lifecycle audit trail for API keys.', '/admin/api-keys/audit'));
    }

    /** Write a lifecycle audit log entry for key admin actions. */
    private static function auditLifecycle(string $action, ?int $keyId, array $meta): void
    {
        try {
            Database::instance()->insert('audit_log', [
                'user_id'   => 'staff:' . (Session::get('user_id') ?? '0'),
                'user_type' => 'staff',
                'action'    => $action,
                'entity'    => 'api_keys',
                'entity_id' => $keyId !== null ? (string)$keyId : 'new',
                'meta_json' => json_encode($meta),
                'ip'         => RateLimiter::clientIp(),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // Non-fatal; fall back to file log.
            Logger::warning('Failed to write audit_log for key action', [
                'action' => $action,
                'key_id' => $keyId,
            ]);
        }
    }

    private function meta(string $title, string $description, string $url): array
    {
        return ['title' => $title, 'description' => $description, 'slug' => '', 'image' => '', 'url' => $url, 'published_at' => ''];
    }
}
