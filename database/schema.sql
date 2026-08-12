-- ============================================================
-- Patriot Pest Control - SQLite schema (idempotent)
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

-- Departments for organizational hierarchy
CREATE TABLE IF NOT EXISTS departments (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    name       TEXT NOT NULL,
    parent_id  INTEGER REFERENCES departments(id),
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Staff have NO password column by design: login is passwordless email-OTP.
CREATE TABLE IF NOT EXISTS staff (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    email      TEXT NOT NULL UNIQUE COLLATE NOCASE,
    name       TEXT NOT NULL,
    role       TEXT NOT NULL DEFAULT 'staff' REFERENCES roles(role),
    department_id INTEGER REFERENCES departments(id),
    manager_id INTEGER REFERENCES staff(id),
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
    source         TEXT NOT NULL DEFAULT 'fieldroutes', -- seed | fieldroutes | manual
    is_no_call     INTEGER NOT NULL DEFAULT 0, -- 1 = do NOT contact (synced to FR)
    dnc_reason     TEXT,                   -- why flagged (e.g. "requested no contact")
    last_service   TEXT,
    created_at     TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at     TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_cust_email ON customers(email);
CREATE INDEX IF NOT EXISTS idx_cust_phone ON customers(phone);
CREATE INDEX IF NOT EXISTS idx_cust_status ON customers(status, is_no_call);
CREATE INDEX IF NOT EXISTS idx_cust_source ON customers(source);

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
    ('super-user',  'Super User',     '["all"]'),
    ('admin',       'Administrator', '["all"]'),
    ('tech_support','Tech Support',  '["view_customers","search_customers","manage_appointments","view_tickets","respond_tickets","send_messages"]'),
    ('accounts',    'Accounts',      '["view_customers","search_customers","manage_billing","view_tickets","respond_tickets","send_messages"]'),
    ('sales',       'Sales',         '["view_customers","search_customers","create_customers","manage_subscriptions","send_messages"]'),
    ('staff',       'Staff',         '["view_customers","search_customers","send_messages"]');

-- ---------- Subscriptions (local cache of FieldRoutes subscriptions) ----------
-- FR subscription/search has a known per-customer scoping bug (returns all
-- district subscriptions). We cache here during sync so the customer portal
-- can show reliable per-customer data from the local DB.
CREATE TABLE IF NOT EXISTS subscriptions (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    fr_subscription_id TEXT,                -- FieldRoutes subscription ID
    customer_id       INTEGER REFERENCES customers(id) ON DELETE CASCADE,
    district          TEXT,                 -- 'wa' | 'az'
    status            TEXT DEFAULT 'active', -- Active | Inactive | Cancelled
    status_label      TEXT,                 -- FR activeText
    charge            TEXT,                 -- recurring charge amount
    freq_label        TEXT,                 -- human-readable billing frequency
    next_service      TEXT,                 -- next service date
    last_service      TEXT,                 -- last completed service date
    date_added        TEXT,                 -- subscription start date
    created_at        TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at        TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_sub_customer ON subscriptions(customer_id);
CREATE INDEX IF NOT EXISTS idx_sub_fr ON subscriptions(fr_subscription_id, district);

-- ---------- Appointments (local cache of FieldRoutes appointments) ----------
CREATE TABLE IF NOT EXISTS appointments (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    fr_appointment_id TEXT,                 -- FieldRoutes appointment ID
    customer_id      INTEGER REFERENCES customers(id) ON DELETE CASCADE,
    district         TEXT,                  -- 'wa' | 'az'
    scheduled        TEXT,                  -- when (date + start time)
    type             TEXT,                  -- appointment type
    status_label     TEXT,                  -- Pending | Completed | Scheduled | Cancelled
    status_kind      TEXT,                  -- open | closed | cancelled | scheduled
    notes            TEXT,
    created_at       TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at       TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_appt_customer ON appointments(customer_id);
CREATE INDEX IF NOT EXISTS idx_appt_fr ON appointments(fr_appointment_id, district);

-- ---------- Payment methods, invoices, payments (local billing cache) ----------
-- Billing data from FieldRoutes; no payment processing exists in-app yet.
-- These tables mirror the FR billing entities for offline dashboard display.
CREATE TABLE IF NOT EXISTS payment_methods (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    fr_payment_method_id TEXT,              -- FieldRoutes payment method ID
    customer_id       INTEGER REFERENCES customers(id) ON DELETE CASCADE,
    method_type       TEXT,                 -- credit_card | ach | check | cash
    last_four         TEXT,                 -- last 4 digits of card/account
    exp_month         TEXT,
    exp_year          TEXT,
    is_default        INTEGER NOT NULL DEFAULT 0,
    created_at        TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at        TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_pm_customer ON payment_methods(customer_id);

CREATE TABLE IF NOT EXISTS invoices (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    fr_invoice_id     TEXT,                 -- FieldRoutes invoice ID
    customer_id       INTEGER REFERENCES customers(id) ON DELETE CASCADE,
    invoice_number    TEXT,
    amount            TEXT,                 -- total amount due
    balance           TEXT,                 -- remaining balance
    status            TEXT DEFAULT 'open',   -- open | paid | overdue | void
    due_date          TEXT,
    paid_date         TEXT,
    created_at        TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at        TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_inv_customer ON invoices(customer_id);

CREATE TABLE IF NOT EXISTS payments (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    fr_payment_id     TEXT,                 -- FieldRoutes payment ID
    customer_id       INTEGER REFERENCES customers(id) ON DELETE CASCADE,
    invoice_id        INTEGER REFERENCES invoices(id) ON DELETE SET NULL,
    amount            TEXT,                 -- amount paid
    payment_date      TEXT,
    payment_method    TEXT,                 -- credit_card | ach | cash | check
    status            TEXT DEFAULT 'completed',
    created_at        TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at        TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_pay_customer ON payments(customer_id);

-- ---------- Twilio Integration (NEW) ----------
-- SMS message logs for tracking all SMS activity
CREATE TABLE IF NOT EXISTS sms_logs (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    phone_number   TEXT NOT NULL,
    message        TEXT NOT NULL,
    direction      TEXT NOT NULL,              -- 'inbound' | 'outbound'
    status         TEXT NOT NULL DEFAULT 'queued', -- queued, sent, delivered, failed, undelivered
    twilio_sid     TEXT UNIQUE,                -- Twilio message SID for tracking
    twilio_status  TEXT,                       -- Twilio's detailed status
    error_message  TEXT,
    media_url      TEXT,                       -- MMS media URL if present
    created_at     TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at     TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_sms_phone ON sms_logs(phone_number);
CREATE INDEX IF NOT EXISTS idx_sms_status ON sms_logs(status, created_at);
CREATE INDEX IF NOT EXISTS idx_sms_direction ON sms_logs(direction, created_at);

-- Voice call logs for tracking all call activity
CREATE TABLE IF NOT EXISTS call_logs (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    phone_number   TEXT NOT NULL,
    direction      TEXT NOT NULL,              -- 'inbound' | 'outbound'
    duration       INTEGER DEFAULT 0,          -- Call duration in seconds
    status         TEXT NOT NULL,              -- queued, ringing, in-progress, completed, failed, busy, no-answer
    twilio_sid     TEXT UNIQUE,                -- Twilio call SID
    twilio_status  TEXT,                       -- Twilio's detailed status
    recording_url  TEXT,                       -- URL to call recording
    transcription  TEXT,                       -- Call transcription if available
    error_message  TEXT,
    created_at     TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at     TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_call_phone ON call_logs(phone_number);
CREATE INDEX IF NOT EXISTS idx_call_status ON call_logs(status, created_at);
CREATE INDEX IF NOT EXISTS idx_call_direction ON call_logs(direction, created_at);

-- Voicemail storage and management
CREATE TABLE IF NOT EXISTS voicemails (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    phone_number   TEXT NOT NULL,
    call_sid       TEXT,                       -- Associated call SID from call_logs
    audio_url      TEXT NOT NULL,              -- URL to voicemail audio
    duration       INTEGER DEFAULT 0,          -- Voicemail duration in seconds
    transcription  TEXT,                       -- Voicemail transcription
    status         TEXT DEFAULT 'new',         -- new, listened, archived, deleted
    created_at     TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_voicemail_phone ON voicemails(phone_number);
CREATE INDEX IF NOT EXISTS idx_voicemail_status ON voicemails(status, created_at);

-- Webhook event logging for all Twilio webhook callbacks
CREATE TABLE IF NOT EXISTS webhook_events (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    event_type     TEXT NOT NULL,              -- 'sms.incoming', 'voice.status', 'voicemail', etc.
    twilio_sid     TEXT,                       -- Associated message/call SID
    payload        TEXT NOT NULL,              -- Full JSON payload from Twilio
    processed      INTEGER NOT NULL DEFAULT 0, -- 0 = pending, 1 = processed
    processed_at   TEXT,
    created_at     TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_webhook_type ON webhook_events(event_type, created_at);
CREATE INDEX IF NOT EXISTS idx_webhook_processed ON webhook_events(processed, created_at);
CREATE INDEX IF NOT EXISTS idx_webhook_sid ON webhook_events(twilio_sid);

-- ---------- API Keys (AI/Agent access) ----------
-- Keys are crypto-random, never stored raw. key_prefix is a public lookup id;
-- key_hash is SHA-256 of the full raw key. Raw key shown once at creation.
CREATE TABLE IF NOT EXISTS api_keys (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    name         TEXT NOT NULL,                -- human label for this key
    key_prefix   TEXT NOT NULL UNIQUE,          -- public id (first 12 chars after ppc_live_)
    key_hash     TEXT NOT NULL,                 -- SHA-256 of full raw key
    scopes       TEXT NOT NULL DEFAULT '[]',    -- JSON array of granted scopes
    created_by   INTEGER REFERENCES staff(id),
    last_used_at TEXT,
    expires_at   TEXT,
    revoked_at   TEXT,
    created_at   TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_apikey_prefix ON api_keys(key_prefix);

-- ---------- Facebook Lead Ads (NEW) ----------
-- Inbound leads from Facebook Lead Ads via webhook. Deduplicated by leadgen_id
-- (UNIQUE constraint) with a 24-hour fingerprint fallback (name+email+phone hash).
CREATE TABLE IF NOT EXISTS facebook_leads (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    leadgen_id        TEXT NOT NULL UNIQUE,       -- Facebook leadgen ID
    page_id           TEXT,                       -- Facebook page ID
    form_id           TEXT,                       -- Facebook form ID
    ad_id             TEXT,                       -- Facebook ad ID
    adgroup_id        TEXT,                       -- Facebook adgroup ID
    campaign_id       TEXT,                       -- Facebook campaign ID
    full_name         TEXT,                       -- lead's full name
    email             TEXT COLLATE NOCASE,        -- lead's email
    phone             TEXT,                       -- lead's phone
    city              TEXT,                       -- lead's city
    state             TEXT,                       -- lead's state
    zip               TEXT,                       -- lead's zip
    raw_payload       TEXT,                       -- full JSON from Graph API
    fingerprint       TEXT,                       -- SHA256 hash of name|email|phone for 24h window dedup
    sms_sent          INTEGER NOT NULL DEFAULT 0, -- 1 if SMS dispatched
    sms_sent_at       TEXT,                       -- when SMS was sent
    sms_error         TEXT,                       -- error if SMS failed
    email_fallback_sent INTEGER NOT NULL DEFAULT 0, -- 1 if email fallback dispatched
    email_fallback_sent_at TEXT,                  -- when email fallback was sent
    processed         INTEGER NOT NULL DEFAULT 0, -- 0=pending, 1=processed
    created_at        TEXT NOT NULL DEFAULT (datetime('now')),
    processed_at      TEXT                        -- when fully processed
);
CREATE INDEX IF NOT EXISTS idx_fb_lead_fingerprint ON facebook_leads(fingerprint, created_at);
CREATE INDEX IF NOT EXISTS idx_fb_lead_created ON facebook_leads(created_at);
