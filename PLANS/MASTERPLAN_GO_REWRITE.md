# MASTERPLAN — Go Rewrite: go.patriotpest.pro (Vanguard Spec)

> **Subdomain:** `go.patriotpest.pro` (Dokploy VPS, TLS)
> **Repo branch:** `feat/go-rewrite` (create from `feat/superuser-passwordless-login` tip `8b99d7c`)
> **Legacy hosts:** `www.patriotpest.pro` (Hostinger, stays live) + `test.patriotpest.pro` (PHP staging, stays until cutover)
> **Stack:** Go 1.22+, `chi`, `SurrealDB` (`surrealdb.go`), `casbin`, `templ`, `ogen` OpenAPI, slog+OTel, distroless Docker

---

## 0) Why This Exists (Context You Gave)

- **User:** David Patton (Full Sail AS, Game Design). Friend/owner **Skyler Rose** (Afghanistan, 1yr Army tour) runs Patriot Pest Control.
- **Pain:** Paying **GoHighLevel $300+/mo**, plus **FieldRoutes** (source of truth) + **Twilio** numbers ported back from GHL. 3 dashboards, 3 bills, no unified inbox.
- **Vision:** One self-contained site better than GHL/Salesforce — **customer dashboard + admin dashboard + unified inbox (SMS/voice/social) + MCP API** in one place. Marketing site is `www`; test is `test.`; Go lives at `go.` then promotes to `www` after parity. Future SaaS `alphaflex.net` reuses same Go modules.
- **Look & Feel:** Current `www` felt like a **video game** — "mesmerizing" scroll, patriotic tactical theme. Must be pixel-identical in Go: olive/khaki/paper/orange tactical palette, Black Ops One + Barlow + IBM Plex Mono, hazard stripes, bug-field canvas, crosshair hero, grain, progress bar, Lenis smooth scroll.

**Master files found:** `PLANS/MASTERPLAN_AI_API.md` (MCP/API) + `PLANS/MASTERPLAN_PASSWORDLESS_EMAIL_CODE.md` (superuser OTP). Both are ingested below.

---

## 1) What PHP Does Today (Ingested Inventory)

**Routes (~55):** marketing (`/`, `/about`, `/services`, `/prices`, `/service-areas`, `/faqs`, `/contact`, `/pest/{slug}`, `/areas/{slug}`, `/blogs`, `/blogs/{slug}`, `/blogs/rss.xml`), auth (`/login`, `/login/verify`, `/su`, `/su/verify`, `/logout`), portals (`/customer-dashboard`, `/staff-dashboard`), staff tools (`/staff/customers`, `/staff/customers/{id}`, `/staff/messages`, `/api/customer-search`), admin CMS (`/admin`, `/admin/posts*`, `/admin/media`, `/admin/content`, `/admin/settings`, `/admin/staff*`, `/admin/api-keys*`, `/admin/twilio*`, `/admin/retention`), APIs/beacons (`/api/track/*`, `/api/retention/summary`, `/health`, `/webhooks/{twilio,facebook}`, `/unsubscribe`, `/api/v1/*` when `API_ENABLED`).

**Auth:** Passwordless OTP — `OtpAuth::issue()` 6-digit (8-digit for `super-login`), `password_hash` at rest, single-use `used_at`, TTL `OTP_TTL=600` / `SU_OTP_TTL=300`, `RateLimiter` table `login_attempts`, enumeration defense (same "code sent" screen). No passwords. `Session` hardened (`use_strict_mode`, `httponly`, `samesite=Lax`, regen on login). Roles: `customer`, `staff`, `admin`, `super-user` (`["all"]`).

**DB (database/schema.sql, 570 lines, SurrealDB port):** `roles`, `staff` (`david@itak.live` `super-user`), `otp_codes`, `login_attempts`, `sessions`, `customers` (FieldRoutes), `messages`, `tickets`+`ticket_responses`, `cases`+`case_tickets`+`case_timeline`, `posts`+`content_blocks`, `api_keys` (`ppc_live_`), `audit_log`, `unsubscribes`, `facebook_leads`, plus new `inbox_threads`, `inbox_channel_configs` (7 channels inc. email), `email_threads`+`email_messages`, `kanban_boards`+`kanban_columns`+`kanban_cards` (kanbn/Trello parity), `supplies`+`supply_moves`, `reactivation_templates`+`campaigns`+`sends`, `webhook_events`, full 41 records.

**Integrations:** FieldRoutes (district WA/AZ, encrypted creds in DB), Twilio (Lookup v2, SMS/voice/voicemail, `X-Twilio-Signature` HMAC), Facebook Lead Ads (`X-Hub-Signature-256`, `fingerprint` dedup), email-to-SMS `vtext.com` (LEAD_SMS via Titan SMTP), Compliance DNC gate (`is_no_call` + `unsubscribes`).

**API/MCP:** `ppc_live_` + 64 hex, `sha256` stored, constant-time compare, scopes (`customer:read`, `ticket:read`, `message:read`, `twilio:read`, `staff:read`), `openapi.yaml` at root, `API_ENABLED` toggle, audit `api.call`. Super-admin creates key in `/admin/api-keys` → agents call `/api/v1/*` to create blog etc.

**Theme (public/assets):** `styles.css` (tactical design system `:root` olive ramp), `app.css` (light appshell), `admin.css`, `main.js` (bugfield canvas 56 bugs, crosshair hero `#xh-v/#xh-h/#xh-ring`, HUD clock, typewriter brief, ticker, Lenis+GSAP ScrollTrigger, `data-reveal`, counters, threat meters), `beacon.js` (first-party retention).

**Known PHP debt:** `test.patriotpest.pro` scrollbars — `DeepSeek` left `overflow`/`height:100vh` leaks. `Dockerfile chmod 777`, `Database::insert` identifier interpolation, rate limiter fail-open.

---

## 2) Go Architecture (Vanguard Defaults — No Permission Needed)

### 2.1 Project Layout (golang-standards)

```
cmd/server/main.go          // entry, grace shutdown
internal/
  config/       // 12-factor env: SURREAL_URL/NS/DB, APP_KEY, *_TOKENs (all editable via settings)
  db/           // surreal client (surrealdb.go), migrations (*.surql), tx helper
  middleware/   // recovery, slog+requestID, CORS, ratelimit, timeout, HSTS, CSRF
  auth/         // OTP issue/verify (surreal otp_codes), session
  rbac/         // casbin Customer/Admin/SuperAdmin, HasPermission(), super-user immutable
  obs/          // slog, prom, otel
  view/         // templ layouts (main/app), assets (identical tactical tokens) + kanban + inbox + email views
  modules/
    marketing/  // 14 routes + pest/area/blog/RSS/cost + GEO llms.txt
    portal/     // customer+staff dashboards + /account
    admin/      // CMS posts/media/content/settings#channels + retention
    customers/  // book/search/profile/sync, FieldRoutes Salesforce mirror
    inbox/      // unified 7-channel threads + per-channel adapters fb/ig/x/li/sms/voicemail/email + logos
    email/      // IMAP/SMTP threads, mailboxes, compose
    kanban/     // boards/columns/cards (kanbn/Trello: labels, checklist, due, WIP, drag)
    supply/     // supplies + moves, FieldRoutes sync or native + /admin/supplies
    twilio/     // sms/calls/voicemail suites + HMAC webhooks
    facebook/   // FB+IG lead webhook + Graph fetch
    social/     // X + LinkedIn ingress (token-configured)
    workflows/  // reactivation 0,7,30,60,90 + n8n/Zapier + webhook_events
    api/        // /api/v1/* + /api/v1/kanban/* + MCP proxy (ogen, no customer:delete)
    retention/  // beacon IP→user stitch + summary
    compliance/ // DNC gate, unsubscribe HMAC
    health/     // /health, /ready
  integrations/
    fieldroutes/ // multi-district + supply + payments proxy + health
    twilio/
    surreal/
pkg/validator, pkg/geo, pkg/crypto // + AI + portal billing proxies
api/openapi.yaml (scopes: customer:read, post:create, kanban:read/write, inbox:read/write, payments:read via FieldRoutes)
configs/feature-flags.yaml (MARKETING, KANBAN, EMAIL_INBOX, SUPPLY, AI_AUTONOMOUS, etc.)
migrations/*.surql (41 + kb_nodes, tech_locations, notifications, pest_identifications)
deploy/Dockerfile (distroless:nonroot), deploy/entrypoint.sh // portal pay never stores card
```

`internal/` prevents circular imports; `pkg/` only for truly reusable utils.

### 2.2 Modularity (Plugin-Style, On/Off)

Every feature is an isolated module:

```go
// internal/modules/api/module.go
type Module struct{ Enabled bool; Enforcer *casbin.Enforcer; DB *sql.DB }
func (m *Module) Register(r chi.Router) bool {
  if !m.Enabled { return false }
  r.Route("/api/v1", func(r chi.Router){ r.Get("/health", m.Health); /*...*/ })
  return true
}
```

**Flags:** `configs/feature-flags.yaml` + env overrides (`API_ENABLED`, `SUPERUSER_ENABLED`, `FACEBOOK_ENABLED`, `TWILIO_ENABLED`, `RETENTION_ENABLED`, `BLOG_ENABLED`, `PWA_ENABLED`). Settings dashboard toggles at runtime (persisted in `settings` table, hot-reload via `config.Watch()`).

Default toggles (from PHP `.env.example` + new):

```yaml
marketing: true
auth_otp: true
superuser: false        # SUPERUSER_ENABLED
api: false              # API_ENABLED
twilio_webhooks: true
facebook_leads: true
retention_beacon: true
blog: true
pwa: true
unified_inbox: false    # new — see §4
```

### 2.3 Cross-Cutting

- **Context:** `context.Context` through all layers; `errgroup` + `WaitGroup` for background jobs (FieldRoutes sync, email send); worker pool for beacon ingestion.
- **DB:** `sqlc` type-safe queries, `golang-migrate` migrations (ported from `database/schema.sql`), `pgx` pool (or `modernc.org/sqlite` if staying SQLite), strict tx boundaries.
- **API:** `ogen`/`huma` from `api/openapi.yaml` (keep existing `ppc_live_` contract for MCP compat).
- **Auth:** Email OTP/Magic Link only (never passwords). `casbin` RBAC `Customer|Admin|SuperAdmin`, `govalidator` for inputs.
- **Settings UI:** `/admin/settings` already exists — extend with feature-flag switches + RBAC role editor (Vanguard rule 8).
- **Frontend:** `templ` + HTMX (or Wails if desktop later). WCAG 2.1 AA, server state sync documented.
- **Obs:** `log/slog` JSON + `X-Request-ID`, `/health` (no auth) + `/ready` (DB check), Prometheus `/metrics`.
- **Security:** Param queries only, input sanitization, CSRF (non-webhook POST), `Secure; HttpOnly; SameSite=Lax` cookies, HSTS.

---

## 3) Pixel-Identical Theme (Patriotic Tactical)

Port verbatim — no redesign:

**Tokens (`styles.css:1`):** `--olive-950 #0e130a`, `--olive-900 #1c2415`, `--olive-800 #26301c`, `--olive-700 #334024`, `--olive-500 #5c6f3a`, `--olive-300 #8fa05e`, `--khaki #c8b98c`, `--paper #ece4cd`, `--orange #f4772e`, `--red #c8402a`, `--cream #f5f1e4`. Fonts: `Black Ops One` (display), `Barlow` (body), `IBM Plex Mono` (mono). Dark marketing shell + light `app.css` appshell (`body.appshell-body` remaps ramp to paper).

**Components:** `nav` fixed + `backdrop-filter blur(6px)`, `nav .brand .star` spin 14s, `clip-path` chamfered CTAs, `hazard` diagonal stripes, `cut`/`card` 16px chamfer, `grain` fixed SVG turbulence, `#progress` scaleX bar, `#bugfield` fixed canvas (56 bugs, mouse-repel 130px, click splat), hero crosshair (`#hero` `#xh-v` `#xh-h` `#xh-ring` mousemove, `pointer:fine` only), HUD clock, typewriter `brief-lines` (OPERATION/ COMMANDER etc.), ticker infinite, GSAP ScrollTrigger + Lenis 1.15 (fallback reveals `opacity:1` if CDN down), counters, threat meters.

**Fix scrollbars (PHP bug):** `test.` shows scrollbars because `app.css` shell grid `height:100vh` + `overflow-y:auto` on `.appshell-side` + missing `overflow-x:hidden` on `body.appshell-body`. Go templ restores `styles.css:body{overflow-x:hidden}` and scopes `height:100vh` to desktop only (already in `app.css:@media(max-width:960px)`). Verify no nested `overflow:scroll`.

**Deliver:** `public/assets` → `internal/view/assets` (same filenames for cache parity), `templ` layouts `layouts/main.templ` + `layouts/app.templ` (dark vs light shell).

---

## 4) Unified System (Replace GHL + FieldRoutes + Twilio + Salesforce)

**Goal:** Skyler never opens FieldRoutes, Twilio console, or Salesforce — everything inside `go.patriotpest.pro`.

| Today | Go module | How |
|---|---|---|
| FieldRoutes customers | `modules/customers` + `integrations/fieldroutes` | `sqlc` cache `customers` table, nightly + webhook sync, district WA/AZ encrypted creds in DB (not env), `source='fieldroutes'` |
| Twilio numbers/SMS/voice | `modules/twilio` + `integrations/twilio` | Ported numbers via Twilio API, Lookup v2, `X-Twilio-Signature` verify, unified log `/admin/twilio` |
| GHL inbox | `modules/messaging` **UNIFIED INBOX** (new) | One view merging `messages` + Twilio SMS + Facebook/Instagram replies (add Meta Graph inbox API) — admin sees all threads, `Compliance::isBlocked` gate before send |
| GHL automations | `internal/worker` pool | Worker pool for drip, reactivation, `facebook_leads` → `vtext.com` SMS + email fallback, `recordUnsubscribe` tokens |
| Salesforce | `modules/customers` + `cases` + `audit_log` | `cases` + `tickets` + `audit_log` already in schema; add `opportunities` if needed later |
| Marketing (GHL) | `modules/marketing` | Keep `www` SEO pages, add `alphaflex.net` SaaS later — share `internal/view` + `api` |

**New — Unified Inbox spec (module off by default, flag `unified_inbox`):**
- Sources: `messages` table, Twilio SMS (`webhooks/twilio/sms`), Twilio voice transcription, Facebook/IG comments/DMs (Graph `/{page}/conversations`).
- UI: `/staff/messages` → threaded, filter by channel, `Compliance` block badge, CSRF POST for reply.
- Outbound: `SmsService` → Twilio or `vtext.com` gateway, DNC checked.

---

## 5) MCP / API Compatibility (No Breaking Change)

- Keep `ppc_live_` + 64 hex, `sha256`, `key_prefix` UNIQUE, `hash_equals`, `revoked_at`/`expires_at`, scopes, `API_ENABLED` 404 when off (MASTERPLAN_AI_API §2). Super-admin reuses existing `/admin/api-keys` flow (create `all` vs scoped keys).
- MCP server at `go.patriotpest.pro/mcp` (new) proxies to `internal/modules/api` — agents use same Bearer token to `POST /api/v1/posts` etc. New `post:create` scope.
- Keep `openapi.yaml` versioned; generate Go server via `ogen`.

---

## 6) Data Migration

`migrations/` ports `database/schema.sql` + `database/retention.sql` idempotently. Seed: roles (`super-user` `["all"]`), `SU_SEED_EMAIL` promotion (idempotent, audit `superuser.grant`), staff/customer immmutable guards. Dual-DB retention stays (`storage/retention.sqlite` → `migrations/retention/`).

---

## 7) Deployment — go.patriotpest.pro

- **DNS:** `go.patriotpest.pro` A → VPS (Dokploy), `www` stays Hostinger.
- **Dokploy:** Create app `go-patriot`, expose 80→3000, TLS auto, env from Dokploy secrets (never `.env` commit). Health check `GET /health` (200 JSON `status:ok`). `storage/` volume persisted, `database/` migrated on boot via `migrations` + seed entrypoint idempotence (`.agents/skills/IDEMPOTENT_SEED_ENTRYPOINT`).
- **Docker:** Multi-stage `golang:1.22-alpine` build → `gcr.io/distroless/static:nonroot`, `USER nonroot:nonroot`, `EXPOSE 3000`. No `chmod 777`.
- **CI:** GitHub Actions on `feat/go-rewrite`: `gofmt -s -w .`, `golangci-lint run ./...`, `govulncheck ./...`, `go test -cover ./...`, `templ generate`, `go build ./...`. SCA + lint before deploy.
- **Branch creation (sandbox blocked):** `.git` is RO on this mount. Run locally:
  ```bash
  git fetch origin
  git checkout -b feat/go-rewrite origin/feat/superuser-passwordless-login
  git push -u origin feat/go-rewrite
  ```
  Then `git checkout feat/go-rewrite` and add this file.

---

## 8) Roadmap (Incremental, Each Phase Shippable)

**Phase 0 (Now):** This plan + fix `test.` scrollbars in PHP (one-line CSS), create branch + Dokploy app.
**Phase 1 — Skeleton (Week 1):** `cmd/server`, `config`, `db/sqlc`, `middleware`, `health`, `view` with `styles.css`+`main.js` parity, `go build` green.
**Phase 2 — Auth (Week 2):** `auth` OTP (6/8-digit), `rbac` casbin, `session`, `/login` `/su` parity + `MASTERPLAN_PASSWORDLESS` defenses.
**Phase 3 — Content (Week 3):** `marketing` pages, `pest/{slug}`, `areas/{slug}`, `blog` + RSS, `admin/cms`.
**Phase 4 — Data (Week 4):** `customers`, `fieldroutes` sync, `compliance` gate.
**Phase 5 — Comms (Week 5):** `twilio` webhooks, `facebook` leads, `messaging` inbox, `vtext` SMS.
**Phase 6 — API/MCP (Week 6):** `api/v1` + `openapi.yaml` + MCP proxy, `/admin/api-keys` audit.
**Phase 7 — Hardening:** `retention` beacon, PWA, `govulncheck`, 80% coverage, cutover `go.` → `www`.

---

## 9) Features You Might Be Missing (Add These)

1. **Unified Inbox filters** — per-staff assignment, snooze, private notes (GHL "conversations" parity).
2. **AI reactivation campaigns** — inference on `storage/retention.sqlite` + Twilio Lookup to auto-flag churn, email/SMS with `Compliance` block.
3. **Gameified referral** — `GAMEIFIED_REWARD_REVEAL` skill (scratch/roll) for referrals, already spec'd.
4. **Competitor crawl** — `COMPETITOR_CRAWL_PIPELINE` skill, price once/week.
5. **Hostinger deploy doctrine** — keep `www` Hostinger TUS deploy while Go is VPS; no conflict.
6. **PWA install prompt** — `PWA_INSTALL_PROMPT` skill, already have `manifest.webmanifest` + `sw.js`.
7. **Alphaflex.net reuse** — extract `pkg/validator`, `pkg/geo`, `internal/rbac` as importable for SaaS.

---

## 10) Verification Commands (Vanguard Loop)

```bash
templ generate
gofmt -s -w .
golangci-lint run ./...
go fix ./...
govulncheck ./...
go build ./...
go test -v -cover ./...
go test -fuzz=Fuzz -fuzztime=10s ./...
```

---

*Ingested from:* `public/index.php` routes, `app/Core/*`, `app/Controllers/*`, `public/assets/styles.css`+`app.css`+`main.js`, `database/schema.sql` (570 lines), `PLANS/*`, `.agents/skills/*`, `.env.example`, `Dockerfile`, `openapi.yaml`. Crosshair found in `public/assets/main.js:76` hero crosshair. Scrollbar root cause is `app.css` shell layout, not theme.
