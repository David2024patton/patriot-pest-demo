<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PPC\Core\Router;

/**
 * Route-registration smoke test.
 *
 * Catches the defect class that phpunit unit tests and php -l cannot see:
 * a route handler referencing a controller class that does not resolve
 * (missing use import, renamed class, deleted file) or a method that does
 * not exist. Router::invoke() does `new $class()` at dispatch time, so
 * these failures only surface on a real HTTP request.
 *
 * Two real defects of this class shipped in Part E and were only caught at
 * dispatch level: public/index.php registered ApiController::class and
 * ApiKeyController::class routes without the `use` imports, so every
 * /api/v1/* and /admin/api-keys* request fataled "Class not found". This
 * test makes that class of regression impossible to repeat.
 *
 * Runs in routes-only mode (PPC_ROUTES_ONLY): public/index.php registers
 * every route exactly as a real request would, then stops before dispatch.
 * app/bootstrap.php skips global error handlers and the session in this
 * mode so the test has no side effects to clean up.
 */
final class RouteRegistrationTest extends TestCase
{
    #[Test]
    public function every_route_controller_class_resolves(): void
    {
        // Force the API surface ON so its routes are registered and checked
        // even when the deployment default (API_ENABLED=false) would skip
        // them. Also force APP_ENV=local so the production HTTPS-force
        // redirect in the front controller can never fire and exit.
        $prevApi = getenv('API_ENABLED');
        $prevEnv = getenv('APP_ENV');
        putenv('API_ENABLED=true');
        putenv('APP_ENV=local');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI']    = '/route-registration-smoke-test';

        try {
            if (!defined('PPC_ROUTES_ONLY')) {
                define('PPC_ROUTES_ONLY', true);
            }
            require BASE_PATH . '/public/index.php';

            $routes = Router::routes();
            $this->assertNotEmpty($routes, 'No routes registered - front controller did not load');

            $handlersChecked = 0;
            foreach ($routes as $route) {
                $handler = $route->handler;
                if (!is_array($handler) || count($handler) !== 2) {
                    continue; // closures are callable by construction
                }
                [$class, $method] = $handler;
                $this->assertTrue(
                    class_exists($class),
                    "Route handler class {$class} does not resolve (missing use import or file) - dispatch would fatal 'Class not found'"
                );
                $this->assertTrue(
                    method_exists($class, $method),
                    "Route handler method {$class}::{$method} does not exist"
                );
                $instance = new $class();
                $this->assertTrue(
                    is_callable([$instance, $method]),
                    "Route handler {$class}::{$method} is not callable on a fresh instance"
                );
                $handlersChecked++;
            }
            $this->assertGreaterThan(0, $handlersChecked, 'No array-style [Class, method] handlers found - nothing to assert');
        } finally {
            // Restore env so later tests in this process see the original state.
            if ($prevApi === false) {
                putenv('API_ENABLED');
            } else {
                putenv('API_ENABLED=' . $prevApi);
            }
            if ($prevEnv === false) {
                putenv('APP_ENV');
            } else {
                putenv('APP_ENV=' . $prevEnv);
            }
        }
    }
}
