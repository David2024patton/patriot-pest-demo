<?php
declare(strict_types=1);
namespace PPC\Controllers;
use PPC\Core\ApiAuth;
use PPC\Core\Database;
use PPC\Core\Session;
use PPC\Core\Csrf;
use PPC\Core\Logger;

class ApiKeyController
{
    /** List all API keys. */
    public function index(): void
    {
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

    /** Create a new API key. Returns a page showing the raw key ONCE. */
    public function create(): void
    {
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

        Logger::info('API key created', ['by' => Session::get('user_id'), 'name' => $name, 'scopes' => $scopes]);

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
    {
        Csrf::verifyOrDie();
        $ok = ApiAuth::revokeKey((int) $id);
        if ($ok) {
            Session::flash('api_keys', ['success' => 'API key revoked.']);
            Logger::info('API key revoked', ['key_id' => $id, 'by' => Session::get('user_id')]);
        } else {
            Session::flash('api_keys', ['errors' => ['key' => ['Key not found or already revoked.']]]);
        }
        header('Location: /admin/api-keys');
        exit;
    }

    /** Rotate an API key — creates a new one and revokes the old in one TX. */
    public function rotate(string $id): void
    {
        Csrf::verifyOrDie();
        $newKey = ApiAuth::rotateKey((int) $id, (int) (Session::get('user_id') ?? 0));
        if ($newKey) {
            Logger::info('API key rotated', ['old_key_id' => $id, 'by' => Session::get('user_id')]);
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
    {
        Csrf::verifyOrDie();
        $db = Database::instance();
        $row = $db->fetch('SELECT id FROM api_keys WHERE id = ? AND revoked_at IS NULL', [$id]);
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
        Logger::info('API key scopes updated', ['key_id' => $id, 'scopes' => $scopes, 'by' => Session::get('user_id')]);
        Session::flash('api_keys', ['success' => 'Scopes updated.']);
        header('Location: /admin/api-keys');
        exit;
    }

    private function meta(string $title, string $description, string $url): array
    {
        return ['title' => $title, 'description' => $description, 'slug' => '', 'image' => '', 'url' => $url, 'published_at' => ''];
    }
}
