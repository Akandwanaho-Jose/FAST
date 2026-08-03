<?php

declare(strict_types=1);

namespace FastWebsite\Core;

use Throwable;

final class Application
{
    public function __construct(
        private readonly Router $router,
        private readonly ExceptionHandler $exceptions
    ) {
    }

    public function handle(Request $request): Response
    {
        try {
            $response = $this->router->dispatch($request);
        } catch (Throwable $exception) {
            $response = $this->exceptions->render($exception, $request);
        }

        return $this->applySecurityHeaders($response);
    }

    private function applySecurityHeaders(Response $response): Response
    {
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
            'Content-Security-Policy' => "default-src 'self'; "
                . "base-uri 'self'; form-action 'self'; frame-ancestors 'self'; "
                . "img-src 'self' data:; style-src 'self'; script-src 'self'",
        ];

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}

