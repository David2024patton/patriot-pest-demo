# PLAN.md — Patriot Pest Control: Own Your Stack

> **The mission:** Kill the ~$300/mo GoHighLevel bill by running everything we need
> on `patriotpest.pro` — our own Twilio + FieldRoutes + social + email control center.
> One login, dashboards for every channel, a single unified inbox.
> Last updated: 2026-08-12

---

## 1. Why we're building this

- GoHighLevel was costing **$300+/mo** and we still didn't own the data or the code.
- Numbers were ported to **Twilio**; FieldRoutes gives us **API + SDK access**.
- Instead of tab-hopping between GoHighLevel / Twilio / FieldRoutes / Facebook /
  Google, we log into **patriotpest.pro** and see everything in one place.
- The site already IS a CRM (customers, tickets, messages, RBAC, portals) — this
  plan turns it into the full replacement.

## 2. What already exists (verified 2026-08-12)

| Area | Status |
|---|---|
| Passwordless email-OTP login (staff + customers) | ✅ live |
| Superuser `/su` surface (8-digit codes, isolated) | ✅ implemented, toggle off by default |
| RBAC admin CMS (posts, media, roles, settings) | ✅ live |
| Twilio ops console (SMS, calls, voicemails, webhooks) | ✅ live |
| FieldRoutes sync (customers, subscriptions, appointments, invoices, payments) | ✅ live |
| Facebook Lead Ads webhook → SMS pipeline | ✅ live |
| AI/Agent API (`/api/v1/*`, bearer keys, scopes, rate limits, audit) | ✅ live, 33/33 tests pass |
| Customer portal + staff dashboard | ✅ live |
| PWA (installable) + analytics (GA4, Google Ads) | ✅ live |
| Unified inbox | ❌ **next big build** |

## 3. Build order

### Phase A — Fix what's broken NOW (in progress)
- [x] Mobile: top nav hidden, bottom sticky nav is the only nav
- [x] Mobile: page actually scrolls (Lenis disabled on touch — native scroll)
- [x] Desktop: threat board is a grid, no more side-scroll marathon
- [x] Crosshair HUD restored page-wide on desktop (Skyler-approved)
- [ ] Deploy + verify on test.patriotpest.pro

### Phase B — Unified Inbox (the GoHighLevel killer) ⭐
Single screen showing every conversation, newest first, with unread badges:

- **SMS/MMS** (Twilio) — send/reply, threads by customer
- **Calls + voicemails** (Twilio) — transcript/audio playback, call-back button
- **Email** (Titan SMTP/IMAP) — send/receive via the same thread view
- **Facebook/IG DMs** (Graph API) — inbox for page messages
- **Google Business Messages** (optional, later)
- Rules: auto-tag by source, unread counter, customer profile side panel,
  reply-as (staff member), assignment, notes
- Existing pieces: `messages`, `sms_logs`, `call_logs`, `voicemails`, `webhook_events`
  tables are already in the schema — the inbox is a read/write surface over them.

### Phase C — Control-Center Dashboards (replace the vendor sites)
- **Twilio Dashboard**: spend, message/call volume, DNC hits, delivery failures,
  per-number stats (we already have TwilioController + logs)
- **FieldRoutes Dashboard**: sync health, district split (WA/AZ), subscription
  churn, next-service radar, invoice aging (already cached in DB)
- **Cost Dashboard**: per-channel CAC + ROI (plan exists:
  `COST_DASHBOARD_MASTERPLAN.md`)
- **Social Dashboard**: FB lead count, ad spend hooks, DM load
- **Analytics Dashboard**: GA4 + Ads + Clarity rollup, weekly audit report
  (`templates/admin/audit.php` is written but NOT wired — wire it in this phase)

### Phase D — Marketing machine (from PROJECT.md, all currently unchecked)
1. **Tracking**: GA4 (done), Facebook Pixel, Google Ads conversion, Clarity, baseline dashboard
2. **Site fixes**: blog repair, testimonials, data gap audit, mobile QA
3. **Conversion**: hero CTA + form, pricing clarity, trust signals (reviews,
   certifications, veteran story), A/B framework
4. **Content/SEO**: 8 pest service pages, 15 city/area pages, seasonal blog
   calendar, technical SEO, local SEO (GBP)
5. **Amplification**: Google Ads launch, FB/IG retargeting, SMS nurture (Twilio),
   email automation (Titan), referral program

### Phase E — Hardening & scale
- Contact form: persist + notify staff + auto-reply (currently a stub TODO)
- Unified inbox permissions (who can see/reply per channel)
- Webhook replay + delivery health monitor
- Backup/restore runbook for the SQLite DB + media

## 4. Infra & blockers (from PATRIOT_PEST_DEPLOYMENT.md)
- **Hostinger API**: Cloudflare 1016 on all endpoints → **David to regenerate token**
- **Dokploy**: verify credentials at dashboard.itak.live
- **Docker on the desktop**: WSL config keys invalid, daemon not starting (local only)
- Deploys use `deploy/hostinger-deploy.sh` / `hostinger-tus-upload.py` (README in repo)

## 5. Guardrails
- **No secrets in git** — everything lives in `.env` / `E:\global.env`
- API keys hashed (SHA-256) at rest, shown once
- DLP: SSN/credit-card blocks, API-key/PII redaction (already in shield + audit)
- Superuser immutable from staff CRUD; `/su` only path
- Every build ships tests (33 passing now — keep it green)

## 6. Definition of done
- Test suite green
- No credentials in commits
- Mobile scroll + bottom nav verified on a real phone
- Unified inbox handles SMS + calls + voicemails + email end-to-end
- David can log into patriotpest.pro and see every channel without opening
  Twilio, FieldRoutes, GoHighLevel, or Meta
