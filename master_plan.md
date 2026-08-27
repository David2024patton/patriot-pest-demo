# Master Plan — patriot-pest-go (Go rewrite of patriotpest.pro)

Module: `github.com/David2024patton/patriot-pest-go` · Go 1.26 · chi v5 router · SQLite + SurrealDB

---

## Tech Stack Registry

| Layer | Choice | Notes |
|---|---|---|
| Language / Runtime | Go 1.26 (windows/amd64 dev) | stdlib `net/http`; chi v5 mux |
| Frontend | Server-rendered templates + static assets | `internal/view` (html/template), `public/assets/` (styles.css, main.js, admin.css) |
| Auth | Passwordless OTP (email) | `internal/auth`: code store, staff store (SQLite), rate limiter, web flow (`/login`, `/login/verify`, `/logout`) + JSON API (`/api/auth/*`) |
| Database | SQLite + SurrealDB | SQLite content catalog (`database/patriot.db`), SurrealDB for app state / unified inbox |
| Email | SMTP SSL port 465 (`internal/auth/mailer.go`) | Dev mode (APP_ENV=local): logs to `storage/logs/mail-YYYY-MM-DD.log` |
| Integrations | FieldRoutes (WA + AZ districts), Twilio, Facebook, TikTok, LinkedIn | Configured via DB Settings Console (Hot-Reload) |
| Hosting | Docker image (Go binary + baked SQLite) | Deploy path: build / docker-save / scp / swarm-update to go.patriotpest.pro |

---

## Core Pillars & Dashboards (Super-Admin Control Room)

The primary goal of the Go rewrite is **consolidation**. Super-admins must have complete control over every operational channel from a single, unified app shell at `go.patriotpest.pro` without ever having to leave the dashboard.

### 1. Central API Settings & Integrations Console
* **Database-Driven Configs**: All API credentials, tokens, webhooks, and channel configs are stored in the SQLite/SurrealDB settings layer. 
* **Supported Credentials**:
  * **Twilio**: Account SID, Auth Token, API Key, Webhook signing keys, and phone numbers.
  * **Meta (Facebook/Instagram)**: Page Access Tokens, App ID, App Secret, and Webhook verification tokens.
  * **TikTok / LinkedIn**: Lead Form API tokens, developer credentials, and webhook endpoints.
* **Hot-Reloading**: Backend services watch the database settings and dynamically update client connections without restarting the Go application.

### 2. Dynamic Meta Pixel & Tracking Engine
* **Global Injection**: Paste the base Meta Pixel/conversion tracking scripts once in the Super-Admin console to apply it across all public marketing pages.
* **Page-Specific Overrides**: Admins can map specific pixel tracking snippets or conversion event tags (e.g. `PageView`, `Lead`, `Purchase`) to specific paths (e.g. `/r/claim` vs `/r/reward/choose`).

### 3. Unified Social Tab (Slack-Style Inbox)
* **Channel Ingress**: A threaded live chat screen that merges:
  * Facebook Messenger DMs & page comments
  * Instagram Direct messages
  * Twilio SMS, voice transcriptions, and voicemails
  * LinkedIn & Twitter/X direct messages
* **Automatic Bot-Muting**: The automated intake bot handles initial questions. The second a staff member replies in the thread (via the dashboard or Slack bridge), the bot instantly mutes itself (`HumanHandled = true`) so staff can chat 1-on-1.

### 4. Marketing Analytics Hub
* **Attribution Tracker**: Logs marketing sources (`Facebook Page Messenger`, `Instagram Direct`, `TikTok Ad`, `Phone IVR Inbound`, `Website Lead Form`) directly on FieldRoutes customer files.
* **Conversion Tracking**: Tracks ROI, lead funnel metrics, and referrals.
* **Referral Fulfillments**: Manage the neighbor referral program, digital gift card dispatches ($25 Amazon/Visa), and account billing credit approvals.

### 5. Super-Admin FieldRoutes Control Panel
* **Tech Dispatch Map**: Real-time GPS and routing overlay showing tech locations in WA and AZ districts.
* **Scheduling Matrix**: Book, reschedule, or cancel visits directly via FieldRoutes API proxy without opening the FieldRoutes portal.
* **Customer Profile Sync**: Look up profiles, view service histories, flat-rate contracts, and log payment actions.

### 6. Super-Admin Twilio Call Manager
* **Call Routing Console**: Drag-and-drop call flows (IVA, IVR routes, AZ/WA office hours, voicemail greetings, hold music queues).
* **Mass SMS Broadcasts**: Send compliance-checked SMS updates to opted-in customers directly from your database.

---

## Tactical Delivery Phases

1. **Skeleton + content parity** — layout, marketing pages, pest catalog, blog/search seeded from live site. ✅
2. **Auth surface** — OTP login web flow (staff + customer), CSRF, rate limiting, dev mailer, session cookies; legacy stubs removed for `/login`, `/login/verify`, `/logout`. ✅ (smoke-tested end-to-end)
3. **Dashboards** — customer, staff, and admin panels behind the new sessions (admin: overview + people + keys + api-keys sub-screens). ✅ (E2E-verified 2026-08-27)
4. **Deploy + parity verification** — rebuild image, deploy to go.patriotpest.pro, browser-verify against live PHP site, then retire legacy stubs module-by-module.

---

## Key Conventions

- Feature modules self-register routes on the chi router; chi's `InsertRoute` makes the LAST registration win, so legacy stubs must be removed as real handlers land.
- Cookie state (pending login, flash) is base64(JSON) — Go 1.26 strips raw `"` from cookie values.
- Login surfaces pass `AppUI: true` so admin.css loads (parity with PHP `$__appUi`).
- Identity resolution for customer login goes through `auth.SetLookup(customers.Lookup)`; canonical OTP key is the email on file.

---

## System Architecture & Operational Rules

### 1. The 90-Day Cancellation-Save Cooldown Rule
* **The Rule**: Customers who call the IVR or use the portal to cancel can only claim the $25 loyalty save credit once every 3 months (90 days).
* **Implementation**: Store a historical ledger of all retention saves (`retention_saves` table) with timestamps. If a customer attempts to claim it again within the cooldown window, the system automatically redirects them to the "Speak to Manager" queue or displays a direct callback prompt instead of applying the discount.

### 2. Timezone & Same-Day Dispatch Cutoff Logic
* **The Challenge**: Patriot Pest operates in Washington (Pacific Time) and Arizona (Mountain Standard Time - no Daylight Saving).
* **The Rule**: Same-day scheduling availability must dynamically check the time zone of the customer's district and apply a strict same-day dispatch cutoff (e.g., 1:00 PM local time). If they try to book past 1:00 PM local time, the scheduler automatically hides same-day options and defaults to the next morning.

### 3. FieldRoutes API Outage Resilience (Local Caching)
* **The Rule**: The Go dashboards must load instantly even if the FieldRoutes API is down or slow.
* **Implementation**: Maintain an offline-first SQLite cache of active customer profiles, service addresses, and scheduled appointments. The Go app queries this local cache first for instant UI rendering, and updates it asynchronously via background sync workers and FieldRoutes webhook events.

### 4. Zero-Storage Payment Security (PCI Compliance)
* **The Rule**: To keep compliance overhead zero and security absolute, our Go application must **never** ingest, process, or store credit card numbers or bank details on our servers.
* **Implementation**: All payment portals and "Make a Payment" buttons must redirect to or embed FieldRoutes/Stripe-hosted checkout links so payment details are tokenized directly in the customer's browser.

### 5. AI Copilot Integration & Safety Rails
* **The Rule**: If you use local LLMs (like Qwen or Ollama) to draft social media replies or summarize incoming voicemails, there must be strict safety walls.
* **Implementation**: The AI can *never* delete records (the "No Customer Delete" invariant in the MCP API). AI-generated draft responses are presented as *suggestions* in the Unified Social Inbox for staff to review and click "Send," rather than sending auto-replies directly, preventing accidental hallucination slipups.

---

## Salesforce-Killer Dashboard Features

### 1. Global Command Bar (Command-K "Spotlight" Search)
* Floating search bar accessed from anywhere (`Cmd+K` or `Ctrl+K`) to search Customers, Service Tickets, Invoices, or Messages with keyboard navigation.

### 2. Customer 360 Interactive Timeline
* Chronological unified feed on the customer's profile combining call logs, text bubbles, emails, invoices, technician statuses, and bot interactions.

### 3. "Quick Action" Timeline Sidebar
* Expanded sidebar on the timeline to log a call, send an SMS, compile an email, or trigger a callback in 1-click using HTMX.

### 4. Interactive Kanban Pipelines
* Drag-and-drop boards to track leads and collections. Card transitions trigger automated notifications (e.g. dragging to "Scheduled" texts confirmation).

### 5. Server-Sent Events (SSE) Live Feed HUD
* Live tactical slide-out notifications on the dashboard for incoming calls, customer callbacks, or social messages with audio cues.

---

## 20 Consolidated Operational Expansion Ideas

### Operations & Dispatch
1. **Smart Route Cluster Map**: Geographic auto-clustering to minimize drive times for WA and AZ technicians.
2. **Timezone-Aware Cutoff Enforcement**: Strict same-day cutoff (1:00 PM local district time) dynamically applied to booking portals.
3. **Weather-Adaptive Scheduler**: Dynamic warning overlay for exterior appointments affected by heavy rain or Phoenix extreme heat.
4. **Google Maps Place Autocomplete**: Prevents address typos on manual entries using API lookups.
5. **Auto-Stitch Duplicate Accounts**: Automatic merging recommendation widget for matching phones/emails.

### Communication & Inbox
6. **AI Call & Voicemail Summaries**: Direct Twilio voice transcriptions compressed into single-sentence summaries.
7. **Canned Template Shortcodes**: Quick key triggers (like `;onmyway` or `;delayed`) to pre-fill customized messages.
8. **Twilio Call Whisper & Barging**: Allows managers to silently listen to live calls, whisper tips, or barge in to resolve disputes.
9. **Website Live Chat Widget**: Integrates a live chat window on `patriotpest.pro` directly into the Social Inbox.
10. **After-Hours Lead Alert Cascade**: Cascade SMS sequence alerting coordinators of overnight leads until claimed.

### Customers & Field Techs
11. **Bug-Photo Upload Portal**: Customers upload photos of active infestations directly to their booking requests.
12. **AI Pest Identifier (Vision)**: Integrates local vision LLM processing on photos to identify pest species for technician prep.
13. **Downloadable Mobile Field Service App**: A dedicated, downloadable native application (compiled using Wails or a Capacitor PWA wrapper) for technicians in the field, modeled after professional field service dispatch apps. Includes offline-first route maps, client service histories, material logs, and photo uploads.
14. **Mobile E-Sign Contracts**: Send customer agreements directly via SMS for on-site mobile signature.
15. **Google Review Booster**: 5-star visit feedback redirects to Google Business; low scores trigger coordinator callbacks.

### Sales & Marketing ROI
16. **Interactive USA Customer Density Heatmap**: An interactive visual map of the United States (using Leaflet.js/OpenStreetMap or Google Maps API) displaying customer distribution, hot leads, and churn patterns. Admins can zoom from a national level down to specific neighborhoods in Spokane/Phoenix to target local marketing and flyer drops.
17. **Seasonal Pest Forecast Matrix**: Predicts wasp, spider, and rodent spikes based on year-over-year ticket data.
18. **Instant SMS Payment Links**: In-chat link generator allowing card/Apple Pay checkout in 30 seconds.
19. **Technician Sales Leaderboard**: Gamifies upsell values, review scores, and chemical efficiencies to drive competition.
20. **Digital Referral Cashout**: Dynamic tracking of referral credits with automatic Visa card generation.

---

## STILL TO DO — Remaining Work Checklist

> Tracks every outstanding task across the Go rewrite and IVR/retention system. Check off as completed.

---

### PHASE 1 — Legacy Page Migrations (from test.patriotpest.pro)

Still stubbed in `internal/modules/legacy/legacy.go` — each needs a real Go handler + template.

- [ ] `/cost` — Service pricing calculator page (real interactive UI)
- [ ] `/customer-portal` — Customer hub landing alias
- [ ] `/customer/invoices/{id}/download` — Stream invoice PDF from FieldRoutes API
- [ ] `/customer/messages` — Customer-facing messaging thread UI
- [ ] `POST /api/customer/messages` — Customer message submit (feeds Unified Inbox)
- [ ] `/customer-auth` / `/customer-verify` — Legacy auth aliases (redirect to `/login`)
- [ ] `/staff` / `/staff-verify` / `/staff-logout` — Legacy staff aliases
- [ ] `/dashboard` — Legacy dashboard alias (redirect based on role)
- [ ] `/account` — Customer profile / account settings page
- [ ] `/admin/posts/new` + `/admin/posts/{id}` — Blog post creator and editor (CMS)
- [ ] `/admin/settings POST` — General site settings save handler
- [ ] `/admin/staff/new` + `/admin/staff/{id}` — Add and edit staff profiles / roles
- [ ] `/admin/api-keys/audit` — API key usage audit log screen
- [ ] `/admin/api-keys/{id}/revoke|rotate|scopes` — Key lifecycle controls
- [ ] `/admin/retention/settings POST` — Save retention engine settings
- [ ] `/admin/fieldroutes` — FieldRoutes health dashboard (WA vs AZ district sync status)
- [ ] `/tech/ask` — Field tech AI copilot search UI
- [ ] `/su` + `/su/verify` — Super-user master login flow (when `SUPERUSER_ENABLED=true`)

---

### PHASE 2 — Salesforce-Killer Dashboard Features (New Builds)

- [ ] **Global Command Bar (Ctrl+K)** — Spotlight-style floating modal to jump to any customer, ticket, or message
- [ ] **Customer 360 Timeline** — Unified feed on each customer profile: calls, texts, emails, visits, invoices
- [ ] **Sameday AI Receptionist & Call Coach** — 24/7 AI CSR voice/chat receptionist + automatic call outcome scoring
- [ ] **USA Customer Density Heatmap** — Leaflet.js map of customer locations across WA and AZ with drill-down
- [ ] **Kanban Pipeline Boards** — Lead and Collections drag-and-drop boards; card moves trigger SMS/email
- [ ] **SSE Live Feed HUD** — Real-time sidebar notifications for incoming calls, callbacks, and social messages
- [ ] **Central API Settings Console** — Hot-reload SQLite UI for Twilio, Meta, TikTok, LinkedIn, Webhook keys
- [ ] **Meta Pixel Admin Console** — Set pixel ID globally or per-page from the dashboard (no redeploy needed)
- [ ] **Unified Social Inbox** — Merge Facebook, Instagram, TikTok leads, website chat, SMS, voicemails into one threaded inbox

---

### PHASE 3 — IVR / Twilio / Calling Ads (e:\hi / test.patriotpest.pro)

- [ ] **Calling Ads CAPI** — Verify Conversions API Gateway fires `Lead` events to Meta for every call from a calling ad
- [ ] **Retention beacon** — Confirm first-party SQLite beacon captures sessions and admin summary endpoint returns data
- [ ] **IVR hold-music finalization** — Confirm final approved queue ads/audio files are live in the Twilio Flow
- [ ] **Messenger bot mute** — Verify bot auto-mutes on keywords and unmutes after 15 minutes
- [ ] **Facebook Pixel on IVR pages** — Confirm pixel fires on `/referral`, `/reward-choice`, `/optout` (deployed commit `f0a08e0`)
- [ ] **Twilio Call Whisper / Barge** — Supervisor monitor, whisper, and barge-in controls in staff dashboard

---

### PHASE 4 — Downloadable Native App (Wails)

- [ ] **Wails scaffold** — Initialize Wails project wrapping the Go backend as a native downloadable shell
- [ ] **Tech Routing Screen** — Map-based daily route view with stop ordering, client info, check-in/checkout
- [ ] **Offline-First Sync** — Service notes, photos, material logs written locally and synced when online
- [ ] **Mobile E-Sign** — Customer agreement signature capture from the tech's device
- [ ] **Field Tech AI Copilot** — Wire `/tech/ask` to local Qwen model for treatment guide lookups in the field

---

### PHASE 5 — Infrastructure & DevOps

- [ ] **go.patriotpest.pro live deploy** — Push Go binary Docker image to VPS and confirm live traffic
- [ ] **golangci-lint CI** — Add linter to GitHub Actions so every PR is checked
- [ ] **Race detector in CI** — Enable `CGO_ENABLED=1` on the Actions runner so `-race` tests run on every push
- [ ] **Automated DB migrations** — Replace manual SQLite edits with versioned `goose`/`golang-migrate` runner
- [ ] **FieldRoutes offline cache** — Local SQLite cache of FR customer/service data for when the FR API is down
- [ ] **PCI compliance audit** — Walk every payment touchpoint and confirm zero card data stored on our servers
