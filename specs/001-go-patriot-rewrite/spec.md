# Feature Specification: Go Rewrite — go.patriotpest.pro (All-in-One GHL Replacement)

**Feature Branch**: `001-go-patriot-rewrite`

**Created**: 2026-08-13

**Status**: Draft

**Input**: User description: "Create subdomain go.patriotpest.pro, new branch feat/go-rewrite, complete Go rewrite pixel-identical to patriotpest.pro patriotic theme + crosshair, modular every feature on/off, ingest PHP code from www + test, MCP API for super-admin agents (create blog etc.), customer+admin dashboards, FieldRoutes+Twilio unified inbox (social inbox), replace GoHighLevel/Salesforce, game-like scroll"

## User Scenarios & Testing

### User Story 1 - Visitor sees patriotic game-like marketing site (Priority: P1)

A visitor lands on `go.patriotpest.pro` and experiences the identical tactical theme: olive/khaki/paper/orange, Black Ops One hero, bugfield canvas, crosshair, HUD clock, Lenis smooth scroll. Skylers test: "feels like a video game, mesmerizing."

**Why this priority**: Brand identity — without parity the rewrite is rejected.

**Independent Test**: Open `go.patriotpest.pro/` and `www.patriotpest.pro/` side-by-side; visual diff <2% per Percy/Chromatic; Lighthouse perf ≥90.

**Acceptance Scenarios**:
1. **Given** marketing module enabled, **When** GET `/`, **Then** hero crosshair moves on mousemove, bugfield repels, no scrollbars, grain+GSSP reveals work.
2. **Given** module disabled (`MARKETING_ENABLED=false`), **When** GET `/`, **Then** 404.

### User Story 2 - Passwordless OTP login for everyone (Priority: P1)

Customer enters email/phone/account, staff enters email — system matches staff first, sends 6-digit OTP (8-digit for super-user `/su`), verifies single-use, opens role-routed dashboard. No passwords.

**Why this priority**: Gate to all dashboards; replaces PHP `OtpAuth`.

**Independent Test**: Request OTP for `staff@x` and `customer@y`, verify with `curl POST /login/verify` — session cookie set, redirect to correct dashboard.

**Acceptance Scenarios**:
1. **Given** valid email, **When** POST `/login` + POST `/login/verify` with correct code, **Then** 302 to `/customer-dashboard` or `/admin`/`/staff-dashboard` by role, `Session::regenerate` done.
2. **Given** wrong code 5 times, **When** 6th verify, **Then** 429 lockout per `OTP_MAX_ATTEMPTS`.
3. **Given** `SUPERUSER_ENABLED=false`, **When** GET `/su`, **Then** 404.

### User Story 3 - Customer + Staff + Admin dashboards (Priority: P1)

Unified portal: customer sees account/services, staff sees book/search/messages, admin sees CMS/blog/media/settings/api-keys/twilio/retention.

**Why this priority**: Core daily use; replaces GoHighLevel dashboard.

**Independent Test**: Login as each role, navigate to dashboard; RBAC blocks cross-access (403).

**Acceptance Scenarios**:
1. **Given** customer session, **When** GET `/staff-dashboard`, **Then** 302 `/login` or 403.
2. **Given** admin, **When** GET `/admin/api-keys`, **Then** list+create works; `hasPermission` enforced.

### User Story 4 - Super-admin MCP API key → agent creates blog (Priority: P2)

Super-admin creates `ppc_live_` key with `post:create` scope in `/admin/api-keys`; external agent calls `POST /api/v1/posts` with `Authorization: Bearer ppc_live_...` to create blog; audit logged.

**Why this priority**: AI agent extensibility; Skyler's "MCP server to let agents do things".

**Independent Test**: Create key via UI, `curl -H "Authorization: Bearer ppc_live_..." /api/v1/health` 200, `POST /api/v1/posts` with `customer:read` only → 403.

**Acceptance Scenarios**:
1. **Given** `API_ENABLED=true` + valid scoped key, **When** POST `/api/v1/posts`, **Then** 201 + OpenAPI validated.
2. **Given** revoked/expired key, **When** call, **Then** 401.

### User Story 5 - Unified inbox (Twilio SMS/voice + FieldRoutes + social) (Priority: P2)

Admin sees one inbox merging `messages` table + Twilio SMS + Facebook/IG replies; can reply with Compliance DNC gate.

**Why this priority**: Replaces 3 tools; the big $300/mo saving.

**Independent Test**: Seed `messages` + mock Twilio webhook POST `/webhooks/twilio/sms` (HMAC), see both in `/staff/messages`.

**Acceptance Scenarios**:
1. **Given** `is_no_call=1`, **When** send SMS, **Then** blocked + audit `customer_is_no_call`.
2. **Given** valid phone, **When** reply, **Then** Twilio API called, log in `/admin/twilio/logs`.

### User Story 6 - FieldRoutes customer sync (Priority: P3)

Nightly + webhook sync caches `customers` from FieldRoutes districts WA/AZ.

**Why this priority**: Source of truth.

**Independent Test**: Trigger `/staff/customers/sync` as admin → `customers` table upserted, visible in `/staff/customers/{id}`.

**Acceptance Scenarios**:
1. **Given** admin, **When** POST `/staff/customers/sync`, **Then** 200 + audit.

### Edge Cases

- What happens when OTP expires mid-verify? → 410 "expired, request new".
- How handle SQLite vs Postgres in sqlc? — `DATABASE_URL` selects driver; migrations idempotent.
- Twilio HMAC fails? → 401 before state change.
- Feature flag off? — route never registered (404), not 403.
- Reduced-motion? — bugfield/crosshair/Lenis disabled, content `opacity:1` fallback.

## Requirements

### Functional Requirements

- **FR-001**: System MUST serve all 14 marketing routes — `GET /`, `/about`, `/services`, `/prices`, `/service-areas`, `/faqs`, `/contact`, `POST /contact` (contactSubmit), `GET /referral`, `/socials`, `/help`, `/links`, `/sitemap`, `/privacy-policy`, `/terms-of-use` — with pixel-identical patriotic theme via `templ` (olive/khaki/paper/orange, Black Ops One, hazard stripes, grain, progress bar, Lenis; no scrollbars).
- **FR-002**: System MUST serve DB-driven routes `GET /pest/{slug}` (PestController), `GET /areas/{slug}` (areaDetail), `GET /blogs`, `GET /blogs/{slug}`, `GET /blogs/rss.xml` + `GET /blog/rss.xml` alias (BlogController RSS), plus Cost dashboard `/cost` served direct without bootstrap (modular unloadable).
- **FR-003**: System MUST implement passwordless OTP for everyone (6-digit, 8-digit for `super-login` purpose), `password_hash` at rest, single-use `used_at`, TTL `OTP_TTL=600`/`SU_OTP_TTL=300`, `RateLimiter` on `login_attempts`, enumeration defense, no passwords, `use_strict_mode` session + regen.
- **FR-004**: System MUST provide unified auth flows `GET/POST /login`, `GET/POST /login/verify`, `GET /logout`, legacy aliases `/customer-auth`, `/staff`, `/dashboard`→`/login`, `/staff-logout`, `/customer-verify`/`/staff-verify`→`/login/verify`, and toggleable superuser `GET/POST /su`, `GET/POST /su/verify` (`SUPERUSER_ENABLED=false`→404, Defense1 `role!='super-user'` + Defense2 session guard).
- **FR-005**: System MUST enforce RBAC via `casbin` with roles `Customer`, `Admin`, `SuperAdmin` (`super-user`=`["all"]` immutable), per-route `->auth('customer'|'staff'|'*')`, `->role('admin')`, `->permission('view_customers','send_messages','search_customers')` guards.
- **FR-006**: System MUST expose portals `GET /customer-dashboard` (customer), `GET /staff-dashboard` (staff), `GET /account` (`auth *`), staff tools `GET /staff/customers`, `GET /staff/customers/{id}`, `GET /staff/messages`, `POST /staff/customers/sync`, `GET /api/customer-search`.
- **FR-007**: System MUST expose Admin CMS `GET /admin`, `GET /admin/posts`, `GET /admin/posts/new`, `POST /admin/posts`, `GET/POST /admin/posts/{id}`, `GET /admin/media`, `GET /admin/content`, `GET /admin/settings`, `POST /admin/settings`, Staff CRUD `GET /admin/staff`, `GET /admin/staff/new`, `POST /admin/staff`, `GET/POST /admin/staff/{id}`, `POST /admin/staff/{id}/toggle` (super-user immutable).
- **FR-008**: System MUST expose API key management `GET /admin/api-keys`, `GET /admin/api-keys/audit`, `POST /admin/api-keys` (create `ppc_live_`+64hex, prefix 12, `sha256`+`hash_equals`, shown once), `POST /admin/api-keys/{id}/revoke|rotate|scopes` (`API_ENABLED` toggle retained).
- **FR-009**: System MUST expose Twilio suite `GET /admin/twilio`, `GET /admin/twilio/sms`, `GET /admin/twilio/sms/new`, `POST /admin/twilio/sms/send`, `GET /admin/twilio/sms/{id}`, `GET /admin/twilio/calls`, `GET /admin/twilio/calls/new`, `POST /admin/twilio/calls/initiate`, `GET /admin/twilio/calls/{id}`, `GET /admin/twilio/voicemail`, `GET /admin/twilio/voicemail/{id}`, `POST /admin/twilio/voicemail/{id}/update`, `GET /admin/twilio/webhooks`, `GET /admin/twilio/webhooks/{id}`, `POST /admin/twilio/webhooks/process`.
- **FR-010**: System MUST expose Retention beacons `POST /api/track/view|event|session_end` (CSRF-exempt, same-origin+payload validated), `GET /api/retention/summary` (admin 401 gate), pages `GET /admin/retention`, `POST /admin/retention/settings` (`RETENTION_ENABLED`, separate `storage/retention.sqlite` → Surreal `retention` namespace).
- **FR-011**: System MUST verify webhooks: `POST /webhooks/twilio/sms|status|voice|voicemail` (`X-Twilio-Signature`), `GET /webhooks/facebook` (hub verify) + `POST /webhooks/facebook` (`X-Hub-Signature-256`), `GET /unsubscribe` (HMAC token, CSRF-exempt), all 401 before state change.
- **FR-012**: System MUST gate outbound via Compliance DNC — `customers.is_no_call` + `unsubscribes` (channel `email`/`sms`/`all`), phone candidates raw/digits/+1/LIKE, `signed unsubscribe` tokens via `APP_KEY`.
- **FR-013**: System MUST provide `/api/v1/*` (when `API_ENABLED`) `GET /api/v1/health`, `/customers` (`customer:read`), `/customers/{id}`, `/tickets` (`ticket:read`), `/messages` (`message:read`), `/services`, `/twilio/logs` (`twilio:read`), `/staff` (`staff:read`), `POST /api/v1/posts` (`post:create` for MCP), `GET /health` (no auth) + `/ready`, constant-time compare, scope denial 403, rate limit 429, audit `api.call`.
- **FR-014**: System MUST expose unified inbox when `UNIFIED_INBOX_ENABLED=true` — `GET /staff/messages` merges `messages`+`sms_logs`+`voicemails`+FB/IG/X/LinkedIn threads, each badge with respected logo (FB/IG/X/LI/SMS/voicemail), reply via single box with Compliance gate.
- **FR-015**: System MUST provide email/SMS reactivation workflows — `reactivation_templates` (subject/body, channel email|sms, interval), `reactivation_campaigns` (status draft|active|paused, segment `status=cancelled|past_customer`), `reactivation_sends` (scheduled_at, sent_at, opened, clicked, bounced) + intervals `day 0, 7, 30, 60, 90` + Compliance DNC gate per send, SMS gated by `TWILIO_SMS_ENABLED` + A2P readiness (draft queued until approved).
- **FR-016**: System MUST persist via SurrealDB (port all 38 tables: `roles`, `staff`, `otp_codes`, `login_attempts`, `sessions`, `customers`, `messages`, `tickets`, `ticket_responses`, `cases`, `case_tickets`, `case_timeline`, `notifications`, `customer_notes`, `carrier_lookup`, `phone_risk_cache`, `phone_lookup_log`, `reactivation_templates`, `reactivation_campaigns`, `reactivation_sends`, `unsubscribes`, `pest_photos`, `posts`, `content_blocks`, `site_settings`, `audit_log`, `subscriptions`, `appointments`, `payment_methods`, `invoices`, `payments`, `sms_logs`, `call_logs`, `voicemails`, `webhook_events`, `api_keys`, `facebook_leads`, `inbox_threads`, `inbox_channel_configs`, `kanban_boards`, `kanban_columns`, `kanban_cards`).
- **FR-017**: System MUST provide Settings channel config at `GET/POST /admin/settings#channels` — per-channel token/oauth fields (`FACEBOOK_APP_SECRET`, `FACEBOOK_PAGE_ACCESS_TOKEN`, `FACEBOOK_HUB_VERIFY_TOKEN`, `TWILIO_*`, `X_BEARER_TOKEN`, `LINKEDIN_ACCESS_TOKEN`, voicemail) encrypted at rest via `APP_KEY`, `Enabled` toggle per channel, plug-in no hard-code.
- **FR-018**: System MUST enforce MCP scope: agents can do **anything except DELETE customers / PII** — `DELETE /api/v1/customers/{id}` always 403 regardless of `all` scope; `customer:delete` scope never issued; audit every MCP call.
- **FR-019**: System MUST expose n8n+Zapier workflow hooks — outbound webhook `webhook_events` on `customer.created|ticket.created|message.received` + Zapier trigger, inbound `POST /webhooks/n8n` + `POST /webhooks/zapier` HMAC, `N8N_WEBHOOK_URL` config.
- **FR-020**: System MUST expose kanban board Trello/kanbn parity — `GET /admin/board` boards (`owner, visibility team|private`)+ columns(`title, position, wip_limit`)+ cards(`title, description, labels[], checklist{title,done}[], due_date, assignee_ids[], cover, attachments[], customer_id|case_id, position, column_id`) + drag-drop `POST /api/kanban/boards/{id}/columns/{id}/cards/{id}/move`, WIP enforcement, labels/due/checklist/attachments/cover, inline edit, search/filter. **Staff collaboration:** `kanban_board_members{board_id, staff_id, role viewer|editor|admin}` — `+ Add member` picker, board shared to staff, per-card assignees `assignee_ids` multi-select, `@mentions` in comments, real-time SSE updates (`/api/kanban/boards/{id}/events`), activity feed + `card_comments` + `card_activity` → `case_timeline`, email/push notifications on assign/mention/due.
- **FR-021**: System MUST tie kanban to MCP — `GET/POST /api/v1/kanban/boards|columns|cards|members|comments` with `kanban:read|kanban:write` scopes, agents operate on **caller's boards + shared boards** (`board_members` visibility) via `ppc_live_` key (list accessible boards, move cards, add members, comment with @mention, add cards linked to customers), per-board RBAC `viewer` read-only vs `editor` write, audit.
- **FR-022**: System MUST expose email inbox — `GET /admin/email` unified threads `email_threads`+`email_messages` (IMAP/SMTP via `MAIL_HOST`+OAuth), link external emails (`gmail|titan|outlook` per-channel token in `admin/settings#channels`), show in `GET /staff/messages` unified + logos (email logo), compose/reply Compliance-gated.
- **FR-023**: System MUST expose supply organizer — if FieldRoutes has inventory API, sync `supplies` table; else native `supplies{supply_id, sku, name, qty, reorder_point, location}` + `supply_moves` + `GET /admin/supplies` CRUD + low-stock alerts + link to `kanban_cards`/`appointments`.
- **FR-024**: System MUST expose editable API-key settings for every integration — `GET/POST /admin/settings` sections `FieldRoutes districts` (`+ Add district` → new `key|token` encrypted), `Twilio`, `Facebook`, `X`, `LinkedIn`, `n8n`, `Zapier`, `Surreal`, `Mail`; `+` creates new encrypted record, `rotate`/`revoke`, inline edit, `test` button.
- **FR-025**: System MUST make `david@itak.live` superuser — `SU_SEED_EMAIL=david@itak.live` idempotent grant `role=super-user` + `audit_log superuser.grant`, cannot be demoted/deactivated via staff CRUD.
- **FR-026**: System MUST proxy FieldRoutes payments — **no Stripe gateway** — `GET /customer-portal` shows account (synced `customers`+`subscriptions`+`invoices`), past appointments `GET /api/customer/appointments` (FieldRoutes `appointments` table), receipts/bills `GET /api/customer/invoices/{id}/pdf` (proxy FieldRoutes PDF), next bill `subscriptions.next_billing_date`, `POST /api/customer/pay` proxy `FieldRoutes Pay` (`payment_methods` tokenized), `FieldRoutes` remains processor — Go only displays + proxies + audits `payments`.
- **FR-027**: System MUST expose customer portal actions — `GET /customer-dashboard` + `GET /customer/account`, `GET /customer/messages` (send to company `POST /api/customer/messages` → internal inbox), cancel `POST /api/customer/cancel` returns **tel: link** `+1-509-XXX-XXXX` modal "Call to cancel — talk to us for a retention deal" (never self-cancel, retention offer `reactivation_campaigns` deal trigger), download `GET /customer/invoices/{id}/download`.
- **FR-028**: System MUST expose FieldRoutes as Salesforce — `FieldRoutes` customers+appointments+invoices+subscriptions+payments sync as CRM, `GET /admin/fieldroutes` health, unified search `/api/customer-search`, `customer_notes` GPS.
- **FR-029**: System MUST embed autonomous AI — `modules/ai_automation` background `errgroup` workers: fate-aware scheduler (RAG+ `reactivation_sends` auto personalizes offer), retention AI (at-risk `status=cancelled` → auto workflow), inbox auto-tag (AI classifies inbound FB/X/email → `kanban_cards` label), copilot for tech `GET /tech/ask` same RAG `kb_nodes`, all toggled `AI_AUTONOMOUS_ENABLED` in `admin/knowledge`, `AI_BASE_URL` local→external fallback.
- **FR-030**: System MUST expose modular feature flags (YAML+env) per module (`MARKETING_ENABLED`, `SUPERUSER_ENABLED`, `API_ENABLED`, `RETENTION_ENABLED`, `WORKFLOWS_ENABLED`, `KANBAN_ENABLED`, `EMAIL_INBOX_ENABLED`, `SUPPLY_ENABLED`, `AI_AUTONOMOUS_ENABLED`, etc.), `configs/feature-flags.yaml` hot-reload, PWA manifest `manifest.webmanifest`+`sw.js`, idempotent seed.
- **FR-031**: System MUST support competitive parity — top-20 reference: Orkin, Terminix (Rentokil), Ehrlich, Viking/Anticimex, Massey, Modern, Truly Nolen, Arrow, Aptive, Bulwark, Hawx, Plunkett's, Cook's, Turner, Home Paramount, Dodson, HiCare, Sanix — feature gap tracker in `docs/competitor-matrix.md`.
- **FR-029**: System MUST utilize SurrealDB graph+RAG for knowledge base — `kb_nodes{label, embedding vector<float>, source}`, `kb_edges{from, to, relation}` graph, vector index `MTREE` on embedding, ingest `posts`, `pest_photos`, `tickets`, `cases`, `site_settings` chunks, `knowledge:ask` API `POST /api/knowledge/ask` RAG (graph traversal + vector KNN + rerank), per-workspace memory `kb_memories{user_id, summary}` editable in `GET /admin/knowledge`.
- **FR-030**: System MUST expose Ask AI floating icon — middle-right `ask-ai` FAB (Chat bubble, patriotic pulse) on all public pages (`internal/view/components/ask_ai.templ`), opens drawer → RAG answer with citations + suggested pests/services, `POST /api/knowledge/ask` streamed, `Ask AI` toggle in `admin/knowledge` settings.
- **FR-031**: System MUST provide tech PWA — `GET /tech` PWA installable, `GET /tech/routes` per-tech `GET /api/tech/routes?tech_id=me` GPS-tracked `tech_locations{tech_id, lat,lng, at}`, add notes `POST /api/customers/{id}/notes` → `customer_notes` + GPS `customer_notes{gps}`, case→ticket flow `POST /api/cases`→`POST /api/cases/{id}/tickets` mirrors Salesforce, offline queue via `sw.js`.
- **FR-032**: System MUST provide internal messaging + notifications — `internal_messages{from_staff_id, to_staff_id|channel, body, read}` + `notifications{user_id, type, title, body, read, created_at}` ( Bell icon, SSE `/api/notifications/stream`), integrated in `GET /staff/messages` + `/admin/board` mentions, push via `sw.js`.
- **FR-033**: System MUST provide employee AI assist + OCR Pokedex — `GET /tech/scan` camera → `POST /api/ai/scan` (OCR `tesseract` or `vision` model), `pest_identifications{pest_id, confidence, photo_url, tech_id}` → Pokedex card `pest_photos` enriched `{type, seasonality, treatment, natural_ways, prevention}` via RAG `kb_nodes`, settings `admin/knowledge` toggle local OpenAI-compatible endpoint (`AI_BASE_URL`, `AI_MODEL`, `AI_API_KEY` per spec: local `http://llm:11434/v1` or external `api.openai.com`), fallback chain local→external.

### Key Entities

- **Staff**: `id, email, name, role (staff|admin|super-user), active, last_login` — seed `david@itak.live` super-user
- **Customer**: `id, fr_id, district, name, email, phone, account_number, status, is_no_call, dnc_reason` — FieldRoutes Salesforce cache
- **OtpCode**: `identity, purpose (login|super-login), code_hash, attempts, expires_at, used_at`
- **ApiKey**: `key_prefix, key_hash, scopes JSON, created_by, revoked_at, expires_at` — `ppc_live_` no `customer:delete`
- **Post/Media**: CMS blog `posts`+`pest_photos`+`content_blocks`, `facebook_leads` dedup `fingerprint`
- **Campaign**: `reactivation_campaigns` + `sends` + `templates` — interval workflow `0,7,30,60,90` days, DNC gated
- **Kanban**: `kanban_boards{title, owner, visibility}`+`kanban_columns{title, position, wip_limit}`+`kanban_cards{title, desc, labels[], checklist[], due_date, assignee_ids[], cover, attachments[], customer_id, case_id, position}` + `kanban_board_members{board_id, staff_id, role}` + `card_comments{card_id, author_id, body, mentions[]}` + `card_activity{SSE, timeline}` — shared boards with staff `viewer|editor|admin`, @mentions, real-time, Trello parity + MCP `kanban:read|write`
- **InboxThread**: `inbox_threads{channel, external_id, logo, last_message, user_id}` + `inbox_channel_configs{channel, token_encrypted, enabled}` (now 7 channels inc. `email`)
- **EmailThread**: `email_threads{subject, from, thread_id}`+`email_messages{from, to, body, created_at, mailbox}` IMAP ingest, `+ Add mailbox` in settings
- **Supply**: `supplies{sku, name, qty, reorder_point, location, fieldroutes_id?}`+`supply_moves{qty, reason, card_id}` + `GET /admin/supplies` (native if FieldRoutes no inventory, else sync)

## Success Criteria

- **SC-001**: Visual diff vs `www.patriotpest.pro` <2% on homepage, hero crosshair + bugfield functional, no scrollbars.
- **SC-002**: OTP login E2E <30s per user, 100 concurrent OTP verifies without race (single-use enforced).
- **SC-003**: MCP agent can create blog via API with scoped key in <2s, audit trail present; `DELETE /api/v1/customers/{id}` always 403.
- **SC-004**: Unified inbox shows 6 channels with logos; DNC blocks 100% of flagged sends; n8n+Zapier webhook round-trip <1s.
- **SC-005**: Email/SMS workflows fire `0,7,30,60,90` with open/click tracking; past-customer winback measurable; SMS queued until Twilio A2P approved then drains.
- **SC-006**: Kanban shared boards with staff `viewer|editor|admin`, multi-assign, checklist, SSE real-time, MCP agent moves cards on shared board; `case_timeline` on move; FieldRoutes as Salesforce shows customers/appointments/invoices in one search.
- **SC-007**: 80% business-logic coverage, `golangci-lint` + `govulncheck` + `go build` + `go test -cover` green on `feat/go-rewrite` CI; competitor gap closed vs top 20 (Orkin/Terminix/Ehrlich/Viking/Massey/Truly Nolen...).

## Clarifications (2026-08-13)

- **C1 — Database:** User chose **SurrealDB** (replaces SQLite/Postgres). All `sqlc`/`pgx` references now SurrealDB Go SDK (`surrealdb.go`), record-based `customers`, `staff`, `otp_codes` etc. Migrations via SurrealQL.
- **C2 — Unified inbox:** Must cover **Facebook, Instagram, X/Twitter, LinkedIn, SMS, voicemail** — each with logo badge. Settings dashboard has per-channel token/oauth fields (FB page token, IG token, X bearer, LinkedIn token, Twilio SID/token, voicemail). Plug-in via settings, no hard-coding.
- **C3 — MCP scope:** Agents can do **anything except DELETE customers / customer PII**. `customer:delete`, `customer:hard_delete` scopes never granted; API returns 403 on `DELETE /api/v1/customers/{id}`.
- **C4 — Build:** Fresh **Go rewrite** but **theme pixel-identical** to `www.patriotpest.pro` (olive ramp, bugfield, crosshair identical).
- **C5 — Domains:** `go.patriotpest.pro` is **independent** subdomain, `test.patriotpest.pro` stays untouched; `www` cutover only after `go` fully vetted.

## Assumptions

- `www.patriotpest.pro` stays Hostinger; `go.` lives on Dokploy VPS; cutover after parity.
- SurrealDB runs on VPS (embedded or `surreal start`); config via `SURREAL_URL`/`SURREAL_NS`/`SURREAL_DB`.
- Existing `openapi.yaml` contract preserved for MCP compat + new inbox scopes + `kanban`+`workflow` scopes.
- `alphaflex.net` SaaS will reuse `pkg/` + `internal/rbac` later — out of scope for v1.
- **C6 — Superuser:** `david@itak.live` is super-user (`SU_SEED_EMAIL`), immutable, controls n8n/Zapier + kanban + all channels.
- **C7 — Marketing 2026:** Rank on AI search (ChatGPT 80%/Perplexity 239% YoY, AI Overviews 25-48%) via GEO — `llms.txt`, JSON-LD `LocalBusiness`+`Service`+`FAQPage`, citation seeding.
