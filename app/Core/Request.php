<?php

declare(strict_types=1);

namespace FastWebsite\Core;

final class Request
{
    /**
     * @param array<string, string> $query
     * @param array<string, mixed> $body
     * @param array<string, array<string, mixed>> $files
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query = [],
        private readonly string $basePath = '',
        private readonly array $body = [],
        private readonly string $ipAddress = '',
        private readonly string $userAgent = '',
        private readonly array $routeParameters = [],
        private readonly array $files = []
    ) {
    }

    public static function capture(): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = (string) (parse_url($uri, PHP_URL_PATH) ?: '/');
        $scriptName = str_replace(
            '\\',
            '/',
            (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php')
        );
        $basePath = rtrim(dirname($scriptName), '/.');

        if ($basePath !== '' && stripos($path, $basePath) === 0) {
            $path = substr($path, strlen($basePath)) ?: '/';
        }

        $path = '/' . ltrim($path, '/');

        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        /** @var array<string, string> $query */
        $query = array_filter(
            $_GET,
            static fn (mixed $value): bool => is_string($value)
        );

        /** @var array<string, mixed> $body */
        $body = array_filter(
            $_POST,
            static fn (mixed $value): bool => is_string($value) || is_array($value)
        );

        /** @var array<string, array<string, mixed>> $files */
        $files = array_filter(
            $_FILES,
            static fn (mixed $value): bool => is_array($value)
        );

        return new self(
            $method,
            $path,
            $query,
            $basePath,
            $body,
            substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45),
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 1000),
            [],
            $files
        );
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function query(string $key, ?string $default = null): ?string
    {
        return $this->query[$key] ?? $default;
    }

    public function baseUrl(): string
    {
        return $this->basePath === '' ? '/' : $this->basePath . '/';
    }

    public function input(string $key, ?string $default = null): ?string
    {
        $value = $this->body[$key] ?? null;
        return is_string($value) ? $value : $default;
    }

    /** @return array<mixed> */
    public function arrayInput(string $key): array
    {
        $value = $this->body[$key] ?? null;
        return is_array($value) ? $value : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function ipAddress(): string
    {
        return $this->ipAddress;
    }

    public function userAgent(): string
    {
        return $this->userAgent;
    }

    public function route(string $key, ?string $default = null): ?string
    {
        return $this->routeParameters[$key] ?? $default;
    }

    /**
     * @param array<string, string> $parameters
     */
    public function withRouteParameters(array $parameters): self
    {
        return new self(
            $this->method,
            $this->path,
            $this->query,
            $this->basePath,
            $this->body,
            $this->ipAddress,
            $this->userAgent,
            $parameters,
            $this->files
        );
    }
}
