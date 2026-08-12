<?php
/**
 * Bootstrap - application entry-point setup.
 *
 * Loaded once by public/index.php (and by CLI scripts). Responsibilities:
 *   - define BASE_PATH,
 *   - load .env into Config,
 *   - register the PSR-4 autoloader (PPC\ => app/),
 *   - install error/exception handlers (log everything, expose details only
 *     in debug mode - never leak stack traces in production),
 *   - initialise Logger, View, and the hardened Session.
 */

declare(strict_types=1);

// Project root (this file lives in app/).
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

// --- Configuration -------------------------------------------------------
require_once BASE_PATH . '/app/Core/Config.php'; // require_once: tests may have loaded it already
\PPC\Core\Config::load(BASE_PATH . '/.env');

// --- PSR-4 autoloader: PPC\Foo\Bar => app/Foo/Bar.php --------------------
spl_autoload_register(function (string $class): void {
    $prefix = 'PPC\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));               // Foo\Bar
    $file     = BASE_PATH . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_readable($file)) {
        require $file;
    }
});

use PPC\Core\Config;
use PPC\Core\Logger;
use PPC\Core\Session;
use PPC\Core\View;

// --- Logging -------------------------------------------------------------
Logger::setDir(BASE_PATH . '/storage/logs');

// --- Error / exception handling -----------------------------------------
// Log every error; only show details when debugging (local). Production gets
// a generic message so attackers learn nothing from stack traces.
if (!defined('PPC_ROUTES_ONLY')) {
    set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
        if (!(error_reporting() & $severity)) {
            return false; // respect @-suppression / error_reporting level
        }
        Logger::warning('PHP error', ['msg' => $message, 'file' => $file, 'line' => $line]);
        return false; // let PHP's normal handling continue
    });

    set_exception_handler(function (\Throwable $e): void {
        Logger::error('Uncaught exception', [
            'msg'  => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
        http_response_code(500);
        if (Config::debug()) {
            header('Content-Type: text/plain; charset=utf-8');
            echo "APPLICATION ERROR (debug)\n\n" . $e->getMessage() . "\n\n" . $e->getTraceAsString();
        } else {
            header('Content-Type: text/html; charset=utf-8');
            echo '<h1>Something went wrong</h1><p>Please try again shortly.</p>';
        }
    });
} // end PPC_ROUTES_ONLY guard (error/exception handlers)

// --- View templates base -------------------------------------------------
View::setBase(BASE_PATH . '/templates');

// --- Session (hardened) --------------------------------------------------
if (!defined('PPC_ROUTES_ONLY')) {
    Session::start();
}
