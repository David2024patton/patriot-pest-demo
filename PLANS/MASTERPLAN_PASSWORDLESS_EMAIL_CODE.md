# Masterplan: Superuser + Passwordless Email-Code Login

## Context (what exists)

The app already has **passwordless email-OTP login for everyone** (`AuthController`, `OtpAuth`, `Mailer`). There are no passwords anywhere. When a user enters an identifier (email/phone/account), the system matches staff first (by email), then customers, issues a 6-digit code hashed with `password_hash()`, emails it, verifies it with `hash_equals()`, and opens a role-routed session. The existing admin role has `["all"]` permissions and gates every admin route via `->auth("staff")->role("admin")`.

This masterplan is **not** building passwordless login from scratch. It is adding a **dedicated, elevated superuser surface** that is toggleable, isolated from the standard login flow, and carries stronger security guarantees.

## What DavidPatton asked for

1. `david@itak.net` becomes a superuser -- a tier above admin
2. When logging in, no password -- enter email, get an emailed code, enter the code, get in
3. "Secure 2nd auth" -- the emailed code IS the second factor (possession of the inbox)

## Architecture

### Standard login isolation (BLOCKER 1 FIX -- Cybersecurity review)

The superuser account MUST NOT be reachable through the standard `/login` flow. The dedicated `/su` surface is the sole path. Two defenses:

**Defense 1: loginRequest() exclusion.** Add `AND role != 'super-user'` to the staff lookup in `AuthController::loginRequest()`:

```php
$staff = $db->fetch(
    "SELECT id, email, name, role FROM staff WHERE email = ? AND active = 1 AND role != 'super-user'",
    [$email]
);
```

Without this filter, the seeded super-user row matches the standard staff lookup, the system issues a 6-digit code (600s TTL, 5 attempts), and `startStaffSession()` sets `staff_role='super-user'`, routing to `/admin`. The super-user is reachable under the materially weaker standard controls -- 6 vs 8 digits (~91x fewer guesses), 5 vs 3 attempts, 2x the window -- and the `/su` hardening is bypassable even when the toggle is OFF.

**Defense 2: startStaffSession() guard (defense in depth).** At the top of `startStaffSession()`, fail closed if the resolved row has role `'super-user'`:

```php
if (($row['role'] ?? '') === 'super-user') {
    throw new \RuntimeException('Super-user must authenticate via /su surface only.');
}
```

This ensures that even if a future code change accidentally removes the SQL filter, the session is never established for a super-user through the standard path. The exception is logged and the user sees a generic error.

**Phpunit:** `test_superuser_blocked_from_standard_login` -- loginRequest() with super-user email returns null (no code issued). `test_superuser_session_guard_denies_superuser` -- startStaffSession() with role='super-user' throws RuntimeException.

### Layer separation

```
/su              Superuser login surface (toggleable -- separate from /login)
/admin/*         Admin CMS (unchanged, existing admin guards)
/login           Standard unified login (unchanged, existing)
```

The superuser surface is a **parallel auth path**, not a modification to the existing `/login`. If `SUPERUSER_ENABLED=false`, `/su` and `/su/verify` return 404 and zero superuser routes register. The standard login is never affected.

### Flow

1. User navigates to `/su` -- a dedicated superuser login page (single email field, no identifier matching)
2. User enters email -- POST `/su`
3. Email validated with `filter_var($email, FILTER_VALIDATE_EMAIL)` -- reject CR/LF and non-email input before any DB lookup
4. System looks up the email in the `staff` table where `role = 'super-user'` AND `active = 1`
5. If match: issues a code, emails it, redirects to `/su/verify` (code entry)
6. On `/su/verify`: user enters code -- POST `/su/verify`
7. Code verified -- session established with `staff_role = 'super-user'`
8. Redirected to `/admin` (the CMS)

Mismatch behavior: if the email does not belong to a super-user, the system shows the same "code sent" screen (enumeration defense), but no code is issued. Verification will fail, and the user sees "code incorrect."

### Why a separate surface

| Property | Standard `/login` | Superuser `/su` |
|---|---|---|
| Identifier matching | Matches staff by email, then customers by email/phone/account | Matches ONLY super-user staff by email |
| Email validation | Loose (trim, max:120) | Strict (FILTER_VALIDATE_EMAIL, CR/LF rejected) |
| Enumeration defense | Shows "code sent" for no-match (same defense) | Same |
| Rate limit (request) | 5/min per IP + 3/5min per identity | 3/min per IP + 2/5min per identity (tighter) |
| OTP length | 6 digits | 8 digits |
| OTP TTL | OTP_TTL (default 600s) | SU_OTP_TTL (default 300s, shorter) |
| Max verify attempts | OTP_MAX_ATTEMPTS (default 5) | 3 (tighter, hardcoded) |
| Session idle | SESSION_LIFETIME_STAFF (7200s) | 3600s (shorter for superuser) |
| Feature toggle | None (always on) | SUPERUSER_ENABLED (default false) |
| Dev mail log gate | Config::isLocal() only | Same -- staging host has APP_ENV != local so codes never written to disk |

## Database changes

### New role: super-user

```sql
INSERT OR IGNORE INTO roles (role, label, permissions) VALUES
    ('super-user', 'Super User', '["all"]');
```

The `"all"` permission already exists on the admin role and grants bypass in `Session::hasPermission()`. The super-user role carries the same permission set (`"all"`) but is an **immutable role** -- the staff CRUD cannot change a super-user's role field or deactivate them.

### Seed migration (idempotent, run at startup by Database.php)

The seed reads `SU_SEED_EMAIL` from config. If the staff row exists with a different role, it is promoted to `'super-user'` via UPDATE. If it does not exist, it is INSERTed with name 'David Patton' and role `'super-user'`. The grant is audit-logged:

```php
$db->insert('audit_log', [
    'user_id'   => null,           // system
    'user_type' => 'system',
    'action'    => 'superuser.grant',
    'entity'    => 'staff',
    'entity_id' => (string) $staffId,
    'meta_json' => json_encode(['email' => $email, 'role' => 'super-user']),
    'ip'        => RateLimiter::clientIp(),
    'created_at' => date('Y-m-d H:i:s'),
]);
```

The grant is fully idempotent across three scenarios:

1. **Empty `SU_SEED_EMAIL`:** If `Config::get('SU_SEED_EMAIL')` returns null or empty string, the seed migration exits immediately with zero queries. No staff row is created, no audit log written. This is the default behavior when `.env` omits the variable -- superuser surface can be toggled on later by setting the env var and restarting.
2. **Already-promoted super-user:** If the staff row already has `role = 'super-user'`, the migration is a no-op -- no UPDATE, no audit row. The check `SELECT role FROM staff WHERE email = ?` returns `'super-user'` and the migration skips.
3. **New promotion:** If the staff row exists with a non-superuser role, it is UPDATEd to `'super-user'` and exactly one `audit_log` row is inserted with `action = 'superuser.grant'`. Re-running the migration after this hits scenario 2 (no-op).

Implementation sketch:

```php
$email = Config::get('SU_SEED_EMAIL');
if (!$email || trim($email) === '') {
    return; // No-op on empty config
}

$existing = $db->fetch("SELECT id, role FROM staff WHERE email = ?", [$email]);
if (!$existing) {
    // INSERT new row
    $db->insert('staff', [...]);
    $this->auditGrant($staffId, $email);
} elseif ($existing['role'] !== 'super-user') {
    // UPDATE existing to super-user
    $db->execute("UPDATE staff SET role = 'super-user' WHERE id = ?", [$existing['id']]);
    $this->auditGrant($existing['id'], $email);
}
// else: already super-user, no-op
```

### New env vars in .env.example

```
# --- Superuser login (dedicated elevated surface) ---
# Toggleable: when false, /su and /su/verify return 404.
SUPERUSER_ENABLED=false
# OTP validity for superuser codes (seconds, shorter than staff default).
SU_OTP_TTL=300
# Seed email -- the account promoted to super-user on first migration.
SU_SEED_EMAIL=david@itak.net
```

## Route registration (public/index.php)

```php
// ---------- Superuser login (toggleable, elevated surface) ----------
if (\PPC\Core\Config::bool("SUPERUSER_ENABLED", false)) {
    Router::get("/su",             [AuthController::class, "superLoginForm"]);
    Router::post("/su",            [AuthController::class, "superLoginRequest"]);
    Router::get("/su/verify",      [AuthController::class, "superLoginVerifyForm"]);
    Router::post("/su/verify",     [AuthController::class, "superLoginVerify"]);
}
```

Four routes, four new controller methods, one new template (`templates/auth/super-login.php`). The verify form reuses `templates/auth/verify.php` with `purpose = 'super-login'`.

## Controller changes (AuthController.php)

Four new methods:

### `superLoginForm()` -- GET /su
- If already authenticated as super-user, redirect to /admin
- Renders `auth/super-login` (email-only form)

### `superLoginRequest()` -- POST /su
- CSRF verify
- Validate email with `filter_var($email, FILTER_VALIDATE_EMAIL)`, reject CR/LF, max 254 chars
- Rate limit: 3/min per IP via `RateLimiter::clientIp()` (XFF-aware, tighter than standard)
- Look up staff where email = ? AND role = 'super-user' AND active = 1
- On match: `issueAndEmailSuper($email)` -- 8-digit code, `SU_OTP_TTL`, purpose `'super-login'`
- Stash `pending_login_email`, `pending_login_type = 'staff'`, `pending_login_id` in session
- Flash "code sent"
- On mismatch: same flash, no code issued (enumeration defense)
- Redirect to `/su/verify`

### `superLoginVerify()` -- POST /su/verify
- CSRF verify
- Read pending_login state from session
- `OtpAuth::verify($email, 'super-login', $code)` -- purpose isolation
- On success: `startSuperSession($email)` -- same pattern as `startStaffSession()` but:
  - Role check: enforces `role = 'super-user'` (fail closed)
  - Session regenerate (fixation defense)
  - Session put: `staff_role = 'super-user'`
  - Audit log: `action = 'super-login'`, `ip` via `RateLimiter::clientIp()`
  - Redirect to `/admin`
- On failure: same error handling as existing flow

### Helper: `issueAndEmailSuper(string $email)`
- Rate limit: 2 codes per identity per 5 minutes (tighter)
- `OtpAuth::issue($email, 'super-login', 8)` -- 8-digit code
- TTL from `Config::int('SU_OTP_TTL', 300)`
- `Mailer::send()` with branded template
- Dev mail log gated by `Config::isLocal()` (existing behavior, no change)

## OtpAuth changes

Add optional `$length` parameter (default 6 for backward compat):

```php
public static function issue(string $identity, string $purpose, int $length = 6): string
{
    $min = 10 ** ($length - 1);
    $max = (10 ** $length) - 1;
    $code = (string) random_int($min, $max);
    // ... hash + insert as before
}
```

Superuser calls pass `$length = 8`. All existing calls default to 6 (zero behavior change). `verify()` is unchanged.

**IP attribution fix (Cybersecurity Note 1):** `OtpAuth::issue()` currently records `$_SERVER['REMOTE_ADDR']` (the proxy IP behind Dokploy). For accurate attribution, change the `ip` column insert to use `RateLimiter::clientIp()` (XFF-aware):

```php
// In OtpAuth::issue(), change:
'ip' => RateLimiter::clientIp(),
```

This is a one-line fix with zero behavioral change beyond IP accuracy. Enforcement already uses `clientIp()` via `RateLimiter`. Applies to all OTP purposes, not just super-login, fixing the proxy-blind spot globally.

## Session changes

```php
public static function isSuperUser(): bool
{
    return self::staffRole() === 'super-user';
}

public static function isAdmin(): bool
{
    return self::staffRole() === 'admin' || self::staffRole() === 'super-user';
}
```

This makes **all** admin-guarded routes available to super-users with zero route changes. One fix needed in `AuthController::dashboardFor()`:

```php
return ($role === 'admin' || $role === 'super-user') ? '/admin' : '/staff-dashboard';
```

### enforceIdleTimeout() superuser specialization (Cybersecurity Note 2)

The existing `Session::enforceIdleTimeout()` gives all staff the same 7200s idle timeout. Special-case super-user for a stricter 3600s idle window:

```php
public static function enforceIdleTimeout(): void
{
    if (!self::isStaff()) return;

    $lifetime = (self::staffRole() === 'super-user') ? 3600 : self::SESSION_LIFETIME_STAFF;
    $lastActivity = $_SESSION['last_activity'] ?? 0;

    if (time() - $lastActivity > $lifetime) {
        self::logout();
        header('Location: /login?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}
```

Superuser sessions expire after 3600s idle (half the standard 7200s). The `last_activity` key is bumped on every authenticated request via router middleware, same as today.

## Immutability protections

The staff CRUD must refuse to modify a super-user:

**staffUpdate() guard:**
```php
$target = $db->fetch('SELECT role FROM staff WHERE id = ?', [$id]);
if ($target['role'] === 'super-user') {
    Session::flash('staff', ['error' => 'Super-user accounts cannot be modified.']);
    header('Location: /admin/staff');
    exit;
}
```

**staffToggle() guard:**
```php
$target = $db->fetch('SELECT role FROM staff WHERE id = ?', [$id]);
if ($target['role'] === 'super-user') {
    Session::flash('staff', ['error' => 'Super-user accounts cannot be deactivated.']);
    header('Location: /admin/staff');
    exit;
}
```

## Mapping to Cybersecurity Security Bar

| Bar | Requirement | Implementation |
|---|---|---|
| 1 | Code entropy | 8-digit CSPRNG via `random_int(10**7, 10**8-1)`, ~23 bits |
| 2 | Code at rest | `password_hash()` via existing `OtpAuth::issue()` |
| 3 | Constant-time verify | `password_verify()` + `hash_equals()` via existing `OtpAuth::verify()` |
| 4 | Single-use | `used_at` consumed on success; new issue invalidates prior unused codes |
| 5 | Expiry | `SU_OTP_TTL` 300s default, hard expiry in verify SQL |
| 6 | Rate limit (issue) | 2 codes per identity per 5 min + 3 per IP per min |
| 7 | Rate limit (verify) | 3 attempts per identity per TTL window |
| 8 | Session fixation | `Session::regenerate()` on login (existing, reused) |
| 9 | Session idle timeout | 3600s for superuser (vs 7200s staff) |
| 10 | Enumeration defense | Same "code sent" flash for no-match; no timing difference |
| 11 | Superuser immutability | Staff CRUD cannot change role or deactivate |
| 12 | No secrets in repo | `SU_SEED_EMAIL` from .env (git-ignored) |
| 13 | Feature toggle | `SUPERUSER_ENABLED=false` -- routes never register, 404 |
| 14 | CSRF | All POST routes: `Csrf::verifyOrDie()` |
| 15 | Email header injection | `filter_var(FILTER_VALIDATE_EMAIL)` + CR/LF rejection before any send; Mailer uses fixed headers |
| 16 | Auth audit trail | `audit_log` rows for `superuser.grant`, `super-login`, `logout` with `RateLimiter::clientIp()` |
| 17 | Purpose isolation | `'super-login'` purpose; standard `'login'` codes cannot cross-authenticate |
| 18 | Dev mail log gate | `Config::isLocal()` only (existing); staging APP_ENV != local |
| 19 | Grant audit trail | `audit_log` row for seed grant with `user_type='system'`, `action='superuser.grant'` |
| 20 | Role equivalence | `isAdmin()` returns true for super-user; all guard chains, `hasPermission()`, and `dashboardFor()` handle it |

## Phpunit coverage

| Test | What it asserts |
|---|---|
| `test_superuser_role_seeded` | roles table has 'super-user' with ["all"] |
| `test_superuser_email_seeded` | staff row exists for SU_SEED_EMAIL |
| `test_superuser_grant_audit_logged` | audit_log row for superuser.grant |
| `test_superuser_code_entropy` | 8-digit, within [10000000, 99999999] |
| `test_superuser_code_single_use` | code consumed, second verify fails |
| `test_superuser_code_expiry` | expired code rejected |
| `test_superuser_purpose_isolation` | 'login' code fails 'super-login' verify |
| `test_superuser_rate_limit_verify` | 3 attempts locks out |
| `test_superuser_immutable_role` | Staff CRUD update rejected |
| `test_superuser_immutable_deactivate` | Staff CRUD toggle rejected |
| `test_superuser_disabled_toggle_404` | SUPERUSER_ENABLED=false, /su not registered |
| `test_superuser_isAdmin_gate` | Session::isAdmin() true for super-user |
| `test_superuser_dashboard_routing` | dashboardFor() routes to /admin |
| `test_superuser_blocked_from_standard_login` | loginRequest() excludes super-user email (null, no code issued) |
| `test_superuser_session_guard_denies_superuser` | startStaffSession() with role='super-user' throws RuntimeException |

Route-registration smoke test (existing) covers superuser routes when SUPERUSER_ENABLED=true.

## Toggle lifecycle

- `SUPERUSER_ENABLED=false` (default): zero superuser routes register. `/su` returns standard 404. Seed migration still runs at startup (role promoted, account created, audit-logged), but the super-user account is blocked from standard `/login` by Defense 1 (`AND role != 'super-user'` filter in `loginRequest()`) and Defense 2 (`startStaffSession()` guard). The dedicated `/su` path does not exist. The account exists but no auth path reaches it.
- `SUPERUSER_ENABLED=true`: four routes register, `/su` login surface is live. Super-user authenticates through the dedicated elevated surface with 8-digit codes, 300s TTL, and 3 max attempts. Standard `/login` continues to exclude the super-user (Defense 1 still active).
- No other feature depends on this toggle. Safe to enable/disable at any time.

## What this does NOT touch

- **Standard `/login` flow for non-superuser staff and customers:** unchanged, zero impact. The single change (`AND role != 'super-user'` in `loginRequest()`) is a one-line exclusion that only prevents super-user accounts from authenticating through the weaker path; all other identities are unaffected.
- Zero changes to `otp_codes` schema
- Zero changes to `Mailer` or `RateLimiter` (used as-is, except `OtpAuth` IP attribution fix which improves accuracy globally)
- Zero customer-record reads or writes
- Zero changes to admin CMS route guards (superuser inherits access via `isAdmin()`)
- Zero hardcoded emails or secrets in any committed file
