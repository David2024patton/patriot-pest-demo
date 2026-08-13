<?php
/**
 * Settings - global site toggles backed by the site_settings table.
 *
 * Provides cached typed reads plus a write path for the admin CMS. Keys used:
 *   egg_enabled   ('1'|'0')  the $25 easter egg beacon on the marketing site
 *   track_enabled ('1'|'0')  first-party retention beacon + dashboard
 *
 * Reads are cached per request so layouts can call bool() freely.
 */

declare(strict_types=1);

namespace PPC\Core;

final class Settings
{
    /** @var array<string,string>|null Cached key/value map. */
    private static ?array $cache = null;

    /** Load the settings table into the request cache (once). */
    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach (Database::instance()->fetchAll('SELECT key, value FROM site_settings') as $row) {
                self::$cache[(string) $row['key']] = (string) $row['value'];
            }
        }
        return self::$cache;
    }

    /** Typed boolean read with a sane default for missing keys. */
    public static function bool(string $key, bool $default = true): bool
    {
        $v = self::all()[$key] ?? null;
        if ($v === null) {
            return $default;
        }
        return in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
    }

    /** Raw string read with a default for missing keys. */
    public static function get(string $key, string $default = ''): string
    {
        return self::all()[$key] ?? $default;
    }

    /** Upsert a setting and drop the cache so the next read is fresh. */
    public static function set(string $key, string $value): void
    {
        Database::instance()->execute(
            "INSERT INTO site_settings (key, value, updated_at)
             VALUES (?, ?, datetime('now'))
             ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = datetime('now')",
            [$key, $value]
        );
        self::$cache = null;
    }
}
