# route-registration-smoke-test

**Purpose:** Detect the "route handler class does not resolve" defect class before it ships: a front controller that registers a route with a controller class that cannot be instantiated at dispatch (missing `use` import, renamed class, deleted file) or a handler method that does not exist. `Router::invoke()` does `new $class()` at dispatch time, so these failures are invisible to phpunit unit tests and `php -l` and only surface on a real HTTP request.

**Forged:** 2026-08-09 by Quality Assurance, after two real defects of this class shipped in Part E and were only caught at dispatch level:
- public/index.php registered `ApiController::class` / `ApiKeyController::class` routes without the `use` imports — every `/api/v1/*` and `/admin/api-keys*` request fataled "Class not found".
- Root cause of the blind spot: phpunit unit tests assert on in-memory values; `php -l` checks syntax only. Neither exercises routing.

## What pattern to look for

- Any change to `public/index.php` route registration (new endpoint, renamed controller, moved class, removed file).
- Any API surface addition guarded by a feature toggle — toggled-off branches are skipped at runtime, so their controller classes are never resolved in a default-false deployment. The defect hides until the toggle flips.
- Any controller class or method rename across the app while route strings still reference the old names.

## How to detect it

Run the suite:

```
vendor/bin/phpunit --filter RouteRegistrationTest
```

The test registers every route exactly as the front controller does (API_ENABLED forced on so the whole surface is exercised), then for every `[Class, method]` handler asserts:
1. `class_exists($class)` — catches missing imports/renames/deleted files (the Part E defect),
2. `method_exists($class, $method)` — catches renamed/removed handler methods,
3. `new $class()` + `is_callable([$instance, $method])` — catches constructor breakage that would fatal `Router::invoke()`.

It fails with a message naming the exact class that does not resolve.

## The fix template (seams)

The test requires three behavior-preserving seams (all no-ops outside routes-only mode):

1. `public/index.php`: wrap the final dispatch so registration can run without dispatching:
   ```php
   if (!defined('PPC_ROUTES_ONLY')) {
       Router::dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
   }
   ```
2. `app/Core/Router.php`: expose the route table:
   ```php
   public static function routes(): array { return self::$routes; }
   ```
3. `app/bootstrap.php`: make the bootstrap re-includable and side-effect-free for route registration:
   - `if (!defined('BASE_PATH')) { define('BASE_PATH', dirname(__DIR__)); }`
   - `require_once` for `app/Core/Config.php` (test bootstraps may load it first)
   - skip `set_error_handler`/`set_exception_handler` and `Session::start()` when `PPC_ROUTES_ONLY` is defined (global handlers trip PHPUnit's "did not remove its own error handlers" risky check; routes-only mode never dispatches so handlers/session have nothing to do).

The test forces `putenv('API_ENABLED=true')` and `putenv('APP_ENV=local')` before including `public/index.php` (toggle-on so the API surface registers; APP_ENV=local so the production HTTPS-force redirect can never fire and exit), and restores the env in `finally`.

## Why it matters

The defect class is deploy-breaking (site-wide 500s / dead API surface) and invisible to every automated check that ran before dispatch testing: phpunit 16/46 passed, `php -l` passed, static review passed, yet every API and admin-key request fataled. The smoke test turns "caught only by manual e2e" into "caught by the suite in <100ms".

## Scope and limits

- Catches: class resolution, method existence, instantiation, handler shape.
- Does NOT catch: template/form field contract mismatches (e.g. the Part E CSRF `csrf_token` vs `_csrf` defect) — those need the HTTP/e2e harness.
- Keep the seams minimal: routes-only mode must never skip anything a route registration actually needs (Config, autoloader, Logger/View base are kept).
