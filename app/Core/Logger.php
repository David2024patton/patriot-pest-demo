<?php
/**
 * Logger — lightweight structured file logger (PSR-3 inspired).
 *
 * Why it exists: the old site scattered `error_log()` calls and left a 350KB
 * `php_error.log` with no structure. This logger writes one file per day under
 * storage/logs/ with a consistent, greppable format:
 *
 *   [2026-07-21T14:03:11-07:00] ERROR: OTP verify failed {"email":"x@y.z","ip":"1.2.3.4"}
 *
 * Usage:
 *   Logger::info('Customer logged in', ['customer_id' => 42]);
 *   Logger::error('FieldRoutes timeout', ['district' => 'wa']);
 *
 * SECURITY: never pass secrets (tokens, codes, passwords) into $context.
 * In production, debug()-level messages are skipped to avoid noise/leaks.
 */

declare(strict_types=1);

namespace PPC\Core;

final class Logger
{
    /** Minimum level that gets written (configurable; defaults to debug locally). */
    private const LEVELS = ['debug' => 0, 'info' => 1, 'notice' => 2, 'warning' => 3, 'error' => 4, 'critical' => 5];

    /** @var string Directory log files are written to. */
    private static string $dir = '';

    /**
     * Point the logger at a directory (called once during bootstrap).
     */
    public static function setDir(string $dir): void
    {
        self::$dir = rtrim($dir, '/\\');
        if (!is_dir(self::$dir)) {
            @mkdir(self::$dir, 0775, true);
        }
    }

    /** Log a debug message (suppressed in production). */
    public static function debug(string $msg, array $context = []): void
    {
        if (!Config::isProduction()) {
            self::write('debug', $msg, $context);
        }
    }

    public static function info(string $msg, array $context = []): void
    {
        self::write('info', $msg, $context);
    }

    public static function notice(string $msg, array $context = []): void
    {
        self::write('notice', $msg, $context);
    }

    public static function warning(string $msg, array $context = []): void
    {
        self::write('warning', $msg, $context);
    }

    public static function error(string $msg, array $context = []): void
    {
        self::write('error', $msg, $context);
    }

    public static function critical(string $msg, array $context = []): void
    {
        self::write('critical', $msg, $context);
    }

    /**
     * Write a single log line to today's file. Failures are silent (logging
     * must never crash the request), but we fall back to PHP's error_log().
     */
    private static function write(string $level, string $msg, array $context): void
    {
        $line = sprintf(
            "[%s] %s: %s%s\n",
            date('c'),
            strtoupper($level),
            $msg,
            $context ? ' ' . self::encode($context) : ''
        );

        if (self::$dir === '') {
            error_log(trim($line));
            return;
        }

        $file = self::$dir . DIRECTORY_SEPARATOR . date('Y-m-d') . '.log';
        if (@file_put_contents($file, $line, FILE_APPEND | LOCK_EX) === false) {
            error_log(trim($line)); // last-resort fallback
        }
    }

    /**
     * JSON-encode context, masking anything that looks sensitive.
     */
    private static function encode(array $context): string
    {
        array_walk_recursive($context, function (&$v, $k) {
            // Mask values whose keys suggest secrets.
            if (is_string($k) && preg_match('/(pass|token|secret|code|key|sid)/i', (string) $k)) {
                $v = '***';
            }
        });
        return (string) json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
