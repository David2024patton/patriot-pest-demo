<?php
/**
 * Database - SQLite (PDO) access layer.
 *
 * Replaces the old dual-layer setup (a PPDatabase singleton plus a dead MySQL
 * helper that silently returned null). This is the single source of DB access:
 *
 *   - SQLite in WAL mode (fast reads, safe concurrent access),
 *   - foreign keys enforced,
 *   - busy-timeout so requests don't fail under brief locks,
 *   - EVERY query goes through prepared statements (no string-built SQL),
 *   - schema auto-applied from database/schema.sql on first run,
 *   - small typed helpers so controllers never touch PDO directly.
 *
 * Usage:
 *   $db   = Database::instance();
 *   $row  = $db->fetch('SELECT * FROM staff WHERE id = ?', [$id]);
 *   $rows = $db->fetchAll('SELECT * FROM posts WHERE status = ?', ['published']);
 *   $id   = $db->insert('posts', ['title' => 'x', 'status' => 'draft']);
 *   $db->execute('UPDATE posts SET views = views + 1 WHERE id = ?', [$id]);
 */

declare(strict_types=1);

namespace PPC\Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?Database $instance = null;

    private PDO $pdo;

    private function __construct(string $path)
    {
        // Ensure the directory exists so SQLite can create the file.
        $dir = dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        try {
            $this->pdo = new PDO('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // throw, never silent
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,                  // real prepared statements
            ]);
        } catch (PDOException $e) {
            Logger::critical('Database connection failed', ['path' => $path]);
            throw new RuntimeException('Database connection failed.');
        }

        // Pragmas: WAL for concurrency, FK enforcement, 5s busy timeout.
        $this->pdo->exec('PRAGMA journal_mode = WAL');
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->pdo->exec('PRAGMA busy_timeout = 5000');
        $this->pdo->exec('PRAGMA synchronous = NORMAL');
    }

    /** Get the shared instance (created on first call). */
    public static function instance(): self
    {
        if (self::$instance === null) {
            // DB_PATH is relative to the project root.
            $rel  = Config::get('DB_PATH', 'database/patriot.db') ?? 'database/patriot.db';
            $path = str_starts_with($rel, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $rel)
                ? $rel
                : BASE_PATH . DIRECTORY_SEPARATOR . $rel;

            self::$instance = new self($path);
            self::$instance->migrate();
        }
        return self::$instance;
    }

    /**
     * Open a secondary SQLite database without running the app migration.
     * Used by the retention analytics store (storage/retention.sqlite) so its
     * sessions table never collides with the app auth sessions table. Callers
     * own schema setup for secondary databases (see Retention::db()).
     */
    public static function open(string $relPath): self
    {
        $path = str_starts_with($relPath, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $relPath)
            ? $relPath
            : BASE_PATH . DIRECTORY_SEPARATOR . $relPath;
        return new self($path);
    }

    /**
     * Apply database/schema.sql if the schema_version marker is missing/stale.
     * The schema file is idempotent (CREATE TABLE IF NOT EXISTS), so re-running
     * it is safe. Add ALTER-based upgrades in upgrade() as the schema evolves.
     */
    private function migrate(): void
    {
        $schemaFile = BASE_PATH . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql';
        if (!is_readable($schemaFile)) {
            return;
        }

        // Track applied schema version so we only run the full script once.
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS meta (key TEXT PRIMARY KEY, value TEXT)');
        $stmt = $this->pdo->prepare('SELECT value FROM meta WHERE key = ?');
        $stmt->execute(['schema_version']);
        $current = (int) ($stmt->fetchColumn() ?: 0);

        // Bump when schema.sql changes structurally. Re-running is safe: the
        // schema file is all CREATE TABLE IF NOT EXISTS, so an older volume
        // (e.g. schema_version=1 from before otp_codes existed) gets the
        // missing tables added on boot without touching existing rows.
        $target = 4; // v4 = facebook_leads (FB lead pipeline); re-run is idempotent
        if ($current < $target) {
            $sql = file_get_contents($schemaFile);
            if ($sql !== false && trim($sql) !== '') {
                $this->pdo->exec($sql);
            }
            $up = $this->pdo->prepare('INSERT OR REPLACE INTO meta (key, value) VALUES (?, ?)');
            $up->execute(['schema_version', (string) $target]);
            Logger::info('Database schema applied', ['version' => $target]);
        }

        $this->seedSuperUser();
        $this->upgrade(); // existence-guarded v2->v3 migrations
    }

    /**
     * Idempotent super-user seed: promote or create the account identified by
     * SU_SEED_EMAIL (if configured). Re-running is a no-op.
     */
    private function seedSuperUser(): void
    {
        $email = Config::get('SU_SEED_EMAIL');
        if (!$email || trim($email) === '') {
            return; // No-op when SU_SEED_EMAIL is empty/absent.
        }

        $existing = $this->fetch('SELECT id, role FROM staff WHERE email = ?', [$email]);
        if ($existing === null) {
            // INSERT new super-user row.
            $name = Config::get('SU_SEED_NAME') ?: 'Super User';
            $id = $this->insert('staff', [
                'email'      => $email,
                'name'       => $name,
                'role'       => 'super-user',
                'active'     => 1,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
            $this->auditGrant($id, $email);
            Logger::info('Super-user created via seed', ['email' => $email, 'staff_id' => $id]);
        } elseif (($existing['role'] ?? '') !== 'super-user') {
            // UPDATE existing row to super-user.
            $this->execute("UPDATE staff SET role = 'super-user' WHERE id = ?", [$existing['id']]);
            $this->auditGrant($existing['id'], $email);
            Logger::info('Super-user promoted via seed', ['email' => $email, 'staff_id' => $existing['id']]);
        }
        // else: already super-user, no-op (idempotent).
    }

    /** Write a superuser.grant audit row. */
    private function auditGrant(int $staffId, string $email): void
    {
        try {
            $this->insert('audit_log', [
                'user_id'    => null,
                'user_type'  => 'system',
                'action'     => 'superuser.grant',
                'entity'     => 'staff',
                'entity_id'  => (string) $staffId,
                'meta_json'  => json_encode(['email' => $email, 'role' => 'super-user']),
                'ip'         => RateLimiter::clientIp() ?? null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            Logger::warning('Super-user grant audit failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Existence-guarded v2->v3 upgrades. Runs after the schema script so we
     * can add columns/indexes that CREATE TABLE IF NOT EXISTS cannot handle
     * on existing databases.
     */
    private function upgrade(): void
    {
        // v2->v3: customers.source column (added in schema v3, missing on v2 DBs).
        // Guard: only add if the column does not exist yet.
        try {
            $this->pdo->exec("ALTER TABLE customers ADD COLUMN source TEXT NOT NULL DEFAULT 'fieldroutes'");
        } catch (\Throwable) {
            // Column already exists, no-op (idempotent across restarts).
        }
        // v2->v3: idx_cust_source may fail if schema.sql already created it.
        try {
            $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_cust_source ON customers(source)');
        } catch (\Throwable) {
            Logger::warning('idx_cust_source creation skipped (may already exist)');
        }

        // v4->v5: posts SEO + region targeting + scheduling columns.
        foreach ([
            'ALTER TABLE posts ADD COLUMN meta_title TEXT',
            'ALTER TABLE posts ADD COLUMN meta_description TEXT',
            'ALTER TABLE posts ADD COLUMN meta_keywords TEXT',
            "ALTER TABLE posts ADD COLUMN region TEXT NOT NULL DEFAULT 'all'",
            'ALTER TABLE posts ADD COLUMN og_image TEXT',
            'ALTER TABLE posts ADD COLUMN scheduled_at TEXT',
        ] as $colSql) {
            try { $this->pdo->exec($colSql); } catch (\Throwable) { /* idempotent: column exists */ }
        }

        // v6b: api_keys.key_cipher — encrypted raw key so super-admins can copy it later.
        try { $this->pdo->exec('ALTER TABLE api_keys ADD COLUMN key_cipher TEXT'); } catch (\Throwable) {}

        // v7: marketing_ads (targeted email ads) + ad_impressions (tracking).
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS marketing_ads (
                    id         INTEGER PRIMARY KEY AUTOINCREMENT,
                    bucket     TEXT NOT NULL,      -- new_plan | upgrade | reactivate | referral | review
                    title      TEXT NOT NULL,
                    body       TEXT NOT NULL,
                    cta_label  TEXT NOT NULL,
                    cta_url    TEXT NOT NULL,
                    region     TEXT NOT NULL DEFAULT 'all',  -- all | wa | id | or | az
                    season     TEXT NOT NULL DEFAULT 'all',  -- all | spring | summer | fall | winter
                    weight     INTEGER NOT NULL DEFAULT 1,
                    active     INTEGER NOT NULL DEFAULT 1,
                    created_at TEXT NOT NULL DEFAULT (datetime('now'))
                )");
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS ad_impressions (
                    id           INTEGER PRIMARY KEY AUTOINCREMENT,
                    ad_id        INTEGER NOT NULL,
                    customer_id  INTEGER,
                    purpose      TEXT,
                    created_at   TEXT NOT NULL DEFAULT (datetime('now'))
                )");
        } catch (\Throwable) {}
        self::seedAds($this->pdo);

        // v6: rag_docs (RAG knowledge base) + pest_calendar (NPMA-style seasonal data).
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS rag_docs (
                    id          INTEGER PRIMARY KEY AUTOINCREMENT,
                    doc_name    TEXT NOT NULL,
                    chunk_index INTEGER NOT NULL DEFAULT 0,
                    content     TEXT NOT NULL,
                    created_at  TEXT NOT NULL DEFAULT (datetime('now'))
                )");
            $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_rag_doc ON rag_docs(doc_name)');
        } catch (\Throwable) {}
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS pest_calendar (
                    id          INTEGER PRIMARY KEY AUTOINCREMENT,
                    pest        TEXT NOT NULL,
                    region      TEXT NOT NULL DEFAULT 'all',
                    month_start INTEGER NOT NULL,
                    month_end   INTEGER NOT NULL,
                    severity    TEXT DEFAULT 'high',
                    source      TEXT DEFAULT 'NPMA guidance + regional climate',
                    note        TEXT
                )");
            $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_pestcal ON pest_calendar(pest, region)');
        } catch (\Throwable) {}

        // v3->v4: facebook_leads table for Facebook Lead Ads webhook pipeline.
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS facebook_leads (
                    id                INTEGER PRIMARY KEY AUTOINCREMENT,
                    leadgen_id        TEXT NOT NULL UNIQUE,
                    page_id           TEXT,
                    form_id           TEXT,
                    ad_id             TEXT,
                    adgroup_id        TEXT,
                    campaign_id       TEXT,
                    full_name         TEXT,
                    email             TEXT COLLATE NOCASE,
                    phone             TEXT,
                    city              TEXT,
                    state             TEXT,
                    zip               TEXT,
                    raw_payload       TEXT,
                    fingerprint       TEXT,
                    sms_sent          INTEGER NOT NULL DEFAULT 0,
                    sms_sent_at       TEXT,
                    sms_error         TEXT,
                    email_fallback_sent INTEGER NOT NULL DEFAULT 0,
                    email_fallback_sent_at TEXT,
                    processed         INTEGER NOT NULL DEFAULT 0,
                    created_at        TEXT NOT NULL DEFAULT (datetime('now')),
                    processed_at      TEXT
                )
            ");
            $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_fb_lead_fingerprint ON facebook_leads(fingerprint, created_at)');
            $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_fb_lead_created ON facebook_leads(created_at)');
        } catch (\Throwable) {
            Logger::warning('facebook_leads migration skipped (may already exist)');
        }
    }

    /**
     * Promote due scheduled posts (status='scheduled' and scheduled_at <= now).
     * Called once per request from the front controller; cheap single UPDATE.
     */    public function publishScheduled(): void
    {
        try {
            $this->pdo->exec(
                "UPDATE posts SET status='published', published_at = COALESCE(scheduled_at, datetime('now'))
                 WHERE status='scheduled' AND scheduled_at IS NOT NULL AND scheduled_at <= datetime('now')"
            );
        } catch (\Throwable) {
            // non-fatal; posts table may lack scheduled_at on very old volumes
        }
    }

    /** Seed the targeted-email ad catalog (idempotent). */
    private static function seedAds(\PDO $pdo): void
    {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM marketing_ads')->fetchColumn();
        if ($count > 0) { return; }
        $ads = [
            ['new_plan', 'Get covered in 24 hours', 'Same-day service is available across WA, ID, OR &amp; AZ — and every plan includes a 90-day re-treatment warranty.', 'View Plans', '/prices', 'all', 'all', 3],
            ['new_plan', 'Summer is coming. Are you ready?', 'Mosquitoes and ants ramp up fast in warm weather. Lock in your coverage before they move in.', 'See Pricing', '/prices', 'all', 'summer', 3],
            ['new_plan', 'Rodents don’t wait for winter', 'Cooler nights send mice and rats inside. A quarterly plan stops them before they settle in.', 'Get Protected', '/prices', 'all', 'fall', 2],
            ['upgrade', 'Add Flea &amp; Tick protection', 'Extend your current plan with flea &amp; tick treatment — perfect for pets and yards.', 'Add to My Plan', '/prices', 'all', 'all', 4],
            ['upgrade', 'Fortify the perimeter', 'Add rodent stations and baiting to your existing plan for year-round defense.', 'Upgrade My Plan', '/prices', 'all', 'all', 3],
            ['upgrade', 'Go Gold for priority service', 'Gold adds priority scheduling and seasonal deep checks — the plan your technician recommends.', 'See Gold', '/prices', 'all', 'all', 2],
            ['upgrade', 'Termite season check', 'Carpenter ants and termites swarm in spring. Add an inspection to your plan.', 'Book an Inspection', '/contact', 'all', 'spring', 2],
            ['upgrade', 'Take back your yard with misting', 'Mosquito misting knocks down your whole yard — the #1 add-on for summer.', 'Ask About Misting', '/contact', 'all', 'summer', 2],
            ['reactivate', 'We miss you — rebook your service', 'Pests don’t take a break. Get back on the schedule and we’ll re-treat your property.', 'Rebook Now', '/contact', 'all', 'all', 3],
            ['reactivate', 'Your protection lapsed', 'Re-activate your plan and your 90-day warranty restarts immediately.', 'Reactivate', '/prices', 'all', 'all', 2],
            ['referral', 'Refer a neighbor, get rewarded', 'Every referral that books earns you credit. Helping a neighbor helps your wallet.', 'Start Referring', '/referral', 'all', 'all', 2],
            ['referral', 'Know someone with bugs?', 'Pass along our number — you both get rewarded when they book.', 'Share the Deal', '/referral', 'all', 'all', 2],
            ['review', 'Love the service? Say it loudly', 'A quick review helps a veteran-owned local business more than you know.', 'Leave a Review', '/socials', 'all', 'all', 2],
            ['review', 'Your 5-star review matters', 'Happy with your technician? Give us a rating — it takes 30 seconds.', 'Rate Us', '/socials', 'all', 'all', 2],
        ];
        $stmt = $pdo->prepare("INSERT INTO marketing_ads (bucket, title, body, cta_label, cta_url, region, season, weight, active) VALUES (?,?,?,?,?,?,?,?,1)");
        foreach ($ads as $a) { $stmt->execute($a); }
    }

    /** Expose raw PDO only where genuinely needed (e.g. transactions). */
    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /** Run a statement; returns affected row count. */
    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /** Fetch a single row (associative array) or null. */
    public function fetch(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    /** Fetch all rows as a list of associative arrays. */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Fetch a single scalar value (e.g. COUNT(*)). */
    public function scalar(string $sql, array $params = []): mixed
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    /**
     * Insert an associative array as a row; returns the new row id.
     * Column names come from code (never user input) - values are bound.
     */
    public function insert(string $table, array $data): int
    {
        $cols   = array_keys($data);
        $places = implode(', ', array_fill(0, count($cols), '?'));
        $sql    = 'INSERT INTO ' . $table . ' (' . implode(', ', $cols) . ') VALUES (' . $places . ')';
        $stmt   = $this->pdo->prepare($sql);
        $stmt->execute(array_values($data));
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update rows matching $where (associative column => value); returns count.
     */
    public function update(string $table, array $data, array $where): int
    {
        $set    = implode(', ', array_map(fn($c) => "$c = ?", array_keys($data)));
        $cond   = implode(' AND ', array_map(fn($c) => "$c = ?", array_keys($where)));
        $sql    = "UPDATE $table SET $set WHERE $cond";
        $stmt   = $this->pdo->prepare($sql);
        $stmt->execute([...array_values($data), ...array_values($where)]);
        return $stmt->rowCount();
    }

    /** Begin/commit/rollback helpers for multi-step operations. */
    public function begin(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollback(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }
}
