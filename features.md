# Feature Inventory — patriot-pest-go

Status values: Proposed | In-Development | Active | Disabled | Deprecated.
Access tiers: Public | Customer | Admin (internal = backend, no direct tier).

| ID | Name | Status | Module Boundary | Dependencies | Access Tier |
|---|---|---|---|---|---|
| F-01 | Marketing pages (home, blog, search, faqs, contact, service areas, pest catalog) | Active | `internal/modules/marketing`, `internal/view` | SQLite data layer | Public |
| F-02 | Passwordless login — web flow (`/login`, `/login/verify`, `/logout`) | Active | `internal/auth/webflow.go` | staff store, mailer, rate limiter, CSRF cookie | Public (issue) / Customer / Staff |
| F-03 | Auth JSON API (`/api/auth/otp/issue|verify`, magic-link) | Active | `internal/auth/handlers.go` | customers.Lookup | Public (API) |
| F-04 | OTP rate limiting (per-IP login, per-email issue, attempt caps) | Active | `internal/auth/ratelimit.go` | — | Public |
| F-05 | Dev mail log (`storage/logs/mail-*.log`) | Active | `internal/auth/mailer.go` | APP_ENV=local | Admin (debug) |
| F-06 | Staff store (SQLite staff table, seed fallback) | Active | `internal/auth/staffstore.go` | SQLite staff table | Admin |
| F-07 | Customer dashboard | In-Development | `internal/modules/portal` | session cookie, customers.Lookup | Customer |
| F-08 | Staff dashboard | In-Development | legacy stub → dashboard module | session cookie | Admin / Staff |
| F-09 | Admin panel | Active (stub parity) | `internal/modules/admin` | session + rbac | Admin |
| F-10 | Super-user login surface (`/su`) | Disabled | legacy stubs (SUPERUSER_ENABLED=false) | — | Admin |
| F-11 | Customer identity resolution (email / phone / account number → profile) | Active | `internal/modules/customers` + `internal/auth/resolver.go` | FieldRoutes sync | Customer |
| F-12 | FieldRoutes WA + AZ district sync | Active | `internal/fieldroutes`, `internal/modules/customers` | FR API keys | Admin (ops) |
| F-13 | Twilio SMS / Facebook / webhooks | Active (flag-gated) | respective modules | env keys | Customer / Admin |
| F-14 | Legacy 103-route stubs (remaining spec routes) | Active | `internal/modules/legacy` | — | Public |
| F-15 | Dynamic Meta Pixel Injection (Global + Page-Specific paths) | Proposed | `internal/view`, `internal/modules/admin` | SQLite settings | Public / Admin |
| F-16 | Central API Credentials Console (Twilio, Meta, TikTok, Webhooks settings) | In-Development | `internal/config`, `internal/modules/admin` | SQLite settings | Admin |
| F-17 | Unified Social Tab (Slack-style inbox for Facebook/IG DMs, Twilio SMS) | Proposed | `internal/modules/inbox`, `internal/modules/messaging` | SurrealDB threads | Admin |
| F-18 | Super-Admin FieldRoutes Dashboard (Live scheduling + GPS tech routes) | Proposed | `internal/modules/portal`, `internal/fieldroutes` | FieldRoutes API | Admin |
| F-19 | Super-Admin Twilio Call Router & Mass SMS Broadcast Console | Proposed | `internal/modules/twilio`, `internal/modules/compliance` | Twilio API | Admin |
| F-20 | Marketing & Referral Hub (Conversion tracking + $25 reward dispatches) | Proposed | `internal/modules/portal`, `internal/modules/admin` | SQLite data layer | Customer / Admin |

## Notes

- F-02 verified end-to-end 2026-08-26: staff issue → email log → verify → session → dashboard; wrong-code, CSRF-fail, rate-limit, enumeration-defense paths all confirmed.
- F-07/F-08 replace the legacy `ok()` stubs for `/customer-dashboard` and `/staff-dashboard`; keep stubs removed once real handlers land (chi InsertRoute = last registration wins).
- F-15 through F-20 constitute the "Super-Admin Control Room" consolidation (replacing GHL, Twilio Console, and FieldRoutes standalone UI navigation).

