<?php

declare(strict_types=1);

namespace FastWebsite\Core;

use Throwable;

final class ExceptionHandler
{
    public function __construct(
        private readonly View $view,
        private readonly Logger $logger
    ) {
    }

    public function render(Throwable $exception, Request $request): Response
    {
        $status = $exception instanceof HttpException
            ? $exception->status()
            : 500;

        if ($status >= 500) {
            try {
                $this->logger->error(
                    $exception->getMessage(),
                    [
                        'exception' => $exception::class,
                        'path' => $request->path(),
                        'file' => $exception->getFile(),
                        'line' => $exception->getLine(),
                    ]
                );
            } catch (Throwable) {
                error_log('FAST Website: application error and log write failure.');
            }
        }

        $template = match ($status) {
            403 => 'errors/forbidden',
            404 => 'errors/404',
            409 => 'errors/conflict',
            default => 'errors/500',
        };
        $body = $this->view->render(
            $template,
            [
                'pageTitle' => match ($status) {
                    403 => 'Access denied',
                    404 => 'Page not found',
                    409 => 'Action unavailable',
                    default => 'Something went wrong',
                },
                'metaDescription' => 'FAST website error page.',
                'baseUrl' => $request->baseUrl(),
                'errorMessage' => $status === 409
                    ? $exception->getMessage()
                    : null,
            ]
        );

        return Response::html($body, $status)
            ->withHeader('Cache-Control', 'no-store');
    }
}
