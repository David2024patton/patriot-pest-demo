# Skill: PWA_INSTALL_PROMPT

Purpose: add an install-as-app prompt plus the underlying PWA plumbing (web app manifest, service worker, beforeinstallprompt handler) to an existing PHP site, shown only on mobile/tablet widths, dismissible, and served with correct MIME types behind nginx. Proven in production use on the Patriot Pest test site (ORDER A, deployed via PR #5).

## Required Inputs

- The site layout(s) that wrap every page (for example templates/layouts/main.php plus the auth shell templates/layouts/app.php).
- The nginx server block for the deploy (for example deploy/nginx/default.conf). The static-assets regex must NOT include the manifest, or it will fall through to the PHP front controller and return HTML.
- A valid PNG icon set: 192x192 (install), 512x512 (maskable-capable), 180x180 (apple-touch-icon). Verify with getimagesize before committing.
- The existing design tokens (CSS custom properties) so the banner matches the brand (for example tactical olive/orange on Patriot Pest).

## Build Blocks (modular, swap or scale independently)

1. Manifest (public/manifest.webmanifest): name, short_name, start_url "/", scope "/", display standalone, theme_color, background_color, icons array with purpose any + maskable, plus the apple-touch-icon link in each layout head. JSON must parse; validate with php -r json_decode.
2. Service worker (public/sw.js): versioned cache name (ppc-shell-vN), precache the shell list, activate deletes old cache versions, fetch handler:
   - GET only; never intercept POST (login, beacon, forms) so CSRF and retention flows stay intact.
   - Navigations network-first with cache fallback (offline basic); cache successful HTML under its own URL.
   - Static assets stale-while-revalidate; cross-origin requests pass through.
   - skipWaiting + clients.claim so updates apply on the next load.
3. Install prompt (public/assets/pwa-install.js + .css + a shared partial templates/partials/install-banner.php):
   - Register the service worker on load (secure contexts or localhost only).
   - beforeinstallprompt: preventDefault, stash the event, show the banner only when matchMedia(max-width:1024px) matches AND the user has not dismissed it (localStorage flag).
   - prompt() fires only on the Install button tap (user gesture). appinstalled hides and marks dismissed. X button hides and marks dismissed. Viewport crossing to desktop hides.
   - Banner is a fixed bottom bar (safe-area inset), hidden by default, revealed with an .is-open class; never blocks content.
4. nginx (deploy/nginx/default.conf): exact-match location for /sw.js with default_type text/javascript and Cache-Control no-cache; a webmanifest location with default_type application/manifest+json and no-cache; add application/manifest+json to gzip_types. Match in .htaccess (AddType + no-cache FilesMatch) for Apache dev parity.
5. Wire-up: manifest link, theme-color meta, apple-touch-icon, pwa-install.css in each layout head; render the partial and load pwa-install.js before </body> in each layout.

## Expected Output

- A branch/PR carrying manifest + sw.js + prompt assets + layout wiring + nginx/.htaccess MIME and cache rules.
- Live wire: /manifest.webmanifest serves 200 application/manifest+json; /sw.js serves 200 with no-cache; icons serve 200 image/png.
- Mobile/tablet widths (<= 1024px) show the dismissible prompt when the browser offers install; desktop never shows it.

## Acceptance Verification Checklist (run locally before the PR, on the wire after deploy)

1. php -l clean on every changed template; node --check clean on sw.js and pwa-install.js.
2. Manifest JSON parses; icon dimensions are exactly 180/192/512 PNG.
3. nginx -t passes. Live container test (docker run nginx:alpine + docker cp, MSYS_NO_PATHCONV=1 on Windows):
   - /manifest.webmanifest -> 200 application/manifest+json
   - /sw.js -> 200, Cache-Control no-cache
   - /assets/icons/*.png -> 200 image/png
4. U+2014 (em dash) count = 0 across every new and changed file.
5. After deploy: re-run the manifest/icon checks against the public host (must be 200, not the PHP front controller HTML).

## Lessons

- The nginx static-assets regex is the trap: manifest and json are not covered by the typical css|js|png|ico list, so without an explicit location the manifest 404s or returns HTML through the front controller. Always ship the nginx MIME fix in the same commit as the manifest.
- sw.js must never be served with a long immutable cache header or browsers will not check for updates; use no-cache and rely on the versioned cache name for migration.
- On Windows hosts, docker run volume mounts mangle paths in Git Bash: prefer docker cp into a running container and disable MSYS path conversion (MSYS_NO_PATHCONV=1) for exec commands.
- Gate the banner in BOTH CSS and JS (defense in depth) so desktop never shows it even if one layer misbehaves.

Source: ORDER A thread client-patriot-pest-control, PR #5, deploy/nginx/default.conf.
