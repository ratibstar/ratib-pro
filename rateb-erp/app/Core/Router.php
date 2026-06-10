<?php
declare(strict_types=1);

namespace Rateb\App\Core;

final class Router
{
    /** @var array<int, array{method:string,pattern:string,handler:callable,middleware:array<int,string>}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
    }

    public function post(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    public function put(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->add('PUT', $pattern, $handler, $middleware);
    }

    public function delete(string $pattern, callable $handler, array $middleware = []): void
    {
        $this->add('DELETE', $pattern, $handler, $middleware);
    }

    public function add(string $method, string $pattern, callable $handler, array $middleware = []): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method);
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = rtrim($path, '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $regex = '#^' . preg_replace('#\{([a-zA-Z_]+)\}#', '(?P<$1>[^/]+)', $route['pattern']) . '$#';
            if (!preg_match($regex, $path, $matches)) {
                continue;
            }

            $params = [];
            foreach ($matches as $key => $value) {
                if (!is_int($key)) {
                    $params[$key] = $value;
                }
            }

            $handler = $route['handler'];
            foreach ($route['middleware'] as $middlewareClass) {
                $middleware = new $middlewareClass();
                if (!$middleware->handle()) {
                    return;
                }
            }

            if ($handler instanceof \Closure) {
                $handler($params);
                return;
            }

            if (is_array($handler) && isset($handler[0], $handler[1]) && is_string($handler[0])) {
                $handler[0] = new $handler[0]();
            }

            call_user_func($handler, $params);
            return;
        }

        http_response_code(404);
        if (strpos($path, '/api/') === 0) {
            Response::json(['success' => false, 'message' => 'Not found'], 404);
        }

        View::render('errors/404', ['title' => '404']);
    }
}
