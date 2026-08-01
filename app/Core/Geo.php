<?php
/**
 * Geo - visitor region detection for localizing the site (phone number, etc.).
 *
 * Patriot runs two phone lines: the main WA/ID/OR line and a dedicated Arizona
 * line. This class figures out which line to show a visitor based on where they
 * appear to be, so an Arizonan sees the Arizona number front-and-center while
 * everyone else sees the main line.
 *
 * Detection order (first hit wins):
 *   1. Session cache - resolved once per visit, never re-computed per request.
 *   2. GEO_FORCE_REGION env var - lets you preview a region (e.g. "az") locally.
 *   3. On-disk cache keyed by IP (7-day TTL) - repeat visitors skip the lookup.
 *   4. A fast GeoIP lookup (ip-api.com, no key needed) with a short timeout.
 *   5. Default: 'wa' (the main line) - we never block or fail a page over this.
 *
 * It is deliberately fail-open: any error, timeout, or private/localhost IP just
 * yields the default region. Localization is a nicety, never a dependency.
 */

declare(strict_types=1);

namespace PPC\Core;

final class Geo
{
    /** The two phone regions and their lines. */
    public const REGIONS = [
        'wa' => ['display' => '(509) 471-5767', 'tel' => '+15094715767', 'label' => 'WA, ID, OR', 'state' => 'Washington'],
        'az' => ['display' => '(602) 755-8414', 'tel' => '+16027558414', 'label' => 'ARIZONA',   'state' => 'Arizona'],
    ];

    public const DEFAULT_REGION = 'wa';

    /** How long an IP→region result is cached on disk. */
    private const CACHE_TTL = 7 * 24 * 3600; // 7 days

    /** Max seconds to wait on the GeoIP lookup (fail fast, never block a page). */
    private const LOOKUP_TIMEOUT = 1.5;

    /** @var string|null Per-request memo so we resolve at most once per request. */
    private static ?string $resolved = null;

    /**
     * The visitor's region code: 'wa' (main line) or 'az' (Arizona line).
     */
    public static function region(): string
    {
        if (self::$resolved !== null) {
            return self::$resolved;
        }

        // 1) Session cache.
        $fromSession = Session::get('geo_region');
        if (is_string($fromSession) && isset(self::REGIONS[$fromSession])) {
            return self::$resolved = $fromSession;
        }

        // 2) Explicit override (local preview / testing).
        $forced = Config::get('GEO_FORCE_REGION');
        if ($forced !== null && isset(self::REGIONS[strtolower($forced)])) {
            return self::$resolved = self::remember(strtolower($forced));
        }

        // 3) + 4) Disk cache, then live lookup, by client IP.
        $ip     = self::clientIp();
        $region = null;
        if ($ip !== null && self::isPublicIp($ip)) {
            $region = self::fromCache($ip) ?? self::lookup($ip);
            if ($region !== null) {
                self::toCache($ip, $region);
            }
        }

        // 5) Default.
        return self::$resolved = self::remember($region ?? self::DEFAULT_REGION);
    }

    /** True when the visitor resolves to the Arizona line. */
    public static function isArizona(): bool
    {
        return self::region() === 'az';
    }

    /** The phone number to show this visitor, e.g. "(602) 755-8414". */
    public static function phoneDisplay(): string
    {
        return self::REGIONS[self::region()]['display'];
    }

    /** The tel: href for this visitor, e.g. "tel:+16027558414". */
    public static function phoneTel(): string
    {
        return 'tel:' . self::REGIONS[self::region()]['tel'];
    }

    /** The coverage label for this visitor's line, e.g. "ARIZONA". */
    public static function phoneLabel(): string
    {
        return self::REGIONS[self::region()]['label'];
    }

    /** The other line (so templates can show both, primary first). */
    public static function otherRegion(): string
    {
        return self::region() === 'az' ? 'wa' : 'az';
    }

    /* ============================ internals ============================ */

    /** Persist to the session so the whole visit is consistent. */
    private static function remember(string $region): string
    {
        Session::put('geo_region', $region);
        return $region;
    }

    /** The client's IP, honoring common proxy headers. */
    private static function clientIp(): ?string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            $val = $_SERVER[$key] ?? '';
            if ($val !== '') {
                $first = trim(explode(',', $val)[0]);
                if (filter_var($first, FILTER_VALIDATE_IP)) {
                    return $first;
                }
            }
        }
        return null;
    }

    /** Skip lookups for localhost / private ranges (dev boxes, LAN). */
    private static function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    /** Read a cached IP→region result, or null if missing/expired. */
    private static function fromCache(string $ip): ?string
    {
        $file = self::cacheFile($ip);
        if (!is_readable($file)) {
            return null;
        }
        $data = json_decode((string) @file_get_contents($file), true);
        if (!is_array($data) || ($data['ts'] ?? 0) + self::CACHE_TTL < time()) {
            return null;
        }
        $region = $data['region'] ?? null;
        return is_string($region) && isset(self::REGIONS[$region]) ? $region : null;
    }

    /** Write an IP→region result to the disk cache. */
    private static function toCache(string $ip, string $region): void
    {
        $dir = dirname(self::cacheFile($ip));
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents(
            self::cacheFile($ip),
            json_encode(['region' => $region, 'ts' => time()]),
            LOCK_EX
        );
    }

    private static function cacheFile(string $ip): string
    {
        return BASE_PATH . '/storage/cache/geo/' . md5($ip) . '.json';
    }

    /**
     * Live GeoIP lookup. Returns 'az' or 'wa', or null on any failure. Uses the
     * free ip-api.com endpoint (no key required); the region field is the state
     * code. Arizona → 'az'; everything else falls to the main 'wa' line.
     */
    private static function lookup(string $ip): ?string
    {
        $ctx = stream_context_create([
            'http' => ['timeout' => self::LOOKUP_TIMEOUT, 'ignore_errors' => true],
        ]);
        $body = @file_get_contents("http://ip-api.com/json/{$ip}?fields=status,region", false, $ctx);
        if ($body === false) {
            return null;
        }
        $data = json_decode($body, true);
        if (!is_array($data) || ($data['status'] ?? '') !== 'success') {
            return null;
        }
        $state = strtoupper((string) ($data['region'] ?? ''));
        return $state === 'AZ' ? 'az' : self::DEFAULT_REGION;
    }
}
