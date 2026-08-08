<?php
/**
 * Router for the PHP built-in dev server.
 *
 * The built-in server ignores .htaccess, so this script plays the same role:
 * serve real files (CSS/JS/images) directly, and route everything else to the
 * front controller. Run with:
 *
 *   php -S localhost:8080 -t public public/router.php
 */

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $path;

// Subdomain routing for cost dashboard (mirrors .htaccess logic)
$host = $_SERVER['HTTP_HOST'] ?? '';
if (strpos($host, 'cost.patriotpest.pro') !== false || strpos($host, 'cost.localhost') !== false) {
    // Route subdomain requests to cost subdirectory
    if (strpos($path, '/cost/') !== 0) {
        $path = '/cost' . $path;
        $file = __DIR__ . $path;
    }
}

// Let the built-in server handle existing static files as-is.
if ($path !== '/' && is_file($file)) {
    return false;
}

// Everything else goes through the app.
require __DIR__ . '/index.php';
