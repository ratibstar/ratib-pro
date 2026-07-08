<?php

declare(strict_types=1);

namespace Rateb\PlatformCatalog\Core;

final class Router
{
    private ?Container $container = null;

    /** @var array<int, callable(string,string):bool> */
    private array $middleware = [];

    /** @var array<int, array{method:string,pattern:string,handler:callable|array|\Closure}> */
    private array $routes = [];

    public function setContainer(Container $container): void
    {
        $this->container = $container;
    }

    /** @param callable(string,string):bool $middleware */
    public function addMiddleware(callable $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    /** @param callable|array{0:class-string|object,1:string}|\Closure $handler */
    public function get(string $pattern, $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    /** @param callable|array{0:class-string|object,1:string}|\Closure $handler */
    public function post(string $pattern, $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    /** @param callable|array{0:class-string|object,1:string}|\Closure $handler */
    public function put(string $pattern, $handler): void
    {
        $this->add('PUT', $pattern, $handler);
    }

    /** @param callable|array{0:class-string|object,1:string}|\Closure $handler */
    public function patch(string $pattern, $handler): void
    {
        $this->add('PATCH', $pattern, $handler);
    }

    /** @param callable|array{0:class-string|object,1:string}|\Closure $handler */
    public function delete(string $pattern, $handler): void
    {
        $this->add('DELETE', $pattern, $handler);
    }

    /** @param callable|array{0:class-string|object,1:string}|\Closure $handler */
    public function add(string $method, string $pattern, $handler): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }

    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method);
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $path = rtrim($path, '/') ?: '/';

        foreach ($this->middleware as $middleware) {
            if (!$middleware($method, $path)) {
                return;
            }
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $regex = '#^' . preg_replace_callback(
                '#\{([a-zA-Z_][a-zA-Z0-9_]*)(?::(\.\+))?\}#',
                static function (array $m): string {
                    if (($m[2] ?? '') === '.+') {
                        return '(?P<' . $m[1] . '>.+)';
                    }

                    return '(?P<' . $m[1] . '>[^/]+)';
                },
                $route['pattern']
            ) . '$#';

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

            if ($handler instanceof \Closure) {
                $handler($params);

                return;
            }

            if (is_array($handler) && isset($handler[0], $handler[1]) && is_string($handler[0])) {
                if ($this->container instanceof Container) {
                    $handler[0] = $this->container->make($handler[0]);
                } else {
                    $handler[0] = new $handler[0]();
                }
            }

            self::invokeHandler($handler, $params);

            return;
        }

        http_response_code(404);
        if (str_starts_with($path, '/catalog/')) {
            Response::json([
                'data' => null,
                'meta' => [],
                'errors' => [['message' => 'Not found']],
            ], 404);

            return;
        }

        View::render('errors/404', ['title' => '404'], null);
    }

    /** @param callable|array{0:object,1:string} $handler @param array<string,mixed> $params */
    private static function invokeHandler($handler, array $params): void
    {
        if ($params === []) {
            call_user_func($handler);

            return;
        }

        if (!is_array($handler) || !isset($handler[1]) || !is_string($handler[1])) {
            call_user_func($handler, $params);

            return;
        }

        try {
            $ref = new \ReflectionMethod($handler[0], $handler[1]);
        } catch (\ReflectionException) {
            call_user_func($handler, $params);

            return;
        }

        $required = $ref->getNumberOfRequiredParameters();
        if ($required === 0 && $ref->getNumberOfParameters() === 0) {
            call_user_func($handler);

            return;
        }

        if ($required <= 1 && $ref->getNumberOfParameters() === 1) {
            $first = $ref->getParameters()[0];
            $type = $first->getType();
            if ($type instanceof \ReflectionNamedType && $type->getName() === 'array') {
                call_user_func($handler, $params);

                return;
            }
        }

        call_user_func_array($handler, array_values($params));
    }
}
