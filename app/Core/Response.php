<?php

declare(strict_types=1);

namespace FastWebsite\Core;

final class Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly string $body,
        private readonly int $status = 200,
        private array $headers = []
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self(
            $body,
            $status,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }

    public static function redirect(string $location, int $status = 303): self
    {
        return new self('', $status, ['Location' => $location]);
    }

    public function withHeader(string $name, string $value): self
    {
        $response = clone $this;
        $response->headers[$name] = $value;

        return $response;
    }

    public function send(): void
    {
        header_remove('X-Powered-By');
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header(sprintf('%s: %s', $name, $value), true);
        }

        echo $this->body;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function status(): int
    {
        return $this->status;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }
}
