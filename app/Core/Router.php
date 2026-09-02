<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal routing table supporting {param} path segments.
 *
 * Usage:
 *   $router = new Router();
 *   $router->get('/products/{sku}', [ProductController::class, 'show']);
 *   $router->dispatch(Request::capture());
 */
final class Router
{
    /**
     * @var array<string, array<int, array{pattern:string, names:string[], handler:callable|array}>>
     */
    private array $routes = [];

    /** @var callable|null */
    private $notFoundHandler = null;

    public function get(string $path, callable|array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable|array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function put(string $path, callable|array $handler): void
    {
        $this->add('PUT', $path, $handler);
    }

    public function delete(string $path, callable|array $handler): void
    {
        $this->add('DELETE', $path, $handler);
    }

    public function patch(string $path, callable|array $handler): void
    {
        $this->add('PATCH', $path, $handler);
    }

    public function any(array $methods, string $path, callable|array $handler): void
    {
        foreach ($methods as $method) {
            $this->add(strtoupper($method), $path, $handler);
        }
    }

    public function setNotFoundHandler(callable $handler): void
    {
        $this->notFoundHandler = $handler;
    }

    public function add(string $method, string $path, callable|array $handler): void
    {
        $method = strtoupper($method);
        $names = [];
        $pattern = preg_replace_callback('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', static function (array $m) use (&$names): string {
            $names[] = $m[1];

            return '([^/]+)';
        }, $path);

        $pattern = '#^' . rtrim((string) $pattern, '/') . '/?$#';
        if ($path === '/' || $path === '') {
            $pattern = '#^/?$#';
        }

        $this->routes[$method][] = [
            'pattern' => $pattern,
            'names' => $names,
            'handler' => $handler,
        ];
    }

    /**
     * Resolve and invoke the matching route handler. Returns whatever the
     * handler returns (usually null, since controllers emit via Response).
     */
    public function dispatch(Request $request): mixed
    {
        $method = $request->method();
        // Allow HTML forms to submit PUT/PATCH/DELETE via _method override.
        if ($method === 'POST') {
            $override = strtoupper((string) $request->input('_method', ''));
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = $override;
            }
        }

        $path = rtrim($request->path(), '/');
        if ($path === '') {
            $path = '/';
        }

        foreach ($this->routes[$method] ?? [] as $route) {
            if (
                preg_match($route['pattern'], $path === '/' ? '' : $path, $matches) === 1
                || preg_match($route['pattern'], $path, $matches) === 1
            ) {
                array_shift($matches);
                $params = [];
                foreach ($route['names'] as $index => $name) {
                    $params[$name] = $matches[$index] ?? null;
                }

                return $this->invoke($route['handler'], $request, $params);
            }
        }

        if ($this->notFoundHandler !== null) {
            return ($this->notFoundHandler)($request);
        }

        Response::notFound();

        return null;
    }

    /**
     * @param array<string,mixed> $params
     */
    private function invoke(callable|array $handler, Request $request, array $params): mixed
    {
        if (is_array($handler) && isset($handler[0], $handler[1]) && is_string($handler[0])) {
            $controller = new $handler[0]();
            $method = $handler[1];

            return $controller->{$method}($request, $params);
        }

        return $handler($request, $params);
    }
}
