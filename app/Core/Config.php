<?php
/**
 * Config - environment + configuration loader.
 *
 * Replaces the old hand-rolled `.env` parser (which mishandled quoting and
 * escaping). This version:
 *   - strips comments and trims whitespace safely,
 *   - supports single/double quoted values and escaped characters,
 *   - exposes typed getters (string/int/bool) so callers never guess types,
 *   - lets real environment variables override `.env` (12-factor friendly).
 *
 * SECURITY: secrets live only in `.env` (git-ignored). Nothing here is logged.
 */

declare(strict_types=1);

namespace PPC\Core;

final class Config
{
    /** @var array<string,string> Loaded key/value pairs. */
    private static array $items = [];

    /** @var bool Whether the .env file has been loaded yet. */
    private static bool $loaded = false;

    /**
     * Load configuration from a `.env` file (once).
     *
     * @param string $path Absolute path to the .env file.
     */
    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }

        if (is_readable($path)) {
            // FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES keeps parsing simple.
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                $line = trim($line);
                // Skip blank lines and full-line comments (# or ;).
                if ($line === '' || $line[0] === '#' || $line[0] === ';') {
                    continue;
                }
                // A valid line must contain "=".
                if (!str_contains($line, '=')) {
                    continue;
                }

                [$key, $value] = explode('=', $line, 2);
                $key   = trim($key);
                $value = self::parseValue(trim($value));

                if ($key !== '') {
                    self::$items[$key] = $value;
                }
            }
        }

        self::$loaded = true;
    }

    /**
     * Parse a raw value: strip surrounding quotes and unescape inner characters.
     */
    private static function parseValue(string $value): string
    {
        $len = strlen($value);
        if ($len >= 2) {
            $first = $value[0];
            $last  = $value[$len - 1];
            // Matching single or double quotes → unwrap and unescape.
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $inner = substr($value, 1, -1);
                return $first === '"'
                    ? str_replace(['\\n', '\\r', '\\t', '\\"', '\\\\'], ["\n", "\r", "\t", '"', '\\'], $inner)
                    : $inner; // single-quoted: literal, no escape processing
            }
        }
        // Unquoted: strip any trailing inline comment (" value # note").
        if (preg_match('/^([^#]*?)\s+#.*$/', $value, $m)) {
            return trim($m[1]);
        }
        return $value;
    }

    /**
     * Get a config value. Real environment variables take precedence over .env.
     *
     * @param string      $key     The key to look up.
     * @param string|null $default Returned when the key is missing/empty.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        // getenv() lets the host override .env without editing the file.
        $env = getenv($key);
        if ($env !== false && $env !== '') {
            return $env;
        }
        $val = self::$items[$key] ?? null;
        return ($val === null || $val === '') ? $default : $val;
    }

    /** Get a value as an integer. */
    public static function int(string $key, int $default = 0): int
    {
        $v = self::get($key);
        return $v === null ? $default : (int) $v;
    }

    /** Get a value as a boolean ("true"/"1"/"yes"/"on" → true). */
    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::get($key);
        if ($v === null) {
            return $default;
        }
        return in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
    }

    /** Current environment name (local/staging/production). */
    public static function env(): string
    {
        return self::get('APP_ENV', 'production') ?? 'production';
    }

    /** True when running locally (enables verbose tooling, never in prod). */
    public static function isLocal(): bool
    {
        return self::env() === 'local';
    }

    /** True when production (forces secure cookies, hides errors). */
    public static function isProduction(): bool
    {
        return self::env() === 'production';
    }

    /** True when detailed error output is allowed (local + APP_DEBUG=true). */
    public static function debug(): bool
    {
        return self::bool('APP_DEBUG', false) && !self::isProduction();
    }
}
