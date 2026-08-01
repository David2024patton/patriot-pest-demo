# Skill: FIRST_PARTY_RETENTION_BEACON

Purpose: add first-party, cookie-light retention analytics to a PHP app with a separate SQLite store, anonymous identity tokens, same-origin ingestion endpoints, an admin-gated summary API, and a server-rendered dashboard. Proven live on test.patriotpest.pro (ORDER 3, Nash contract).

## Required Inputs

- Event contract: identity model (localStorage visitor_id / sessionStorage session_id uuids), endpoint list, payload shapes, admin summary keys. Lock it with the data owner before building (one fact, one name).
- Schema file that mirrors the contract exactly.
- App template layer where the beacon script mounts (marketing layout + app shell).
- Settings toggle keys per doctrine.

## Build Blocks (modular, swap or scale independently)

1. Separate analytics DB: second SQLite file (storage/retention.sqlite) opened via a Database::open() secondary connection, never the app auth DB. Avoids session/table collisions with the app schema.
2. Schema as idempotent SQL: CREATE TABLE IF NOT EXISTS + derived views (click_path via group_concat over row_number()). Re-runnable on every boot.
3. Beacon JS: uuid generator, localStorage visitor + sessionStorage session with 30-min TTL, page_view on load, link_click wiring, session_end via sendBeacon on pagehide/visibilitychange. Single fire() helper.
4. Ingestion endpoints: POST /api/track/view|event|session_end, always answer 204 (beacon never sees errors), payload-validated, bad payloads dropped quietly.
5. Same-origin check: compare Origin host against Host header, BOTH normalized through parse_url(..., PHP_URL_HOST) so an explicit port never false-negatives. Empty Origin (curl, old browsers) skips the check.
6. Admin summary: GET /api/retention/summary, admin-gated 401, returns the full locked shape (totals/daily/top_pages/entry_pages/top_flows/sources) computed in SQL, window = trailing 14 calendar days.
7. Dashboard: server-rendered template fed by the summary array (no fetch/base-url issues), toggles for egg/track settings.
8. Toggles: Settings::bool() typed reads with sane defaults, upsert write path, per-request cache.

## Expected Output

- Tracked pages fire view/session_end; egg/link clicks fire custom events with payloads.
- Summary returns every contract key with correct seed math (bounce, engaged rate, returning pct, flow paths as arrays).
- Dashboard renders without JS dependency; zero em dashes in UI copy.

## Acceptance Verification Checklist (run on the live wire, not on faith)

1. POST view/event/session_end return 204 with a matching Origin; DB rows land (visitor/session/page_view/event counts grow).
2. Mismatched Origin returns 204 but writes nothing (quiet drop, verify at DB level).
3. GET summary without admin session = 401; with admin = full shape, key-by-key vs contract.
4. click_path view returns full ordered paths; summary maps them to path arrays.
5. U+2014 count = 0 on dashboard and beacon copy.
6. Settings toggles flip the gates (egg markup appears/disappears, beacon loads/stops).

## Lessons

- preg_match delimiter gotcha: an unescaped / inside a character class when / is the delimiter terminates the pattern early ("Unknown modifier ']'"). Use a different delimiter (#) or escape the slash. Same class of bug on every pattern with slashes.
- group_concat(separator) needs the separator quoted in SQL (' > ') or the parser throws "near >: syntax error".
- parse_url(PHP_URL_HOST) strips the port; compare both sides normalized or localhost:port dev servers silently drop every beacon write.
- SendBeacon cannot set headers: ingestion endpoints must be CSRF-exempt by design and rely on Origin checks + payload validation instead.
- Schema exec on first touch per request (static init flag) is enough; run it idempotently so volume resets self-heal.

Source: ORDER 3 thread client-patriot-pest-control, analytics/retention_tracking/RETENTION_EVENT_CONTRACT.md.
