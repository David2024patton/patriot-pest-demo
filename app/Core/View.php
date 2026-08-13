<?php
/**
 * View - minimal PHP template renderer.
 *
 * Templates live in templates/ as plain .php files (the scrollytelling design
 * is converted into these). A template receives its data as extracted local
 * variables plus a set of always-available helpers (e(), asset(), url(), csrf
 * field, current session info) injected by this class.
 *
 * Two render modes:
 *   - View::render('pages/home', $data)            → raw template HTML
 *   - View::page('pages/home', $data, $pageMeta)   → template wrapped in the
 *     main layout (nav/footer/SEO head), which is what pages actually use.
 *
 * All output is escaped by default via e(); templates opt into raw HTML only
 * for trusted CMS content via raw().
 */

declare(strict_types=1);

namespace PPC\Core;

final class View
{
    /** @var string Base directory for templates. */
    private static string $base = '';

    public static function setBase(string $dir): void
    {
        self::$base = rtrim($dir, '/\\');
    }

    /**
     * Render a template file with data; returns the HTML string.
     *
     * @param string $template Path relative to templates/ (no .php extension).
     * @param array  $data     Variables made available to the template.
     */
    public static function render(string $template, array $data = []): string
    {
        $file = self::$base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $template) . '.php';
        if (!is_readable($file)) {
            Logger::error('View template missing', ['template' => $template]);
            return self::render('errors/404', ['title' => 'Page Not Found']);
        }

        // Helpers available inside every template.
        $view = new class {
            /** Escape for safe HTML output. */
            public function e(mixed $v): string
            {
                return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
            }
            /** Pass trusted HTML through (CMS content that was sanitized on save). */
            public function raw(mixed $v): string
            {
                return (string) ($v ?? '');
            }
            /** Public asset URL. */
            public function asset(string $path): string
            {
                $rel = ltrim($path, '/');
                // Cache-bust: filemtime changes on every deploy so browsers
                // never serve stale CSS/JS after an update.
                $v = '1';
                foreach ([BASE_PATH . '/public/assets/' . $rel, BASE_PATH . '/assets/' . $rel] as $c) {
                    if (is_file($c)) { $v = (string) filemtime($c); break; }
                }
                return '/assets/' . $rel . '?v=' . $v;
            }
            /** Absolute site URL for a path (canonical/OG tags). */
            public function url(string $path = '/'): string
            {
                return rtrim((string) Config::get('APP_URL', ''), '/') . '/' . ltrim($path, '/');
            }
            /** CSRF hidden field for forms. */
            public function csrf(): string
            {
                return Csrf::field();
            }
            /** The phone number to show this visitor (localized by region). */
            public function phone(): string
            {
                return Geo::phoneDisplay();
            }
            /** The tel: href for this visitor's localized number. */
            public function phoneHref(): string
            {
                return Geo::phoneTel();
            }
            /** Coverage label for this visitor's line (e.g. "ARIZONA"). */
            public function phoneLabel(): string
            {
                return Geo::phoneLabel();
            }
            /** True if this visitor should see the Arizona line. */
            public function isArizona(): bool
            {
                return Geo::isArizona();
            }
            /** Current session helpers for nav state. */
            public function authed(): bool
            {
                return Session::authenticated();
            }
            public function userType(): ?string
            {
                return Session::userType();
            }
            public function userName(): ?string
            {
                return Session::get('display_name');
            }
        };

        extract($data, EXTR_SKIP);
        ob_start();
        try {
            include $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            Logger::error('Template render failed', ['template' => $template, 'error' => $e->getMessage()]);
            throw $e;
        }
        return (string) ob_get_clean();
    }

    /**
     * Render a page template wrapped in a layout.
     *
     * @param string      $template Page template path (relative to templates/).
     * @param array       $data     Data for the page template.
     * @param array       $meta     SEO meta: title, description, keywords, canonical,
     *                              og image, jsonld (array of arrays), crumb.
     * @param string|null $layout   Force a layout (relative to templates/). When
     *                              null, the layout is auto-detected from the
     *                              request path: authenticated app areas get the
     *                              light shell, everything else the dark marketing
     *                              layout. Login/verify stay on the marketing layout.
     */
    public static function page(string $template, array $data = [], array $meta = [], ?string $layout = null): string
    {
        $content = self::render($template, $data);
        $layout  = $layout ?? self::detectLayout();
        return self::render($layout, array_merge($meta, [
            'content' => $content,
            'data'    => $data,
        ]));
    }

    /**
     * Pick the page shell from the current request path. Authenticated app
     * areas (staff dashboard, customer portal, CMS, staff tools, account) use
     * the light app shell; the login/verify pages and all public pages keep the
     * dark marketing layout.
     */
    private static function detectLayout(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        if (preg_match('#^/(admin|staff-dashboard|customer-dashboard|staff|account)(/|$)#', $path)) {
            return 'layouts/app';
        }
        return 'layouts/main';
    }
}
