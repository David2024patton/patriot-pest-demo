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
        self::ipRateLimit('health');
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
        self::ipRateLimit('customers');
        ApiAuth::requireAuth('customer:read');
        self::keyRateLimit('customers');

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
        $data = array_map(fn($c) => self::redactCustomer($c, $full), $rows);

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
        self::ipRateLimit('customers');
        ApiAuth::requireAuth('customer:read');
        self::keyRateLimit('customers');

        $db  = Database::instance();
        $row = $db->fetch('SELECT * FROM customers WHERE id = ?', [$id]);
        if (!$row) {
            self::err(404, 'Customer not found');
        }
        self::ok([
            'data' => self::redactCustomer($row, ApiAuth::hasScopes(['customer:read-full'])),
        ]);
        exit;
    }

    /** /api/v1/tickets -- paginated tickets, PII redacted without customer:read-full. */
    public function tickets(): void
    {
        self::ipRateLimit('tickets');
        ApiAuth::requireAuth('ticket:read');
        self::keyRateLimit('tickets');

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

        $full = ApiAuth::hasScopes(['customer:read-full']);
        if ($full) {
            $rows = $db->fetchAll(
                'SELECT * FROM tickets' . $whereSql . ' ORDER BY created_at DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
                $params
            );
        } else {
            $rows = $db->fetchAll(
                'SELECT id, customer_id, category, priority, subject, status, created_at, updated_at'
                . ' FROM tickets' . $whereSql . ' ORDER BY created_at DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
                $params
            );
            $rows = array_map(fn($t) => self::redactTicket($t), $rows);
        }

        self::ok([
            'data' => $rows, 'total' => $total,
            'page' => $page, 'limit' => $limit, 'page_count' => (int) ceil($total / $limit),
        ]);
        exit;
    }

    /** /api/v1/messages -- paginated messages, PII redacted without customer:read-full. */
    public function messages(): void
    {
        self::ipRateLimit('messages');
        ApiAuth::requireAuth('message:read');
        self::keyRateLimit('messages');

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

        $full = ApiAuth::hasScopes(['customer:read-full']);
        if ($full) {
            $rows = $db->fetchAll(
                'SELECT * FROM messages' . $whereSql . ' ORDER BY created_at DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
                $params
            );
        } else {
            $rows = $db->fetchAll(
                'SELECT id, from_user, from_type, to_user, to_type, is_read, created_at'
                . ' FROM messages' . $whereSql . ' ORDER BY created_at DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
                $params
            );
            $rows = array_map(fn($m) => self::redactMessage($m), $rows);
        }

        self::ok([
            'data' => $rows, 'total' => $total,
            'page' => $page, 'limit' => $limit, 'page_count' => (int) ceil($total / $limit),
        ]);
        exit;
    }

    /** /api/v1/services — public pest catalog (no auth required). */
    public function services(): void
    {
        self::ipRateLimit('services');
        $db = Database::instance();
        self::ok(['data' => $db->fetchAll('SELECT * FROM pest_photos ORDER BY name')]);
        exit;
    }

    /** /api/v1/twilio/logs -- Twilio logs, PII redacted without customer:read-full. */
    public function twilioLogs(): void
    {
        self::ipRateLimit('twilio');
        ApiAuth::requireAuth('twilio:read');
        self::keyRateLimit('twilio');

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

        $full = ApiAuth::hasScopes(['customer:read-full']);
        if ($full) {
            $rows = $db->fetchAll(
                'SELECT * FROM ' . $table . $whereSql . ' ORDER BY created_at DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
                $params
            );
        } else {
            $rows = $db->fetchAll(
                self::twilioColumnList($type) . ' FROM ' . $table . $whereSql
                . ' ORDER BY created_at DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
                $params
            );
            $rows = array_map(fn($r) => self::redactTwilioRow($r, $type), $rows);
        }

        self::ok([
            'data' => $rows, 'total' => $total,
            'page' => $page, 'limit' => $limit, 'page_count' => (int) ceil($total / $limit),
        ]);
        exit;
    }

    /** /api/v1/staff — staff roster (admin scope, emails redacted). */
    public function staff(): void
    {
        self::ipRateLimit('staff');
        ApiAuth::requireAuth('staff:read');
        self::keyRateLimit('staff');

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

    /** Redact customer PII fields: phone, email, address, zip. */
    private static function redactCustomer(array $c, bool $full): array
    {
        if ($full) { return $c; }
        $c['phone'] = null;
        $c['email'] = null;
        $c['address'] = null;
        $c['zip'] = null;
        return $c;
    }

    /** Redact ticket body and customer_name. */
    private static function redactTicket(array $t): array
    {
        $t['body'] = null;
        $t['customer_name'] = null;
        return $t;
    }

    /** Redact message body, subject, and name fields. */
    private static function redactMessage(array $m): array
    {
        $m['body'] = null;
        $m['subject'] = null;
        $m['from_name'] = null;
        $m['to_name'] = null;
        return $m;
    }

    /** Build a column whitelist for the given Twilio table type (non-full scope). */
    private static function twilioColumnList(string $type): string
    {
        return match ($type) {
            'sms' => 'SELECT id, direction, status, twilio_sid, twilio_status, error_message, created_at, updated_at',
            'call' => 'SELECT id, direction, duration, status, twilio_sid, twilio_status, error_message, created_at, updated_at',
            'voicemail' => 'SELECT id, call_sid, duration, status, created_at',
            default => 'SELECT *',
        };
    }

    /** Redact Twilio PII: phone_number, message body, transcription, media/recording/audio URLs. */
    private static function redactTwilioRow(array $r, string $type): array
    {
        $r['phone_number'] = null;
        $r['message'] = null;
        $r['transcription'] = null;
        $r['media_url'] = null;
        $r['recording_url'] = null;
        $r['audio_url'] = null;
        return $r;
    }

    /** Per-IP rate limit -- runs BEFORE auth to throttle unauthenticated brute-force. */
    private static function ipRateLimit(string $endpoint): void
    {
        try {
            $ip = RateLimiter::clientIp();
            RateLimiter::checkOrDie('api_ip:' . $ip . ':' . $endpoint, 120, 60);
        } catch (\Throwable) {
            self::err(429, 'Rate limit exceeded. Retry after 60 seconds.');
            exit;
        }
    }

    /** Per-key rate limit -- runs AFTER auth, scoped by key id. */
    private static function keyRateLimit(string $endpoint): void
    {
        try {
            $keyId = ApiAuth::keyId();
            if ($keyId !== null) {
                RateLimiter::checkOrDie('api_key:' . $keyId . ':' . $endpoint, 60, 60);
            }
        } catch (\Throwable) {
            self::err(429, 'Rate limit exceeded. Retry after 60 seconds.');
            exit;
        }
    }

    /** /api/v1/ai/chat -- AI chat endpoint for customer service automation */
    public function aiChat(): void
    {
        self::ipRateLimit('ai_chat');
        // No auth required for public AI chat, but rate limited by IP
        // Future: could require specific API key for AI features
        
        $input = json_decode(file_get_contents('php://input'), true);
        $message = trim((string) ($input['message'] ?? ''));
        $context = $input['context'] ?? [];
        
        if ($message === '') {
            self::err(400, 'Message is required');
        }
        
        // Simple AI response system (can be enhanced with actual AI integration)
        $response = self::generateAIResponse($message, $context);
        
        self::ok([
            'response' => $response,
            'timestamp' => date('c'),
            'message_id' => uniqid('chat_'),
        ]);
        exit;
    }
    
    /** Generate AI response based on message context */
    private static function generateAIResponse(string $message, array $context): string
    {
        $messageLower = strtolower($message);
        
        // Basic intent detection and response generation
        $responses = [
            'pricing' => [
                'keywords' => ['price', 'cost', 'how much', 'rate', 'charge', 'quote', 'estimate'],
                'response' => "Our pricing starts at Bronze, Silver, Gold, and Platinum tiers designed to fit different needs and budgets. For a free, no-obligation quote specific to your property, please call us at (509) 471-5767 or use our contact form. We offer same-day service and a 90-day warranty on all treatments."
            ],
            'services' => [
                'keywords' => ['service', 'treatment', 'what do you do', 'pest', 'insect', 'bug'],
                'response' => "We provide comprehensive pest control services including ant control, termite treatment, bed bug removal, rodent control, mosquito management, wasp removal, cockroach extermination, and more. All treatments are eco-friendly and safe for families and pets. Would you like to schedule a free inspection?"
            ],
            'hours' => [
                'keywords' => ['hour', 'open', 'close', 'time', 'when', 'available', 'schedule'],
                'response' => "We're open Monday-Friday 9AM-5PM and Saturday-Sunday 10AM-4PM. For urgent pest issues, we offer same-day service when possible. Call our 24/7 line at (509) 471-5767 for immediate assistance."
            ],
            'areas' => [
                'keywords' => ['area', 'location', 'where', 'serve', 'cover', 'region', 'washington', 'idaho', 'oregon', 'arizona'],
                'response' => "We serve Washington, Idaho, Oregon, and Arizona. In Washington, we cover Spokane, Spokane Valley, Cheney, Liberty Lake, Airway Heights, Medical Lake, Deer Park, and Mead. In Idaho: Coeur d'Alene, Post Falls, Hayden, and Rathdrum. In Oregon: Hermiston and Milton-Freewater. In Arizona: Phoenix."
            ],
            'appointment' => [
                'keywords' => ['appointment', 'schedule', 'book', 'reserve', 'booked'],
                'response' => "You can schedule a free inspection by calling us at (509) 471-5767 or filling out our contact form on the website. Our team will work with you to find a convenient time for your service appointment."
            ],
            'emergency' => [
                'keywords' => ['emergency', 'urgent', 'asap', 'immediate', 'now', 'help'],
                'response' => "For urgent pest control needs, please call our 24/7 line at (509) 471-5767. We prioritize emergency calls and offer same-day service when possible. Your safety and comfort are our top priority."
            ],
            'greeting' => [
                'keywords' => ['hello', 'hi', 'hey', 'good morning', 'good afternoon', 'good evening'],
                'response' => "Hello! Welcome to Patriot Pest Control. I'm here to help you with any pest control questions or concerns. How can I assist you today?"
            ],
            'thank' => [
                'keywords' => ['thank', 'thanks', 'appreciate', 'helpful'],
                'response' => "You're welcome! If you have any other questions about our pest control services or need assistance, please don't hesitate to ask. We're here to help!"
            ],
            'goodbye' => [
                'keywords' => ['bye', 'goodbye', 'see you', 'farewell'],
                'response' => "Thank you for choosing Patriot Pest Control! For any future pest control needs, we're here 24/7. Have a pest-free day!"
            ]
        ];
        
        // Check for keyword matches
        foreach ($responses as $intent) {
            foreach ($intent['keywords'] as $keyword) {
                if (str_contains($messageLower, $keyword)) {
                    return $intent['response'];
                }
            }
        }
        
        // Default response if no match
        return "I'm here to help with pest control questions! I can assist with information about our services, pricing, service areas, scheduling appointments, and more. What would you like to know about Patriot Pest Control?";
    }
}
