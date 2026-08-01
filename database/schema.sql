-- ============================================================
-- Patriot Pest Control — SQLite schema (idempotent)
-- Applied automatically by app/Core/Database.php on first run.
-- Every table uses CREATE TABLE IF NOT EXISTS so re-running is safe.
-- Conventions: snake_case, INTEGER PK AUTOINCREMENT, UTC timestamps (TEXT ISO-8601).
-- ============================================================

-- ---------- Meta (schema versioning) ----------
CREATE TABLE IF NOT EXISTS meta (
    key   TEXT PRIMARY KEY,
    value TEXT
);

-- ---------- Roles & staff (RBAC) ----------
-- permissions is a JSON array, e.g. ["all"] or ["view_customers","send_messages"].
CREATE TABLE IF NOT EXISTS roles (
    role        TEXT PRIMARY KEY,
    label       TEXT NOT NULL,
    permissions TEXT NOT NULL DEFAULT '[]'
);

-- Staff have NO password column by design: login is passwordless email-OTP.
CREATE TABLE IF NOT EXISTS staff (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    email      TEXT NOT NULL UNIQUE COLLATE NOCASE,
    name       TEXT NOT NULL,
    role       TEXT NOT NULL DEFAULT 'staff' REFERENCES roles(role),
    active     INTEGER NOT NULL DEFAULT 1,
    last_login TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

-- ---------- Passwordless OTP auth ----------
-- One row per issued code. The code itself is stored HASHED (never plaintext).
-- purpose: 'staff_login' | 'customer_login' | 'sms_verify' | 'unsubscribe_confirm'
CREATE TABLE IF NOT EXISTS otp_codes (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    identity   TEXT NOT NULL,              -- email or phone the code was sent to
    purpose    TEXT NOT NULL,
    code_hash  TEXT NOT NULL,              -- password_hash() of the 6-digit code
    attempts   INTEGER NOT NULL DEFAULT 0, -- brute-force counter
    expires_at TEXT NOT NULL,              -- now + OTP_TTL
    used_at    TEXT,                       -- set once consumed (single-use)
    ip         TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_otp_identity ON otp_codes(identity, purpose);

-- Login attempt log → rate limiting + security audit trail.
CREATE TABLE IF NOT EXISTS login_attempts (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    identity   TEXT NOT NULL,
    ip         TEXT,
    user_agent TEXT,
    success    INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_login_identity ON login_attempts(identity, created_at);
CREATE INDEX IF NOT EXISTS idx_login_ip ON login_attempts(ip, created_at);

-- Active sessions (for "sign out everywhere" / revocation).
CREATE TABLE IF NOT EXISTS sessions (
    id            TEXT PRIMARY KEY,        -- session id
    user_id       INTEGER,
    user_type     TEXT NOT NULL,           -- 'staff' | 'customer'
    display_name  TEXT,
    role          TEXT,
    ip            TEXT,
    user_agent    TEXT,
    created_at    TEXT NOT NULL DEFAULT (datetime('now')),
    last_activity TEXT NOT NULL DEFAULT (datetime('now')),
    expires_at    TEXT
);

-- ---------- Customers (local cache of FieldRoutes + flags) ----------
-- FieldRoutes stays the source of truth; we cache identity + local flags
-- (no-call / do-not-contact) so reactivation respects opt-outs even offline.
CREATE TABLE IF NOT EXISTS customers (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    fr_id          TEXT,                   -- FieldRoutes customer id
    district       TEXT,                   -- 'wa' | 'az'
    name           TEXT,
    email          TEXT COLLATE NOCASE,
    phone          TEXT,
    account_number TEXT,
    address        TEXT,
    city           TEXT,
    state          TEXT,
    zip            TEXT,
    status         TEXT DEFAULT 'active',  -- active | cancelled | inactive
    is_no_call     INTEGER NOT NULL DEFAULT 0, -- 1 = do NOT contact (synced to FR)
    dnc_reason     TEXT,                   -- why flagged (e.g. "requested no contact")
    last_service   TEXT,
    created_at     TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at     TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_cust_email ON customers(email);
CREATE INDEX IF NOT EXISTS idx_cust_phone ON customers(phone);
CREATE INDEX IF NOT EXISTS idx_cust_status ON customers(status, is_no_call);

-- ---------- Messaging & support (carried over from existing app) ----------
CREATE TABLE IF NOT EXISTS messages (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    from_user  TEXT NOT NULL,
    from_type  TEXT NOT NULL,              -- 'staff' | 'customer'
    from_name  TEXT,
    to_user    TEXT NOT NULL,
    to_type    TEXT NOT NULL,
    to_name    TEXT,
    subject    TEXT,
    body       TEXT NOT NULL,
    is_read    INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS tickets (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id   TEXT NOT NULL,
    customer_name TEXT,
    category      TEXT,
    priority      TEXT DEFAULT 'normal',
    subject       TEXT NOT NULL,
    body          TEXT NOT NULL,
    status        TEXT DEFAULT 'open',
    created_at    TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at    TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS ticket_responses (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    ticket_id  INTEGER NOT NULL REFERENCES tickets(id) ON DELETE CASCADE,
    from_user  TEXT,
    from_name  TEXT,
    body       TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS cases (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    case_number       TEXT NOT NULL UNIQUE,
    customer_id       TEXT NOT NULL,
    customer_name     TEXT,
    type              TEXT NOT NULL,
    type_label        TEXT,
    priority          TEXT NOT NULL DEFAULT 'normal',
    subject           TEXT NOT NULL,
    description       TEXT,
    status            TEXT NOT NULL DEFAULT 'open',
    created_by        TEXT,
    created_by_name   TEXT,
    created_by_type   TEXT,
    assigned_to       TEXT,
    assigned_to_name  TEXT,
    created_at        TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at        TEXT NOT NULL DEFAULT (datetime('now')),
    resolved_at       TEXT
);

CREATE TABLE IF NOT EXISTS case_tickets (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    case_id    INTEGER NOT NULL REFERENCES cases(id) ON DELETE CASCADE,
    subject    TEXT,
    body       TEXT NOT NULL,
    type       TEXT,
    by_user    TEXT,
    by_name    TEXT,
    by_type    TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS case_timeline (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    case_id    INTEGER NOT NULL REFERENCES cases(id) ON DELETE CASCADE,
    action     TEXT NOT NULL,
    by_name    TEXT,
    by_type    TEXT,
    message    TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS notifications (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    category      TEXT NOT NULL,
    type          TEXT NOT NULL,
    message       TEXT NOT NULL,
    for_staff     INTEGER NOT NULL DEFAULT 1,
    customer_id   TEXT,
    customer_name TEXT,
    link          TEXT,
    is_read       INTEGER NOT NULL DEFAULT 0,
    created_at    TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS customer_notes (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id TEXT NOT NULL,
    note        TEXT NOT NULL,
    updated_by  TEXT,
    updated_at  TEXT NOT NULL DEFAULT (datetime('now'))
);

-- ---------- Twilio phone intelligence (carried over) ----------
CREATE TABLE IF NOT EXISTS carrier_lookup (
    phone       TEXT PRIMARY KEY,          -- E.164
    carrier     TEXT,
    line_type   TEXT,
    sms_capable INTEGER,
    raw_json    TEXT,
    looked_up   TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS phone_risk_cache (
    phone      TEXT PRIMARY KEY,
    risk_score REAL,
    verdict    TEXT,
    raw_json   TEXT,
    looked_up  TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS phone_lookup_log (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    phone      TEXT,
    kind       TEXT,                       -- 'lookup' | 'risk'
    by_staff   TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

-- ---------- Reactivation / customer retention (NEW) ----------
-- Templates are chosen by season + region (state/zip → dominant pest).
-- body_html for email, body_sms for verified text. Merge tags: {{name}},
-- {{city}}, {{pest}}, {{season}}, {{unsubscribe_url}}.
CREATE TABLE IF NOT EXISTS reactivation_templates (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT NOT NULL,
    subject     TEXT,                      -- email subject line
    body_html   TEXT,                      -- email body
    body_sms    TEXT,                      -- SMS body (<=160 chars recommended)
    pest_type   TEXT,                      -- which pest this targets (links to pest_photos.slug)
    season      TEXT,                      -- spring | summer | fall | winter | any
    states      TEXT DEFAULT '[]',         -- JSON array of state codes this applies to
    channel     TEXT DEFAULT 'email',      -- email | sms | both
    active      INTEGER NOT NULL DEFAULT 1,
    created_at  TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at  TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS reactivation_campaigns (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    name        TEXT NOT NULL,
    template_id INTEGER REFERENCES reactivation_templates(id),
    status      TEXT DEFAULT 'draft',      -- draft | scheduled | running | paused | done
    schedule    TEXT,                      -- cron-like or weekly cadence description
    created_by  TEXT,
    created_at  TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS reactivation_sends (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    campaign_id    INTEGER REFERENCES reactivation_campaigns(id) ON DELETE SET NULL,
    customer_id    INTEGER REFERENCES customers(id) ON DELETE SET NULL,
    channel        TEXT NOT NULL,          -- email | sms
    to_address     TEXT,                   -- email or phone actually used
    template_id    INTEGER,
    status         TEXT DEFAULT 'queued',  -- queued | sent | delivered | opened | clicked | bounced | unsubscribed
    sent_at        TEXT,
    opened_at      TEXT,
    clicked_at     TEXT,
    unsubscribed_at TEXT,
    created_at     TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_react_customer ON reactivation_sends(customer_id, status);

-- Global unsubscribe list. On unsubscribe we set customers.is_no_call=1 and
-- queue a sync to FieldRoutes (mark the FR profile as no-call).
CREATE TABLE IF NOT EXISTS unsubscribes (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER REFERENCES customers(id) ON DELETE SET NULL,
    email       TEXT COLLATE NOCASE,
    phone       TEXT,
    channel     TEXT,                      -- email | sms | all
    reason      TEXT,
    synced_to_fr INTEGER NOT NULL DEFAULT 0,
    created_at  TEXT NOT NULL DEFAULT (datetime('now'))
);

-- ---------- CMS: pest photo library (NEW) ----------
-- The single library of real pest photos. Blog editor + pages pick from here.
-- filename is relative to public/assets/img/pests/.
CREATE TABLE IF NOT EXISTS pest_photos (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    slug            TEXT NOT NULL UNIQUE,  -- 'ants', 'spiders', ...
    name            TEXT NOT NULL,         -- 'Ants'
    scientific_name TEXT,                  -- 'Camponotus spp.'
    filename        TEXT NOT NULL,         -- 'ants.jpg'
    description     TEXT,
    category        TEXT DEFAULT 'insect', -- insect | rodent | wildlife
    threat_level    INTEGER DEFAULT 50,    -- 0-100, used on threat board
    sort_order      INTEGER DEFAULT 0,
    created_at      TEXT NOT NULL DEFAULT (datetime('now'))
);

-- ---------- CMS: blog posts (unified template) (NEW) ----------
-- Every post renders through ONE template; pest_photo_id pulls the tactical
-- photo treatment. status: draft | published | scheduled.
CREATE TABLE IF NOT EXISTS posts (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    slug          TEXT NOT NULL UNIQUE,
    title         TEXT NOT NULL,
    excerpt       TEXT,
    body_html     TEXT,
    pest_photo_id INTEGER REFERENCES pest_photos(id) ON DELETE SET NULL,
    season        TEXT,                    -- spring | summer | fall | winter
    pest_category TEXT,
    status        TEXT NOT NULL DEFAULT 'draft',
    author        TEXT,
    views         INTEGER NOT NULL DEFAULT 0,
    published_at  TEXT,
    date_modified TEXT,
    created_at    TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at    TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_posts_status ON posts(status, published_at);

-- ---------- CMS: editable content blocks (NEW) ----------
-- Lets admins edit ANY section of ANY page (WordPress-like). Each block is a
-- keyed chunk of a page; content_json holds its structured content.
CREATE TABLE IF NOT EXISTS content_blocks (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    page         TEXT NOT NULL,            -- 'home', 'services', 'about', ...
    block_key    TEXT NOT NULL,            -- 'hero', 'threat_intro', 'guarantee', ...
    block_type   TEXT NOT NULL DEFAULT 'html', -- html | text | hero | stats | faq | cta
    content_json TEXT NOT NULL DEFAULT '{}',
    sort_order   INTEGER DEFAULT 0,
    updated_by   TEXT,
    updated_at   TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE(page, block_key)
);

-- ---------- Global site settings (NEW) ----------
CREATE TABLE IF NOT EXISTS site_settings (
    key        TEXT PRIMARY KEY,
    value      TEXT,
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

-- ---------- Audit log (security + debugging) (NEW) ----------
-- Immutable trail of who did what. Essential for troubleshooting + security.
CREATE TABLE IF NOT EXISTS audit_log (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id    TEXT,
    user_type  TEXT,                       -- staff | customer | system
    action     TEXT NOT NULL,              -- 'login', 'settings.update', 'post.create', ...
    entity     TEXT,                       -- 'post', 'customer', 'settings', ...
    entity_id  TEXT,
    meta_json  TEXT,
    ip         TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_audit_action ON audit_log(action, created_at);
CREATE INDEX IF NOT EXISTS idx_audit_user ON audit_log(user_id, user_type);

-- ---------- Seed: default roles ----------
INSERT OR IGNORE INTO roles (role, label, permissions) VALUES
    ('admin',       'Administrator', '["all"]'),
    ('tech_support','Tech Support',  '["view_customers","search_customers","manage_appointments","view_tickets","respond_tickets","send_messages"]'),
    ('accounts',    'Accounts',      '["view_customers","search_customers","manage_billing","view_tickets","respond_tickets","send_messages"]'),
    ('sales',       'Sales',         '["view_customers","search_customers","create_customers","manage_subscriptions","send_messages"]'),
    ('staff',       'Staff',         '["view_customers","search_customers","send_messages"]');
