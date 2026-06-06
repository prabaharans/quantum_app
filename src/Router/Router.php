<?php

declare(strict_types=1);

namespace QuantumApp\Router;

/**
 * Lightweight front-controller router.
 *
 * Usage:
 *   $router = new Router();
 *   $router->get('/api/presets', [MyController::class, 'presets']);
 *   $router->post('/api/simulate', [MyController::class, 'simulate']);
 *   $router->dispatch();
 */
class Router
{
    /** @var array<string, array<string, callable>> [method => [path => handler]] */
    private array $routes = [];

    // ─── Route registration ────────────────────────────────────────────────

    public function get(string $path, callable|array $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, callable|array $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    private function addRoute(string $method, string $path, callable|array $handler): void
    {
        $this->routes[$method][$path] = $handler;
    }

    // ─── Dispatch ──────────────────────────────────────────────────────────

    /**
     * Dispatch the current HTTP request to the appropriate handler.
     * Returns false if no route matches (caller can send 404).
     */
    public function dispatch(): bool
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $uri    = '/' . trim($uri, '/');

        // Handle CORS pre-flight
        if ($method === 'OPTIONS') {
            http_response_code(204);
            return true;
        }

        // Exact match
        if (isset($this->routes[$method][$uri])) {
            $this->call($this->routes[$method][$uri]);
            return true;
        }

        return false;
    }

    private function call(callable|array $handler): void
    {
        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;
            // Instantiate only if not already an object
            $instance = is_object($class) ? $class : new $class();
            $instance->$method();
        } elseif (is_callable($handler)) {
            ($handler)();
        }
    }
}
