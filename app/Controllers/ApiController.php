<?php
declare(strict_types=1);
namespace PPC\Controllers;
use PPC\Core\ApiAuth;
use PPC\Core\Database;
use PPC\Core\RateLimiter;

class ApiController
{
    /** /api/v1/health — always available when API_ENABLED=true, no scope required. */
    public function health(): void
    {
        self::rateLimit('health');
        self::ok([
            'status'  => 'ok',
            'service' => 'patriot-pest-control-api',
            'version' => '1.0',
            'time'    => date('c'),
        ]);
        exit;
    }

    /** /api/v1/customers — paginated list, scoped field visibility. */
    public function customers(): void
    {
        ApiAuth::requireAuth('customer:read');
        self::rateLimit('customers');

        $db = Database::instance();
        $q      = trim((string) ($_GET['q'] ?? ''));
        $status = trim((string) ($_GET['status'] ?? ''));
        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $limit  = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $where  = ["source != 'seed'"];
        $params = [];
        if ($q !== '') {
            $where[] = '(name LIKE ? OR phone LIKE ? OR email LIKE ? OR account_number LIKE ? OR city LIKE ?)';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }
        if ($status !== '') {
            $where[]  = 'status = ?';
            $params[] = $status;
        }
        $whereSql = count($where) ? ' WHERE ' . implode(' AND ', $where) : '';

        $total = (int) $db->scalar('SELECT COUNT(*) FROM customers' . $whereSql, $params);
        $rows  = $db->fetchAll(
            'SELECT * FROM customers' . $whereSql . ' ORDER BY name LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params
        );

        $full = ApiAuth::hasScopes(['customer:read-full']);
        $data = array_map(fn($c) => self::redact($c, $full), $rows);

        self::ok([
            'data'       => $data,
            'total'      => $total,
            'page'       => $page,
            'limit'      => $limit,
            'page_count' => (int) ceil($total / $limit),
        ]);
        exit;
    }

    /** /api/v1/customers/{id} — single customer. */
    public function customerById(string $id): void
    {
        ApiAuth::requireAuth('customer:read');
        self::rateLimit('customers');

        $db  = Database::instance();
        $row = $db->fetch('SELECT * FROM customers WHERE id = ?', [$id]);
        if (!$row) {
            self::err(404, 'Customer not found');
        }
        self::ok([
            'data' => self::redact($row, ApiAuth::hasScopes(['customer:read-full'])),
        ]);
        exit;
    }

    /** /api/v1/tickets — paginated tickets. */
    public function tickets(): void
    {
        ApiAuth::requireAuth('ticket:read');
        self::rateLimit('tickets');

        $db    = Database::instance();
        $cid   = trim((string) ($_GET['customer_id'] ?? ''));
        $stat  = trim((string) ($_GET['status'] ?? ''));
        $page  = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $where  = ['1=1'];
        $params = [];
        if ($cid !== '') { $where[] = 'customer_id = ?'; $params[] = $cid; }
        if ($stat !== '') { $where[] = 'status = ?'; $params[] = $stat; }
        $whereSql = ' WHERE ' . implode(' AND ', $where);

        $total = (int) $db->scalar('SELECT COUNT(*) FROM tickets' . $whereSql, $params);
        $rows  = $db->fetchAll(
            'SELECT * FROM tickets' . $whereSql . ' ORDER BY created_at DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params
        );
        self::ok([
            'data' => $rows, 'total' => $total,
            'page' => $page, 'limit' => $limit, 'page_count' => (int) ceil($total / $limit),
        ]);
        exit;
    }

    /** /api/v1/messages — paginated messages. */
    public function messages(): void
    {
        ApiAuth::requireAuth('message:read');
        self::rateLimit('messages');

        $db    = Database::instance();
        $cid   = trim((string) ($_GET['customer_id'] ?? ''));
        $page  = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $where  = ['1=1'];
        $params = [];
        if ($cid !== '') {
            $where[] = '(to_user = ? OR from_user = ?)';
            array_push($params, $cid, $cid);
        }
        $whereSql = ' WHERE ' . implode(' AND ', $where);

        $total = (int) $db->scalar('SELECT COUNT(*) FROM messages' . $whereSql, $params);
        $rows  = $db->fetchAll(
            'SELECT * FROM messages' . $whereSql . ' ORDER BY created_at DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params
        );
        self::ok([
            'data' => $rows, 'total' => $total,
            'page' => $page, 'limit' => $limit, 'page_count' => (int) ceil($total / $limit),
        ]);
        exit;
    }

    /** /api/v1/services — public pest catalog (no auth required). */
    public function services(): void
    {
        $db = Database::instance();
        self::ok(['data' => $db->fetchAll('SELECT * FROM pest_photos ORDER BY name')]);
        exit;
    }

    /** /api/v1/twilio/logs — Twilio logs (admin scope). */
    public function twilioLogs(): void
    {
        ApiAuth::requireAuth('twilio:read');
        self::rateLimit('twilio');

        $db    = Database::instance();
        $type  = trim((string) ($_GET['type'] ?? 'sms'));
        $phone = trim((string) ($_GET['phone'] ?? ''));
        $page  = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $tables = ['sms' => 'sms_logs', 'call' => 'call_logs', 'voicemail' => 'voicemails'];
        $table = $tables[$type] ?? null;
        if ($table === null) {
            self::err(400, 'Invalid type. Use: sms, call, voicemail');
        }

        $where  = ['1=1'];
        $params = [];
        if ($phone !== '') { $where[] = 'phone_number LIKE ?'; $params[] = '%' . $phone . '%'; }
        $whereSql = ' WHERE ' . implode(' AND ', $where);

        $total = (int) $db->scalar('SELECT COUNT(*) FROM ' . $table . $whereSql, $params);
        $rows  = $db->fetchAll(
            'SELECT * FROM ' . $table . $whereSql . ' ORDER BY created_at DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params
        );
        self::ok([
            'data' => $rows, 'total' => $total,
            'page' => $page, 'limit' => $limit, 'page_count' => (int) ceil($total / $limit),
        ]);
        exit;
    }

    /** /api/v1/staff — staff roster (admin scope, emails redacted). */
    public function staff(): void
    {
        ApiAuth::requireAuth('staff:read');
        self::rateLimit('staff');

        $db = Database::instance();
        $rows = $db->fetchAll(
            'SELECT s.id, s.name, s.role, r.label AS role_label, s.active, s.last_login, s.created_at
             FROM staff s LEFT JOIN roles r ON r.role = s.role
             WHERE s.active = 1 ORDER BY s.name'
        );
        self::ok(['data' => $rows]);
        exit;
    }

    /* ============ helpers ============ */

    private static function ok(mixed $data): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    private static function err(int $status, string $message): never
    {
        http_response_code($status);
        self::ok(['error' => true, 'code' => $status, 'message' => $message]);
        exit;
    }

    private static function redact(array $c, bool $full): array
    {
        if ($full) { return $c; }
        $c['phone'] = null;
        $c['email'] = null;
        $c['address'] = null;
        $c['zip'] = null;
        return $c;
    }

    private static function rateLimit(string $endpoint): void
    {
        try {
            $keyId = ApiAuth::keyId();
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            if ($keyId !== null) {
                RateLimiter::checkOrDie('api_key:' . $keyId . ':' . $endpoint, 60, 60);
            }
            RateLimiter::checkOrDie('api_ip:' . $ip . ':' . $endpoint, 120, 60);
        } catch (\Throwable) {
            self::err(429, 'Rate limit exceeded. Retry after 60 seconds.');
            exit;
        }
    }
}
