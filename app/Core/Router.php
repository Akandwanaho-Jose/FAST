<?php

declare(strict_types=1);

namespace FastWebsite\Core;

use Closure;

final class Router
{
    /**
     * @var array<string, array<string, callable(Request): Response>>
     */
    private array $routes = [];

    /**
     * @param callable(Request): Response $handler
     */
    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$this->normalizePath($path)] = $handler;
    }

    /**
     * @param callable(Request): Response $handler
     */
    public function post(string $path, callable $handler): void
    {
        $this->routes['POST'][$this->normalizePath($path)] = $handler;
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->method() === 'HEAD' ? 'GET' : $request->method();
        $handler = $this->routes[$method][$request->path()] ?? null;

        if ($handler !== null) {
            return Closure::fromCallable($handler)($request);
        }

        foreach ($this->routes[$method] ?? [] as $route => $candidate) {
            if (!str_contains($route, '{')) {
                continue;
            }

            [$pattern, $parameterNames] = $this->compilePattern($route);

            if (preg_match($pattern, $request->path(), $matches) !== 1) {
                continue;
            }

            $parameters = [];

            foreach ($parameterNames as $name) {
                $parameters[$name] = rawurldecode((string) $matches[$name]);
            }

            return Closure::fromCallable($candidate)(
                $request->withRouteParameters($parameters)
            );
        }

        throw new HttpException(404, 'The requested page was not found.');
    }

    private function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');

        return $path === '/' ? $path : rtrim($path, '/');
    }

    /**
     * @return array{string, list<string>}
     */
    private function compilePattern(string $route): array
    {
        $parameterNames = [];
        $quoted = preg_quote($route, '#');
        $pattern = preg_replace_callback(
            '/\\\\\{([A-Za-z_][A-Za-z0-9_]*)\\\\\}/',
            static function (array $matches) use (&$parameterNames): string {
                $parameterNames[] = $matches[1];

                return '(?P<' . $matches[1] . '>[^/]+)';
            },
            $quoted
        );

        return ['#^' . $pattern . '$#D', $parameterNames];
    }
}
