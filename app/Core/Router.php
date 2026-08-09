<?php
/**
 * Router — small, explicit front-controller router.
 *
 * Replaces the old hybrid (.htaccess rewrite to loose .php files + a router.php
 * shim). All requests flow through public/index.php → Router::dispatch().
 *
 * Features:
 *   - GET/POST registration with {param} placeholders,
 *   - chainable per-route guards: ->auth('staff'|'customer'|'*')->role('admin'),
 *   - handlers are closures or [Controller::class, 'method'],
 *   - clean 404 for anything unmatched.
 *
 * Example:
 *   Router::get('/pest/{slug}', [PestController::class, 'show']);
 *   Router::post('/admin/posts', [PostController::class, 'store'])->auth('staff')->role('admin');
 */

declare(strict_types=1);

namespace PPC\Core;

/**
 * A single registered route. Returned by Router::get()/post() so guards can be
 * chained fluently: ->auth('staff')->role('admin').
 */
final class Route
{
    public ?string $auth = null; // required user_type ('*' = any authenticated)
    public ?string $role = null; // required staff role
    /** @var string[] Extra permissions required on top of role (e.g. 'view_customers'). */
    public array $permissions = [];

    public function __construct(
        public string $method,
        public string $regex,
        public array $params,
        public mixed $handler,
    ) {}

    /** Require an authenticated user of $type ('*' for any). */
    public function auth(string $type = '*'): self
    {
        $this->auth = $type;
        return $this;
    }

    /** Require a specific staff role (admin routes also allow Session::isAdmin()). */
    public function role(string $role): self
    {
        $this->role = $role;
        return $this;
    }

    /**
     * Require one or more specific permissions beyond role.
     * Admin users bypass all permission checks.
     */
    public function permission(string ...$perms): self
    {
        $this->permissions = array_merge($this->permissions, $perms);
        return $this;
    }
}

final class Router
{
    /** @var Route[] */
    private static array $routes = [];

    /** Register a GET route; returns the Route for chaining guards. */
    public static function get(string $pattern, mixed $handler): Route
    {
        return self::add('GET', $pattern, $handler);
    }

    /** Register a POST route. */
    public static function post(string $pattern, mixed $handler): Route
    {
        return self::add('POST', $pattern, $handler);
    }

    /** Compile a /pest/{slug} pattern into a regex and register the route. */
    private static function add(string $method, string $pattern, mixed $handler): Route
    {
        $params = [];
        $regex  = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', function ($m) use (&$params) {
            $params[] = $m[1];
            return '(?P<' . $m[1] . '>[^/]+)';
        }, $pattern);

        $route = new Route($method, '#^' . $regex . '/?$#', $params, $handler);
        self::$routes[] = $route;
        return $route;
    }

    /**
     * Return every registered route. Used by the route-registration smoke
     * test to assert every controller class referenced by a route resolves.
     */
    public static function routes(): array
    {
        return self::$routes;
    }

    /**
     * Clear all registered routes (test seam).
     * Allows tests to re-register routes under different toggle states.
     */
    public static function reset(): void
    {
        self::$routes = [];
    }

    /** Match the current request and invoke its handler. */
    public static function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = '/' . trim($path, '/');
        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        foreach (self::$routes as $route) {
            if ($route->method !== $method || !preg_match($route->regex, $path, $m)) {
                continue;
            }
            $params = [];
            foreach ($route->params as $p) {
                $params[$p] = $m[$p] ?? null;
            }
            self::enforceGuards($route);
            self::invoke($route->handler, $params);
            return;
        }

        self::notFound();
    }

    /** Enforce auth/role/permission guards before the handler runs. */
    private static function enforceGuards(Route $route): void
    {
        if ($route->auth !== null) {
            $ok = $route->auth === '*' ? Session::authenticated() : (Session::userType() === $route->auth);
            if (!$ok) {
                header('Location: /login');
                exit;
            }
        }
        if ($route->role !== null) {
            if (Session::staffRole() !== $route->role && !Session::isAdmin()) {
                http_response_code(403);
                echo View::render('errors/403');
                exit;
            }
        }
        // Permission checks: each required permission must be held.
        // Admins bypass by definition; Session::hasPermission handles that.
        foreach ($route->permissions as $perm) {
            if (!Session::hasPermission($perm)) {
                http_response_code(403);
                echo View::render('errors/403');
                exit;
            }
        }
    }

    /** Invoke a handler (closure or [Class, 'method']) with the route params. */
    private static function invoke(mixed $handler, array $params): void
    {
        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            (new $class())->$method(...array_values($params));
        } elseif (is_callable($handler)) {
            $handler(...array_values($params));
        } else {
            self::notFound();
        }
    }

    /** Render a friendly 404. */
    public static function notFound(): void
    {
        http_response_code(404);
        echo View::render('errors/404');
        exit;
    }
}
