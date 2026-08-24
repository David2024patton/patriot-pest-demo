# Tasks: 001-go-patriot-rewrite — go.patriotpest.pro

**Feature**: `001-go-patriot-rewrite` | **Spec**: [spec.md](spec.md) | **Plan**: [plan.md](plan.md)
**Stack**: Go 1.22, chi, SurrealDB, casbin, templ, ogen | **Host**: `go.patriotpest.pro` independent, `test` untouched

## Phase 0 — Research (0.5d)

- [x] T0.1 Spike SurrealDB Go SDK `surrealdb.go` ws vs embedded, SurrealQL 41 tables, tx pattern, testcontainers — `research.md`
- [x] T0.2 Audit FieldRoutes inventory API — native `supplies` vs sync decision — `research.md`
- [x] T0.3 Map kanbn/kanban schema — boards/columns/cards labels/checklist/WIP — `research.md`
- [x] T0.4 Draft `api/openapi.yaml` scopes `customer:read post:create kanban:read/write inbox:read/write` + 7-channel webhook contracts — `contracts/`

## Phase 1 — Skeleton (1d)

- [x] T1.1 `cmd/server/main.go` chi + graceful, `internal/config` SURREAL_*+all tokens 12-factor, `internal/db` surreal client + `migrations/*.surql`
- [x] T1.2 `internal/middleware` recovery/slog/CORS/HSTS/CSRF/ratelimit/timeout
- [x] T1.3 `internal/view` templ `layouts/main|app` + identical tactical assets `styles.css` `app.css` `main.js` crosshair bugfield (no scrollbars), `configs/feature-flags.yaml`
- [x] T1.4 `internal/modules/health` GET /health (no auth) + /ready (DB ping) + `deploy/Dockerfile` distroless:nonroot
- [x] T1.5 `internal/modules/marketing` 14 routes + pest/area/blog RSS + /cost direct + GEO `llms.txt` JSON-LD

## Phase 2 — Auth + Settings Multi-Key (2d)

- [ ] T2.1 `internal/auth` OTP 6/8 `password_hash` single-use `otp_codes` Surreal, `RateLimiter` login_attempts, `david@itak.live` super-user seed SU_SEED_EMAIL
- [ ] T2.2 `internal/rbac` casbin Customer/Admin/SuperAdmin immutable + per-route ->auth/role/permission (103 routes)
- [ ] T2.3 `internal/modules/portal` aliases /customer-auth etc., GET /customer-dashboard /staff-dashboard /account
- [ ] T2.4 `internal/modules/admin` settings#channels — per-channel FieldRoutes districts `+ Add district` key|token encrypted APP_KEY, Twilio FB X LI n8n Zapier Surreal Mail each edit/rotate/revoke/test + Enabled toggle, hot-reload

## Phase 3 — Portals + Customers + Admin CMS + Staff (2d)

- [ ] T3.1 `modules/customers` + `integrations/fieldroutes` sync wa|az + book/profile /staff/customers /api/customer-search + Salesforce mirror customers/appointments/invoices
- [ ] T3.2 `modules/admin` CMS GET /admin posts/media/content/settings/retention + Staff CRUD GET /admin/staff new/{id}/toggle (super-user blocked)
- [ ] T3.3 Audit `inbox_channel_configs` encryption + audit_log

## Phase 4 — Workflows + Twilio + Compliance (2d)

- [ ] T4.1 `modules/workflows` reactivation_templates→campaigns→sends intervals 0,7,30,60,90 DNC gated, TWILIO_SMS_ENABLED queue until A2P, Mail Titan
- [ ] T4.2 `modules/twilio` suite 15 admin routes + `POST /webhooks/twilio/sms|status|voice|voicemail` HMAC X-Twilio-Signature
- [ ] T4.3 `modules/facebook` GET/POST /webhooks/facebook hub+X-Hub-Signature-256 + facebook_leads fingerprint → vtext.com
- [ ] T4.4 `compliance` DNC is_no_call+unsubscribes + GET /unsubscribe HMAC + outbound webhook_events + POST /webhooks/n8n|zapier HMAC

## Phase 4b — Kanban Shared + Supply (1.5d)

- [ ] T4b.1 `modules/kanban` GET /admin/board shared boards+members `+ Add member` `viewer|editor|admin`, columns WIP, cards `assignee_ids[]`+labels+checklist+cover, drag+`POST /api/kanban/.../move` → `case_timeline`, SSE `/api/kanban/boards/{id}/events`, `@mentions`+`card_comments`→notifications, search/filter
- [ ] T4b.2 `modules/supply` supplies/supply_moves GET /admin/supplies — sync FieldRoutes or native reorder alerts, link kanban+appointments, `SUPPLY_ENABLED`
- [ ] T4b.3 MCP kanban already 6 but members/comments here

## Phase 5 — Inbox 7-Channel (2d)

- [ ] T5.1 `modules/inbox` core GET /staff/messages merges messages+sms_logs+voicemails+social 7 channels each logo badge, single reply Compliance gate
- [ ] T5.2 Adapters `inbox/adapters/{facebook,instagram,twitter,linkedin,twilio,voicemail,email}` Ingest(ctx) Enabled per settings token
- [ ] T5.3 `modules/email` IMAP/SMTP email_threads/messages + mailboxes + compose, visible in unified inbox

## Phase 6 — API/MCP + Kanban Shared (2d)

- [ ] T6.1 `modules/api` ppc_live_ 64hex prefix12 sha256 hash_equals, scopes no customer:delete, 403 on DELETE /api/v1/customers/{id}, audit api.call, ogen openapi + /api/v1/posts
- [ ] T6.2 MCP kanban GET/POST /api/v1/kanban/boards|columns|cards|members|comments via ppc_live_ + per-board viewer|editor, audit

## Phase 7 — Graph RAG + Ask AI + Retention (1.5d)

- [ ] T7.1 `modules/knowledge` kb_nodes embedding MTREE + kb_edges RELATE + kb_memories, POST /api/knowledge/ask RAG graph+vector, Ask AI FAB middle-right streaming
- [ ] T7.2 `modules/retention` POST /api/track/view|event|session_end beacon IP→user stitch, GET /api/retention/summary + /admin/retention settings
- [ ] T7.3 GEO `public/llms.txt` + JSON-LD LocalBusiness/Service/FAQPage per pest/area, docs/competitor-matrix.md top20
- [ ] T7.4 PWA manifest.webmanifest + sw.js, hostinger parity, idempotent seed, `docs/progress` plan satisfied
- [ ] T7.5 Gates: gofmt -s -w . golangci-lint run ./... govulncheck ./... go build ./... go test -v -cover 80% + 103-route registration smoke + visual diff <2%

## Phase 8 — Tech PWA + Messaging + Pokedex (1.5d)

- [ ] T8.1 `modules/tech` GET /tech PWA installable, GET /tech/routes?tech_id=me GPS tech_locations live map, POST /api/customers/{id}/notes with GPS → customer_notes, case→ticket POST /api/cases/{id}/tickets, sw.js offline queue
- [ ] T8.2 `modules/messaging` internal_messages + notifications{read} SSE GET /api/notifications/stream bell in GET /staff/messages + board mentions, push sw.js
- [ ] T8.3 `modules/ai` GET /tech/scan camera → POST /api/ai/scan OCR tesseract/vision pest_identifications → Pokedex card pest_photos{seasonality,treatment,natural} via RAG kb_nodes, settings AI_BASE_URL local http://llm:11434/v1 or external api.openai.com + AI_MODEL/API_KEY fallback
