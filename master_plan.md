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
3. **Dashboards** — customer dashboard + staff dashboard app shells behind the new sessions; admin panel wiring. ▶ in progress
4. **Deploy + parity verification** — rebuild image, deploy to go.patriotpest.pro, browser-verify against live PHP site, then retire legacy stubs module-by-module.

---

## Key Conventions

- Feature modules self-register routes on the chi router; chi's `InsertRoute` makes the LAST registration win, so legacy stubs must be removed as real handlers land.
- Cookie state (pending login, flash) is base64(JSON) — Go 1.26 strips raw `"` from cookie values.
- Login surfaces pass `AppUI: true` so admin.css loads (parity with PHP `$__appUi`).
- Identity resolution for customer login goes through `auth.SetLookup(customers.Lookup)`; canonical OTP key is the email on file.
