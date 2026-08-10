<?php
/**
 * AdminController — the WordPress-like CMS (admin-only).
 *
 * Lets admins manage the site's content without touching code:
 *   - Blog posts: list → click to edit → create new. All posts share ONE
 *     template; the editor offers a pest-photo picker (the media library) so
 *     any selected photo gets the site's tactical treatment automatically.
 *   - Media: the pest photo library (pest_photos) — the single source of pest
 *     imagery used across posts and pages.
 *   - Content: per-page editable blocks (expanded in the CMS phase).
 *
 * All routes are guarded ->auth('staff')->role('admin'). Every state change is
 * CSRF-protected, validated, and audit-logged.
 */

declare(strict_types=1);

namespace PPC\Controllers;

use PPC\Core\View;
use PPC\Core\Session;
use PPC\Core\Database;
use PPC\Core\Csrf;
use PPC\Core\Logger;
use PPC\Core\Validator;

class AdminController extends PageController
{
    /** CMS home: quick links + content stats. */
    public function index(): void
    {
        $db = Database::instance();
        $stats = [
            'posts'     => (int) $db->scalar('SELECT COUNT(*) FROM posts'),
            'published' => (int) $db->scalar("SELECT COUNT(*) FROM posts WHERE status = 'published'"),
            'photos'    => (int) $db->scalar('SELECT COUNT(*) FROM pest_photos'),
            'blocks'    => (int) $db->scalar('SELECT COUNT(*) FROM content_blocks'),
        ];
        echo View::page('admin/index', ['stats' => $stats, 'flash' => $this->flash()], $this->meta('Admin | Patriot Pest Control', 'Site administration.', '/admin'));
    }

    /** List every blog post (draft + published) with edit links. */
    public function posts(): void
    {
        $db    = Database::instance();
        $posts = $db->fetchAll(
            'SELECT p.id, p.slug, p.title, p.status, p.published_at, p.updated_at, p.views, pp.name AS pest_name
             FROM posts p LEFT JOIN pest_photos pp ON pp.id = p.pest_photo_id
             ORDER BY p.updated_at DESC'
        );
        echo View::page('admin/posts', ['posts' => $posts, 'flash' => $this->flash()], $this->meta('Blog Posts | Admin', 'Manage blog posts.', '/admin/posts'));
    }

    /** New-post form (with the pest photo picker). */
    public function postNew(): void
    {
        echo View::page('admin/post-edit', [
            'post'   => null,
            'photos' => $this->photos(),
            'flash'  => $this->flash(),
        ], $this->meta('New Post | Admin', 'Create a blog post.', '/admin/posts/new'));
    }

    /** Store a new post. */
    public function postStore(): void
    {
        Csrf::verifyOrDie();
        $data = $this->validatePost();
        if ($data === null) {
            return; // validation failed; redirect already issued
        }

        $db = Database::instance();
        $now = date('Y-m-d H:i:s');
        $id = $db->insert('posts', array_merge($data, [
            'created_at'    => $now,
            'updated_at'    => $now,
            'date_modified' => $now,
            'author'        => Session::get('display_name', 'Admin'),
        ]));

        $this->audit('post.create', 'post', $id, ['title' => $data['title']]);
        Session::flash('admin', ['success' => 'Post created.']);
        header('Location: /admin/posts');
        exit;
    }

    /** Edit form for an existing post. */
    public function postEdit(string $id): void
    {
        $db   = Database::instance();
        $post = $db->fetch('SELECT * FROM posts WHERE id = ?', [$id]);
        if ($post === null) {
            \PPC\Core\Router::notFound();
        }
        echo View::page('admin/post-edit', [
            'post'   => $post,
            'photos' => $this->photos(),
            'flash'  => $this->flash(),
        ], $this->meta('Edit Post | Admin', 'Edit a blog post.', "/admin/posts/$id"));
    }

    /** Update an existing post. */
    public function postUpdate(string $id): void
    {
        Csrf::verifyOrDie();
        $db   = Database::instance();
        $post = $db->fetch('SELECT * FROM posts WHERE id = ?', [$id]);
        if ($post === null) {
            \PPC\Core\Router::notFound();
        }

        $data = $this->validatePost();
        if ($data === null) {
            return;
        }

        $db->update('posts', array_merge($data, [
            'updated_at'    => date('Y-m-d H:i:s'),
            'date_modified' => date('Y-m-d H:i:s'),
        ]), ['id' => $id]);

        $this->audit('post.update', 'post', $id, ['title' => $data['title']]);
        Session::flash('admin', ['success' => 'Post saved.']);
        header('Location: /admin/posts');
        exit;
    }

    /** The pest photo library page. */
    public function media(): void
    {
        echo View::page('admin/media', ['photos' => $this->photos(), 'flash' => $this->flash()], $this->meta('Media Library | Admin', 'Pest photo library.', '/admin/media'));
    }

    /** Editable content blocks (per-page sections). */
    public function content(): void
    {
        $db     = Database::instance();
        $blocks = $db->fetchAll('SELECT * FROM content_blocks ORDER BY page, sort_order');
        echo View::page('admin/content', ['blocks' => $blocks, 'flash' => $this->flash()], $this->meta('Content | Admin', 'Edit page sections.', '/admin/content'));
    }

    /** Settings page — tracking IDs with DB overrides. */
    public function settings(): void
    {
        $db       = Database::instance();
        $settings = [];
        $rows     = $db->fetchAll(
            "SELECT key, value FROM site_settings WHERE key IN ('gtag_id','gads_id','fb_pixel_id','clarity_id')"
        );
        foreach ($rows as $r) {
            $settings[$r['key']] = $r['value'];
        }
        echo View::page('admin/settings', [
            'settings' => $settings,
            'flash'    => $this->flash(),
        ], $this->meta('Settings | Admin', 'Tracking IDs and configuration.', '/admin/settings'));
    }

    /** Save settings (POST). */
    public function settingsSave(): void
    {
        Csrf::verifyOrDie();
        $db = Database::instance();

        $keys = ['gtag_id', 'gads_id', 'fb_pixel_id', 'clarity_id'];
        $errors = [];
        $patterns = [
            'gtag_id'     => '/^G-[A-Z0-9]{10}$/',
            'gads_id'     => '/^AW-[0-9]{10}$/',
            'fb_pixel_id' => '/^[0-9]{13,16}$/',
            'clarity_id'  => '/^[A-Za-z0-9]{8,12}$/',
        ];

        foreach ($keys as $k) {
            $val = trim($_POST[$k] ?? '');
            if ($val === '') {
                // Delete existing override
                $db->execute('DELETE FROM site_settings WHERE key = ?', [$k]);
            } elseif (!preg_match($patterns[$k], $val)) {
                $errors[$k] = match ($k) {
                    'gtag_id'     => 'GTAG must be G- + 10 chars (e.g. G-XXXXXXXXXX).',
                    'gads_id'     => 'Google Ads ID must be AW- + 10 digits.',
                    'fb_pixel_id' => 'FB Pixel ID must be 13-16 digits.',
                    'clarity_id'  => 'Clarity ID must be 8-12 alphanumeric chars.',
                    default       => 'Invalid format.',
                };
            } else {
                $db->execute(
                    'INSERT INTO site_settings (key, value, updated_at) VALUES (?, ?, datetime(\'now\'))
                     ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = excluded.updated_at',
                    [$k, $val]
                );
            }
        }

        if ($errors) {
            Session::flash('admin', ['errors' => $errors]);
        } else {
            $this->audit('settings.update', 'settings', null, [
                'keys' => array_map(fn($k) => $k . '=' . (strlen($_POST[$k] ?? '') > 0 ? '***' : '(cleared)'), $keys),
            ]);
            Session::flash('admin', ['success' => 'Tracking settings saved. Changes are live immediately.']);
        }

        header('Location: /admin/settings');
        exit;
    }

    /* ============================ helpers ============================ */

    /** Pull one-shot admin flash (success/errors) for display. */
    private function flash(): mixed
    {
        return Session::pullFlash('admin');
    }

    /** All pest photos for the picker. */
    private function photos(): array
    {
        return Database::instance()->fetchAll('SELECT * FROM pest_photos ORDER BY name');
    }

    /**
     * Validate + sanitize the post form. Returns the clean row array, or null
     * (after flashing errors + redirecting back) when invalid.
     */
    private function validatePost(): ?array
    {
        $errors = Validator::make($_POST, [
            'title'   => ['required', 'max:200'],
            'slug'    => ['required', 'max:200'],
            'excerpt' => ['max:500'],
            'status'  => ['in:draft,published,scheduled'],
        ]);
        if ($errors) {
            Session::flash('admin', ['errors' => $errors]);
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/posts'));
            exit;
        }

        $status = $_POST['status'] ?? 'draft';
        $now    = date('Y-m-d H:i:s');

        return [
            'title'         => Validator::clean($_POST['title']),
            'slug'          => $this->uniqueSlug(Validator::clean($_POST['slug']), $_POST['id'] ?? null),
            'excerpt'       => Validator::clean($_POST['excerpt'] ?? ''),
            'body_html'     => $this->sanitizeRich($_POST['body_html'] ?? ''),
            'pest_photo_id' => !empty($_POST['pest_photo_id']) ? (int) $_POST['pest_photo_id'] : null,
            'season'        => Validator::clean($_POST['season'] ?? ''),
            'pest_category' => Validator::clean($_POST['pest_category'] ?? ''),
            'status'        => $status,
            'published_at'  => $status === 'published' ? ($_POST['published_at'] ?: $now) : null,
        ];
    }

    /** Ensure the slug is unique (append -2, -3, ... on collision). */
    private function uniqueSlug(string $slug, mixed $excludeId): string
    {
        $db    = Database::instance();
        $base  = $this->slugify($slug) ?: 'post';
        $try   = $base;
        $n     = 2;
        while (true) {
            $exists = $db->fetch('SELECT id FROM posts WHERE slug = ? AND id != ?', [$try, $excludeId ?? -1]);
            if ($exists === null) {
                return $try;
            }
            $try = $base . '-' . $n++;
        }
    }

    /**
     * Sanitize rich-text body. Allows a safe formatting subset; strips scripts,
     * event handlers, and dangerous schemes. (A full allow-list sanitizer can be
     * swapped in later without changing callers.)
     */
    private function sanitizeRich(string $html): string
    {
        // Remove script/style/iframe entirely.
        $html = preg_replace('#<(script|style|iframe|object|embed)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;
        // Strip on* event handlers and javascript: URLs.
        $html = preg_replace('#\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html) ?? $html;
        $html = preg_replace('#href\s*=\s*(["\'])\s*javascript:[^"\']*\1#i', 'href=$1#$1', $html) ?? $html;
        // Allow only a safe tag set.
        return trim(strip_tags($html, '<p><br><strong><em><b><i><u><ul><ol><li><h2><h3><h4><blockquote><a><img><figure><figcaption><table><thead><tbody><tr><th><td>'));
    }

    /** Audit-log helper (mirrors AuthController's). */
    private function audit(string $action, string $entity, mixed $entityId, array $meta = []): void
    {
        try {
            Database::instance()->insert('audit_log', [
                'user_id'    => (string) (Session::get('user_id') ?? ''),
                'user_type'  => Session::userType() ?? 'system',
                'action'     => $action,
                'entity'     => $entity,
                'entity_id'  => $entityId !== null ? (string) $entityId : null,
                'meta_json'  => $meta ? json_encode($meta) : null,
                'ip'         => $_SERVER['REMOTE_ADDR'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            Logger::warning('Audit write failed', ['action' => $action]);
        }
    }
}
