<?php
declare(strict_types=1);
// Test bootstrap: loads the app without starting a session.
define("BASE_PATH", dirname(__DIR__));
require BASE_PATH . "/app/Core/Config.php";
\PPC\Core\Config::load(BASE_PATH . "/.env");
spl_autoload_register(function (string $class): void {
    $prefix = "PPC\\";
    if (!str_starts_with($class, $prefix)) { return; }
    $relative = substr($class, strlen($prefix));
    $file = BASE_PATH . "/app/" . str_replace("\\", "/", $relative) . ".php";
    if (is_readable($file)) { require $file; }
});
require __DIR__ . "/../vendor/autoload.php";
