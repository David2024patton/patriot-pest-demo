<?php
/**
 * AdminController - the WordPress-like CMS (admin-only).
 *
 * Lets admins manage the site's content without touching code:
 *   - Blog posts: list → click to edit → create new. All posts share ONE
 *     template; the editor offers a pest-photo picker (the media library) so
 *     any selected photo gets the site's tactical treatment automatically.
 *   - Media: the pest photo library (pest_photos) - the single source of pest
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
use PPC\Core\Retention;
use PPC\Core\Settings;

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

    /** ==================== RBAC MANAGEMENT ==================== */
    
    /** Roles management - list all roles with permissions */
    public function roles(): void
    {
        $db = Database::instance();
        $roles = $db->fetchAll('SELECT * FROM roles ORDER BY role');
        
        // Count staff per role
        $roleCounts = [];
        foreach ($roles as $r) {
            $count = $db->scalar('SELECT COUNT(*) FROM staff WHERE role = ?', [$r['role']]);
            $roleCounts[$r['role']] = (int) $count;
        }
        
        echo View::page('admin/roles', [
            'roles' => $roles,
            'roleCounts' => $roleCounts,
            'flash' => $this->flash(),
        ], $this->meta('Roles | Admin', 'Manage roles and permissions.', '/admin/roles'));
    }
    
    /** Edit role permissions */
    public function roleEdit(string $role): void
    {
        $db = Database::instance();
        $roleData = $db->fetch('SELECT * FROM roles WHERE role = ?', [$role]);
        if (!$roleData) {
            \PPC\Core\Router::notFound();
        }
        
        // Define available permissions
        $availablePermissions = [
            'all' => 'Full System Access',
            'view_customers' => 'View Customer Information',
            'search_customers' => 'Search Customers',
            'create_customers' => 'Create New Customers',
            'edit_customers' => 'Edit Customer Records',
            'delete_customers' => 'Delete Customer Records',
            'manage_billing' => 'Manage Billing and Payments',
            'manage_appointments' => 'Manage Appointments',
            'view_tickets' => 'View Support Tickets',
            'respond_tickets' => 'Respond to Tickets',
            'manage_tickets' => 'Manage All Tickets',
            'send_messages' => 'Send Messages',
            'view_messages' => 'View Message History',
            'manage_staff' => 'Manage Staff Accounts',
            'manage_roles' => 'Manage Roles and Permissions',
            'view_analytics' => 'View Analytics and Reports',
            'manage_content' => 'Manage Website Content',
            'manage_marketing' => 'Manage Marketing Campaigns',
            'api_access' => 'API Access',
        ];
        
        $currentPerms = json_decode($roleData['permissions'], true) ?: [];
        
        echo View::page('admin/role-edit', [
            'role' => $roleData,
            'availablePermissions' => $availablePermissions,
            'currentPerms' => $currentPerms,
            'flash' => $this->flash(),
        ], $this->meta('Edit Role | Admin', 'Edit role permissions.', "/admin/roles/{$role}"));
    }
    
    /** Update role permissions */
    public function roleUpdate(string $role): void
    {
        Csrf::verifyOrDie();
        
        $db = Database::instance();
        $roleData = $db->fetch('SELECT * FROM roles WHERE role = ?', [$role]);
        if (!$roleData) {
            \PPC\Core\Router::notFound();
        }
        
        // Prevent modifying super-user role permissions (security)
        if ($role === 'super-user') {
            Session::flash('admin', ['error' => 'Cannot modify Super User role permissions.']);
            header('Location: /admin/roles');
            exit;
        }
        
        $permissions = $_POST['permissions'] ?? [];
        if (!is_array($permissions)) {
            $permissions = [];
        }
        
        // Validate permissions
        $validPermissions = [
            'all', 'view_customers', 'search_customers', 'create_customers', 
            'edit_customers', 'delete_customers', 'manage_billing', 'manage_appointments',
            'view_tickets', 'respond_tickets', 'manage_tickets', 'send_messages',
            'view_messages', 'manage_staff', 'manage_roles', 'view_analytics',
            'manage_content', 'manage_marketing', 'api_access'
        ];
        
        $permissions = array_intersect($permissions, $validPermissions);
        
        $db->update('roles', [
            'permissions' => json_encode(array_values($permissions))
        ], ['role' => $role]);
        
        $this->audit('role.update', 'role', $role, [
            'role' => $role,
            'permissions' => $permissions
        ]);
        
        Session::flash('admin', ['success' => 'Role permissions updated.']);
        header('Location: /admin/roles');
        exit;
    }
    
    /** Departments management */
    public function departments(): void
    {
        $db = Database::instance();
        $departments = $db->fetchAll('SELECT * FROM departments ORDER BY name');
        
        // Build tree structure
        $tree = [];
        $deptMap = [];
        
        foreach ($departments as $dept) {
            $deptMap[$dept['id']] = $dept;
            $deptMap[$dept['id']]['children'] = [];
            $deptMap[$dept['id']]['staff_count'] = 0;
        }
        
        // Count staff per department
        foreach ($departments as $dept) {
            $count = $db->scalar('SELECT COUNT(*) FROM staff WHERE department_id = ?', [$dept['id']]);
            $deptMap[$dept['id']]['staff_count'] = (int) $count;
        }
        
        // Build tree
        foreach ($departments as $dept) {
            if ($dept['parent_id'] && isset($deptMap[$dept['parent_id']])) {
                $deptMap[$dept['parent_id']]['children'][] = &$deptMap[$dept['id']];
            } else {
                $tree[] = &$deptMap[$dept['id']];
            }
        }
        
        echo View::page('admin/departments', [
            'departments' => $tree,
            'flash' => $this->flash(),
        ], $this->meta('Departments | Admin', 'Manage organizational structure.', '/admin/departments'));
    }
    
    /** Create new department */
    public function departmentCreate(): void
    {
        Csrf::verifyOrDie();
        
        $name = trim($_POST['name'] ?? '');
        $parentId = !empty($_POST['parent_id']) ? (int) $_POST['parent_id'] : null;
        
        if ($name === '') {
            Session::flash('admin', ['error' => 'Department name is required.']);
            header('Location: /admin/departments');
            exit;
        }
        
        $db = Database::instance();
        $db->insert('departments', [
            'name' => $name,
            'parent_id' => $parentId
        ]);
        
        $this->audit('department.create', 'department', (string) $db->lastInsertId(), [
            'name' => $name,
            'parent_id' => $parentId
        ]);
        
        Session::flash('admin', ['success' => 'Department created.']);
        header('Location: /admin/departments');
        exit;
    }
    
    /** Edit department */
    public function departmentEdit(string $id): void
    {
        $db = Database::instance();
        $department = $db->fetch('SELECT * FROM departments WHERE id = ?', [$id]);
        if (!$department) {
            \PPC\Core\Router::notFound();
        }
        
        $departments = $db->fetchAll('SELECT * FROM departments ORDER BY name');
        
        echo View::page('admin/department-edit', [
            'department' => $department,
            'departments' => $departments,
            'flash' => $this->flash(),
        ], $this->meta('Edit Department | Admin', 'Edit department details.', "/admin/departments/{$id}"));
    }
    
    /** Update department */
    public function departmentUpdate(string $id): void
    {
        Csrf::verifyOrDie();
        
        $db = Database::instance();
        $department = $db->fetch('SELECT * FROM departments WHERE id = ?', [$id]);
        if (!$department) {
            \PPC\Core\Router::notFound();
        }
        
        $name = trim($_POST['name'] ?? '');
        $parentId = !empty($_POST['parent_id']) ? (int) $_POST['parent_id'] : null;
        
        // Prevent circular reference
        if ($parentId == $id) {
            Session::flash('admin', ['error' => 'Department cannot be its own parent.']);
            header('Location: /admin/departments/' . $id);
            exit;
        }
        
        if ($name === '') {
            Session::flash('admin', ['error' => 'Department name is required.']);
            header('Location: /admin/departments/' . $id);
            exit;
        }
        
        $db->update('departments', [
            'name' => $name,
            'parent_id' => $parentId
        ], ['id' => $id]);
        
        $this->audit('department.update', 'department', $id, [
            'name' => $name,
            'parent_id' => $parentId
        ]);
        
        Session::flash('admin', ['success' => 'Department updated.']);
        header('Location: /admin/departments');
        exit;
    }
    
    /** Delete department */
    public function departmentDelete(string $id): void
    {
        Csrf::verifyOrDie();
        
        $db = Database::instance();
        
        // Check if department has staff
        $staffCount = $db->scalar('SELECT COUNT(*) FROM staff WHERE department_id = ?', [$id]);
        if ($staffCount > 0) {
            Session::flash('admin', ['error' => 'Cannot delete department with staff members.']);
            header('Location: /admin/departments');
            exit;
        }
        
        // Check if department has child departments
        $childCount = $db->scalar('SELECT COUNT(*) FROM departments WHERE parent_id = ?', [$id]);
        if ($childCount > 0) {
            Session::flash('admin', ['error' => 'Cannot delete department with sub-departments.']);
            header('Location: /admin/departments');
            exit;
        }
        
        $db->execute('DELETE FROM departments WHERE id = ?', [$id]);
        
        $this->audit('department.delete', 'department', $id, []);
        
        Session::flash('admin', ['success' => 'Department deleted.']);
        header('Location: /admin/departments');
        exit;
    }

    /** Retention dashboard (ORDER 3): live summary + toggle states. */
    public function retention(): void
    {
        $summary = [];
        try {
            $summary = Retention::summary();
        } catch (\Throwable $e) {
            \PPC\Core\Logger::warning('Retention summary failed', ['error' => $e->getMessage()]);
        }
        echo View::page('admin/retention', [
            'summary'      => $summary,
            'eggEnabled'   => Settings::bool('egg_enabled', true),
            'trackEnabled' => Settings::bool('track_enabled', true),
            'flash'        => $this->flash(),
        ], $this->meta('Retention Analytics | Admin', 'First-party retention analytics.', '/admin/retention'));
    }

    /** Save the retention/beacon toggles (doctrine: on/off per feature). */
    public function retentionSettings(): void
    {
        Csrf::verifyOrDie();
        Settings::set('egg_enabled',   !empty($_POST['egg_enabled'])   ? '1' : '0');
        Settings::set('track_enabled', !empty($_POST['track_enabled']) ? '1' : '0');
        $this->audit('settings.update', 'settings', 'retention', [
            'egg_enabled'   => Settings::bool('egg_enabled'),
            'track_enabled' => Settings::bool('track_enabled'),
        ]);
        Session::flash('admin', ['success' => 'Settings saved.']);
        header('Location: /admin/retention');
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
        $region = Validator::clean($_POST['region'] ?? 'all');
        if (!in_array($region, ['all', 'wa', 'id', 'or', 'az'], true)) { $region = 'all'; }

        // Scheduled posts keep published_at null until publishScheduled() fires.
        $scheduledAt = null;
        $publishedAt = null;
        if ($status === 'published') {
            $publishedAt = Validator::clean($_POST['published_at'] ?? '') ?: $now;
        } elseif ($status === 'scheduled') {
            $scheduledAt = Validator::clean($_POST['scheduled_at'] ?? '');
            if ($scheduledAt === '') {
                Session::flash('admin', ['errors' => ['scheduled_at' => 'Pick a date/time to schedule this post.']]);
                header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/posts'));
                exit;
            }
        }

        return [
            'title'            => Validator::clean($_POST['title']),
            'slug'             => $this->uniqueSlug(Validator::clean($_POST['slug']), $_POST['id'] ?? null),
            'excerpt'          => Validator::clean($_POST['excerpt'] ?? ''),
            'body_html'        => $this->sanitizeRich($_POST['body_html'] ?? ''),
            'pest_photo_id'    => !empty($_POST['pest_photo_id']) ? (int) $_POST['pest_photo_id'] : null,
            'season'           => Validator::clean($_POST['season'] ?? ''),
            'pest_category'    => Validator::clean($_POST['pest_category'] ?? ''),
            'status'           => $status,
            'published_at'     => $publishedAt,
            'scheduled_at'     => $scheduledAt,
            'region'           => $region,
            'meta_title'       => Validator::clean($_POST['meta_title'] ?? '') ?: null,
            'meta_description' => Validator::clean($_POST['meta_description'] ?? '') ?: null,
            'meta_keywords'    => Validator::clean($_POST['meta_keywords'] ?? '') ?: null,
            'og_image'         => Validator::clean($_POST['og_image'] ?? '') ?: null,
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
