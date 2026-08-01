-- ============================================================================
-- FIRST-PARTY RETENTION ANALYTICS // SQLITE SCHEMA // STORAGE/RETENTION.SQLITE
-- Owner: Nash (data model) / Edison (implementation).
-- Mirrors analytics/retention_tracking/RETENTION_SCHEMA.sql exactly.
-- Separate database file so the analytics sessions table never collides with
-- the app auth sessions table in database/patriot.db.
--   - visitor_id  : anonymous uuid persisted in localStorage (cross-session)
--   - session_id  : uuid per visit, held in sessionStorage (per-tab)
--   - click_path  : derived VIEW over page_views, never duplicated to disk
--   - timestamps  : ISO-8601 UTC text, always with Z, sortable and portable
--   - referrer    : captured per visitor (first), per session, per page view
-- ============================================================================

PRAGMA journal_mode = WAL;
PRAGMA foreign_keys = ON;

-- ----------------------------------------------------------------------------
-- VISITORS // one row per anonymous browser identity
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS visitors (
    visitor_id    TEXT PRIMARY KEY,               -- uuid from localStorage
    first_seen_at TEXT NOT NULL,                  -- ISO-8601 UTC
    last_seen_at  TEXT NOT NULL,
    user_agent    TEXT,
    referrer      TEXT,                           -- first referrer for visitor
    device_class  TEXT                            -- desktop | mobile | tablet
);

-- ----------------------------------------------------------------------------
-- SESSIONS // one row per visit
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sessions (
    session_id    TEXT PRIMARY KEY,               -- uuid from sessionStorage
    visitor_id    TEXT NOT NULL REFERENCES visitors(visitor_id),
    started_at    TEXT NOT NULL,                  -- ISO-8601 UTC
    ended_at      TEXT,                           -- set at unload / timeout
    duration_sec  INTEGER,                        -- ended_at - started_at
    entry_page    TEXT,                           -- first page_path
    exit_page     TEXT,                           -- last page_path
    referrer      TEXT,
    user_agent    TEXT,
    device_class  TEXT,
    is_bounce     INTEGER NOT NULL DEFAULT 0      -- 1 = 1 page AND <10s
);

CREATE INDEX IF NOT EXISTS idx_sessions_visitor   ON sessions(visitor_id);
CREATE INDEX IF NOT EXISTS idx_sessions_started   ON sessions(started_at);
CREATE INDEX IF NOT EXISTS idx_sessions_referrer  ON sessions(referrer);

-- ----------------------------------------------------------------------------
-- PAGE_VIEWS // every page load, ordered per session
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS page_views (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id    TEXT NOT NULL REFERENCES sessions(session_id),
    visitor_id    TEXT NOT NULL REFERENCES visitors(visitor_id),
    page_path     TEXT NOT NULL,                  -- e.g. /prices
    page_title    TEXT,
    referrer      TEXT,                           -- per-view referrer
    viewed_at     TEXT NOT NULL,                  -- ISO-8601 UTC
    view_order    INTEGER NOT NULL                -- 1,2,3... within session
);

CREATE INDEX IF NOT EXISTS idx_pv_session_order ON page_views(session_id, view_order);
CREATE INDEX IF NOT EXISTS idx_pv_page         ON page_views(page_path);
CREATE INDEX IF NOT EXISTS idx_pv_viewed_at    ON page_views(viewed_at);
CREATE INDEX IF NOT EXISTS idx_pv_visitor      ON page_views(visitor_id);

-- ----------------------------------------------------------------------------
-- EVENTS // custom interaction events (link clicks, easter egg, etc.)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS events (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id    TEXT NOT NULL REFERENCES sessions(session_id),
    visitor_id    TEXT NOT NULL REFERENCES visitors(visitor_id),
    event_name    TEXT NOT NULL,                  -- link_click | easter_egg_reveal | ...
    page_path     TEXT,
    payload       TEXT,                           -- JSON: {target, label, value}
    occurred_at   TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_events_name  ON events(event_name, occurred_at);
CREATE INDEX IF NOT EXISTS idx_events_sess  ON events(session_id);

-- ----------------------------------------------------------------------------
-- CLICK_PATH // ordered page-visit sequences per session (derived, read-only)
-- ----------------------------------------------------------------------------
CREATE VIEW IF NOT EXISTS click_path AS
SELECT
    session_id,
    visitor_id,
    group_concat(page_path, ' > ') AS path,
    count(*)                        AS pages_visited
FROM (
    SELECT
        session_id,
        visitor_id,
        page_path,
        row_number() OVER (PARTITION BY session_id ORDER BY view_order) AS rn
    FROM page_views
)
GROUP BY session_id, visitor_id;
