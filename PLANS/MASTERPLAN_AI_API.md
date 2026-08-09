# Masterplan: AI/Agent API Layer (Part E of Front One)

## Architecture

### Layer separation (Cybersecurity constraint: separate namespaces)
```
/api/v1/*          Authenticated, bearer-token-gated. Write + read with scopes.
/public/data/*     Unauthenticated, read-only. No bearer checks. Rate-limited by IP only.
/health             Unauthenticated health check. Always available.
```

External agents call `/api/v1/*` with `Authorization: Bearer ppc_live_<random>`. The public surface (`/public/data/*`) is the no-key default — a request to `/api/v1/*` must never fall through to a public route and vice versa.

### Flow
1. Request hits `/api/v1/*`
2. `API_ENABLED` env check → 404 if off (mirroring COST_DASHBOARD_ENABLED pattern)
3. Extract `Authorization: Bearer <token>`
4. Lookup by key prefix (public id, stored in `api_keys.key_prefix`)
5. Hash presented token with SHA-256, compare constant-time against `api_keys.key_hash`
6. Check `revoked_at IS NULL` and `expires_at IS NULL OR expires_at > now()`
7. Rate limit per key + per IP (reuse `RateLimiter`)
8. Check required scope against key's scopes JSON
9. Audit-log the call
10. Execute handler (same Router dispatch, separate route group)

### RBAC on endpoints
Admin API key management routes (`/admin/api-keys/*`) use the existing `->auth('staff')->role('admin')` guard pattern. API key scopes control what `/api/v1/*` endpoints can be called.

## Database: api_keys table
- `id` INTEGER PK AUTOINCREMENT
- `name` TEXT NOT NULL (human label)
- `key_prefix` TEXT NOT NULL UNIQUE (public id, first 12 chars of raw key)
- `key_hash` TEXT NOT NULL (SHA-256 of the full raw key)
- `scopes` TEXT NOT NULL DEFAULT '[]' (JSON array)
- `created_by` INTEGER REFERENCES staff(id)
- `last_used_at` TEXT
- `expires_at` TEXT
- `revoked_at` TEXT
- `created_at` TEXT NOT NULL DEFAULT (datetime('now'))

Key generation: `bin2hex(random_bytes(32))` → `ppc_live_` + 64 hex chars. Prefix = first 12 chars after `ppc_live_`. Raw key shown once at creation, never stored.

## Endpoints

### /api/v1/health
Scope: none (always available when API_ENABLED=true)
Returns: {status, version, time}

### /api/v1/customers
Scope: `customer:read`, `customer:read-full` (full includes phone, email)
Params: ?q=, ?status=, ?page=, ?limit=
Returns paginated customer list

### /api/v1/customers/{id}
Scope: `customer:read` or `customer:read-full`
Returns single customer record

### /api/v1/tickets
Scope: `ticket:read`
Params: ?customer_id=, ?status=, ?page=
Returns tickets

### /api/v1/messages
Scope: `message:read`
Params: ?customer_id=, ?page=
Returns messages

### /api/v1/services
Scope: none (public data)
Returns pest/service catalog

### /api/v1/twilio/logs
Scope: `twilio:read` (admin scope)
Params: ?type=sms|call|voicemail, ?phone=, ?page=

### /api/v1/staff
Scope: `staff:read` (admin scope)
Returns staff list (roles, active status; no email)

## OpenAPI Spec
`openapi.yaml` at repo root. Self-describing surface with all endpoints, auth requirements, scopes, error codes.

## Feature Toggle
`API_ENABLED=true|false` in `.env`. When false, all `/api/v1/*` returns 404.

## Verification checklist (QA 8-point contract)
1. Bad key → 401, no info leak
2. Missing key → 401
3. Expired key → 401
4. Revoked key → 401
5. Scope denial → 403
6. Rate limit → 429, per-key isolation
7. Credential scan clean
8. Full test suite green

## Cybersecurity bar (QA deep review)
- SHA-256 at rest, constant-time compare
- Crypto-random 32+ byte, ppc_live_ prefix, shown once
- PII redaction unless customer:read-full
- 404 when API_ENABLED off
- Audit logging on every authenticated call
- OpenAPI matches actual routes/scopes/error codes
