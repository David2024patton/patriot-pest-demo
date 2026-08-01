<?php
/**
 * Retention - first-party retention analytics store (ORDER 3).
 *
 * Owns the separate SQLite database at storage/retention.sqlite (see
 * database/retention.sql, mirrored from Nash RETENTION_SCHEMA.sql) so the
 * analytics sessions table never collides with the app auth sessions table
 * in database/patriot.db. GA4 stays live; this is additive and toggleable
 * via the track_enabled site setting.
 *
 * Public API used by RetentionController:
 *   Retention::recordView($p)        POST /api/track/view
 *   Retention::recordEvent($p)       POST /api/track/event
 *   Retention::recordSessionEnd($p)  POST /api/track/session_end
 *   Retention::summary()             GET  /api/retention/summary
 *
 * The summary shape is locked to RETENTION_EVENT_CONTRACT.md section
 * "Admin Dashboard Data" (one fact one name). Anti-drift: timestamps are
 * ISO-8601 UTC with Z; click paths come from the click_path VIEW, never a
 * duplicated table.
 */

declare(strict_types=1);

namespace PPC\Core;

final class Retention
{
    private static ?Database $db = null;
    private static bool $initialized = false;

    public const DB_PATH = 'storage/retention.sqlite';

    /** Get the analytics database connection (created + schema'd once). */
    public static function db(): Database
    {
        if (self::$db === null) {
            self::$db = Database::open(self::DB_PATH);
        }
        if (!self::$initialized) {
            $schema = BASE_PATH . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'retention.sql';
            if (is_readable($schema)) {
                self::$db->pdo()->exec((string) file_get_contents($schema));
            }
            self::$initialized = true;
        }
        return self::$db;
    }

    /* ======================= ingestion ======================= */

    /** POST /api/track/view: upsert visitor + session, insert page view. */
    public static function recordView(array $p): void
    {
        $db      = self::db();
        $visitor = self::uuidOrNew($p['visitor_id'] ?? null);
        $session = self::uuidOrNew($p['session_id'] ?? null);
        $path    = self::path($p['page_path'] ?? '/');
        $title   = self::str($p['page_title'] ?? '');
        $ref     = self::str($p['referrer'] ?? '');
        $ts      = self::ts($p['ts'] ?? null);
        $ua      = self::str($_SERVER['HTTP_USER_AGENT'] ?? '');
        $dev     = self::deviceClass($ua);

        // Upsert visitor: first_seen on insert, last_seen on every view.
        $db->execute(
            "INSERT INTO visitors (visitor_id, first_seen_at, last_seen_at, user_agent, referrer, device_class)
             VALUES (?, ?, ?, ?, ?, ?)
             ON CONFLICT(visitor_id) DO UPDATE SET last_seen_at = excluded.last_seen_at, user_agent = excluded.user_agent",
            [$visitor, $ts, $ts, $ua, $ref, $dev]
        );

        // Upsert session: entry_page on insert; exit_page + duration every view.
        $db->execute(
            "INSERT INTO sessions (session_id, visitor_id, started_at, entry_page, referrer, user_agent, device_class)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON CONFLICT(session_id) DO UPDATE SET visitor_id = excluded.visitor_id",
            [$session, $visitor, $ts, $path, $ref, $ua, $dev]
        );

        // view_order = count(existing rows for session) + 1
        $order = (int) $db->scalar('SELECT COUNT(*) FROM page_views WHERE session_id = ?', [$session]) + 1;
        $db->insert('page_views', [
            'session_id' => $session,
            'visitor_id' => $visitor,
            'page_path'  => $path,
            'page_title' => $title,
            'referrer'   => $ref,
            'viewed_at'  => $ts,
            'view_order' => $order,
        ]);

        // Update exit_page + duration from the latest view ts.
        $db->execute(
            'UPDATE sessions SET exit_page = ?, duration_sec = CAST((julianday(?) - julianday(started_at)) * 86400 AS INTEGER) WHERE session_id = ?',
            [$path, $ts, $session]
        );
    }

    /** POST /api/track/event: insert a custom interaction event row. */
    public static function recordEvent(array $p): void
    {
        $db      = self::db();
        $visitor = self::uuidOrNew($p['visitor_id'] ?? null);
        $session = self::uuidOrNew($p['session_id'] ?? null);
        $name    = self::str($p['event_name'] ?? '');
        if ($name === '') {
            return; // nameless events are noise, drop them
        }
        $payload = $p['payload'] ?? null;
        $payload = is_array($payload) ? json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
        $db->insert('events', [
            'session_id'  => $session,
            'visitor_id'  => $visitor,
            'event_name'  => substr($name, 0, 80),
            'page_path'   => self::path($p['page_path'] ?? ''),
            'payload'     => $payload,
            'occurred_at' => self::ts($p['ts'] ?? null),
        ]);
    }

    /** POST /api/track/session_end: close the session, mark bounce. */
    public static function recordSessionEnd(array $p): void
    {
        $db      = self::db();
        $session = self::str($p['session_id'] ?? '');
        if ($session === '') {
            return;
        }
        $ts = self::ts($p['ts'] ?? null);
        $db->execute(
            'UPDATE sessions SET ended_at = ?, duration_sec = CAST((julianday(?) - julianday(started_at)) * 86400 AS INTEGER) WHERE session_id = ?',
            [$ts, $ts, $session]
        );
        // Bounce: exactly 1 page view AND under 10 seconds.
        $views = (int) $db->scalar('SELECT COUNT(*) FROM page_views WHERE session_id = ?', [$session]);
        $secs  = (float) ($db->scalar('SELECT duration_sec FROM sessions WHERE session_id = ?', [$session]) ?? 0);
        if ($views === 1 && $secs < 10) {
            $db->execute('UPDATE sessions SET is_bounce = 1 WHERE session_id = ?', [$session]);
        }
    }

    /* ======================= summary ======================= */

    /**
     * GET /api/retention/summary - locked shape per RETENTION_EVENT_CONTRACT.md.
     * Window: trailing 14 calendar days, ISO-8601 UTC (from inclusive, to exclusive).
     */
    public static function summary(): array
    {
        $db   = self::db();
        $from = gmdate('Y-m-d', strtotime('-13 days')) . 'T00:00:00Z';
        $to   = gmdate('Y-m-d', strtotime('+1 day')) . 'T00:00:00Z';

        // totals (queries 1, 2, 5a window-scoped)
        $t = $db->fetch(
            'SELECT count(DISTINCT visitor_id) AS unique_visitors, count(*) AS sessions,
                    count(DISTINCT CASE WHEN is_bounce = 0 THEN session_id END) AS engaged_sessions
             FROM sessions WHERE started_at >= :from AND started_at < :to',
            ['from' => $from, 'to' => $to]
        ) ?? [];
        $d = $db->fetch(
            'SELECT round(avg(duration_sec), 1) AS avg_session_sec,
                    round(avg(CASE WHEN is_bounce = 0 THEN duration_sec END), 1) AS avg_engaged_sec,
                    round(sum(CASE WHEN is_bounce = 0 THEN 1 ELSE 0 END) * 1.0 / nullif(count(*), 0), 3) AS engaged_rate
             FROM sessions
             WHERE started_at >= :from AND started_at < :to AND ended_at IS NOT NULL',
            ['from' => $from, 'to' => $to]
        ) ?? [];
        $r = $db->fetch(
            'SELECT count(*) FILTER (WHERE active_days > 1) AS returning_visitors, count(*) AS total_visitors,
                    round(100.0 * count(*) FILTER (WHERE active_days > 1) / nullif(count(*), 0), 1) AS returning_pct
             FROM (SELECT visitor_id, count(DISTINCT date(started_at)) AS active_days
                   FROM sessions WHERE started_at >= :from AND started_at < :to
                   GROUP BY visitor_id)',
            ['from' => $from, 'to' => $to]
        ) ?? [];

        $unique   = (int) ($t['unique_visitors'] ?? 0);
        $sessions = (int) ($t['sessions'] ?? 0);
        $engaged  = (int) ($t['engaged_sessions'] ?? 0);
        $bounce   = $sessions > 0 ? round(100.0 * ($sessions - $engaged) / $sessions, 1) : 0.0;

        $totals = [
            'unique_visitors' => $unique,
            'sessions'        => $sessions,
            'avg_session_sec' => (float) ($d['avg_session_sec'] ?? 0.0),
            'avg_engaged_sec' => (float) ($d['avg_engaged_sec'] ?? 0.0),
            'engaged_rate'    => (float) ($d['engaged_rate'] ?? 0.0),
            'bounce_pct'      => $bounce,
            'returning_pct'   => (float) ($r['returning_pct'] ?? 0.0),
        ];

        // daily (queries 1 + 2 merged on day, newest first)
        $byDay = [];
        foreach ($db->fetchAll(
            'SELECT date(started_at) AS day, count(DISTINCT visitor_id) AS unique_visitors, count(*) AS sessions,
                    round(100.0 * sum(is_bounce) / count(*), 1) AS bounce_pct
             FROM sessions WHERE started_at >= :from AND started_at < :to
             GROUP BY day ORDER BY day DESC',
            ['from' => $from, 'to' => $to]
        ) as $row) {
            $byDay[(string) $row['day']] = [
                'day'             => (string) $row['day'],
                'unique_visitors' => (int) $row['unique_visitors'],
                'sessions'        => (int) $row['sessions'],
                'avg_session_sec' => 0.0,
                'bounce_pct'      => (float) $row['bounce_pct'],
            ];
        }
        foreach ($db->fetchAll(
            'SELECT date(started_at) AS day, round(avg(duration_sec), 1) AS avg_session_sec
             FROM sessions WHERE started_at >= :from AND started_at < :to AND ended_at IS NOT NULL
             GROUP BY day',
            ['from' => $from, 'to' => $to]
        ) as $row) {
            if (isset($byDay[(string) $row['day']])) {
                $byDay[(string) $row['day']]['avg_session_sec'] = (float) $row['avg_session_sec'];
            }
        }
        $daily = array_values($byDay);

        // top_pages (query 3)
        $topPages = $db->fetchAll(
            'SELECT page_path, count(*) AS views, count(DISTINCT visitor_id) AS unique_visitors,
                    round(100.0 * count(*) / (SELECT count(*) FROM page_views WHERE viewed_at >= :from AND viewed_at < :to), 1) AS share_pct
             FROM page_views WHERE viewed_at >= :from AND viewed_at < :to
             GROUP BY page_path ORDER BY views DESC LIMIT 20',
            ['from' => $from, 'to' => $to]
        );

        // entry_pages (query 4a)
        $entryPages = $db->fetchAll(
            'SELECT entry_page, count(*) AS entries, count(DISTINCT visitor_id) AS unique_visitors,
                    round(100.0 * count(*) / (SELECT count(*) FROM sessions WHERE started_at >= :from AND started_at < :to), 1) AS share_pct
             FROM sessions WHERE started_at >= :from AND started_at < :to
             GROUP BY entry_page ORDER BY entries DESC LIMIT 20',
            ['from' => $from, 'to' => $to]
        );

        // top_flows (query 4d): full ordered click paths as path ARRAYS
        $flows = [];
        foreach ($db->fetchAll(
            'SELECT path, pages_visited, count(*) AS occurrences
             FROM click_path GROUP BY path, pages_visited ORDER BY occurrences DESC LIMIT 25'
        ) as $row) {
            $flows[] = [
                'path'          => array_values(array_filter(array_map('trim', explode('>', (string) $row['path'])))),
                'pages_visited' => (int) $row['pages_visited'],
                'occurrences'   => (int) $row['occurrences'],
            ];
        }

        // sources (query 6)
        $sources = $db->fetchAll(
            "SELECT CASE
                        WHEN referrer IS NULL OR referrer = '' THEN 'direct'
                        WHEN referrer LIKE '%google.%' THEN 'google'
                        WHEN referrer LIKE '%facebook%' THEN 'facebook'
                        WHEN referrer LIKE '%instagram%' THEN 'instagram'
                        ELSE 'other'
                    END AS source,
                    count(*) AS sessions, count(DISTINCT visitor_id) AS unique_visitors,
                    round(100.0 * count(*) / (SELECT count(*) FROM sessions WHERE started_at >= :from AND started_at < :to), 1) AS share_pct
             FROM sessions WHERE started_at >= :from AND started_at < :to
             GROUP BY source ORDER BY sessions DESC",
            ['from' => $from, 'to' => $to]
        );

        return [
            'window_start' => $from,
            'window_end'   => $to,
            'totals'       => $totals,
            'daily'        => $daily,
            'top_pages'    => $topPages,
            'entry_pages'  => $entryPages,
            'top_flows'    => $flows,
            'sources'      => $sources,
        ];
    }

    /* ======================= helpers ======================= */

    /** Return a valid uuid v4, or generate a fresh one. */
    private static function uuidOrNew(?string $v): string
    {
        $v = (string) ($v ?? '');
        return preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $v)
            ? strtolower($v)
            : self::uuid();
    }

    /** Generate a uuid v4 (RFC 4122) without external deps. */
    public static function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        $h = bin2hex($b);
        return substr($h, 0, 8) . '-' . substr($h, 8, 4) . '-' . substr($h, 12, 4) . '-' . substr($h, 16, 4) . '-' . substr($h, 20, 12);
    }

    /** Normalize a page path to a safe, short string. */
    private static function path(?string $v): string
    {
        $v = trim((string) ($v ?? '/'));
        if ($v === '') {
            return '/';
        }
        $v = strtok($v, '?#') ?: '/';
        return strlen($v) > 255 ? substr($v, 0, 255) : $v;
    }

    /** Clean a text field (null-safe, length-capped). */
    private static function str(?string $v): string
    {
        $v = trim((string) ($v ?? ''));
        return strlen($v) > 1024 ? substr($v, 0, 1024) : $v;
    }

    /** ISO-8601 UTC with Z (ms precision); accepts a client ts or now. */
    private static function ts(?string $v): string
    {
        $v = trim((string) ($v ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?Z$/', $v)) {
            return substr($v, 0, 23) . 'Z';
        }
        return gmdate('Y-m-d\TH:i:s.v\Z');
    }

    /** desktop | mobile | tablet from a user agent string. */
    private static function deviceClass(string $ua): string
    {
        $ua = strtolower($ua);
        if (str_contains($ua, 'ipad') || (str_contains($ua, 'macintosh') && str_contains($ua, 'mobile'))) {
            return 'tablet';
        }
        if (preg_match('/(android|iphone|ipod|windows phone|mobile)/', $ua)) {
            return 'mobile';
        }
        return 'desktop';
    }
}
