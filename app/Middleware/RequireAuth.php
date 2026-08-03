<?php

declare(strict_types=1);

namespace FastWebsite\Middleware;

use FastWebsite\Core\Request;
use FastWebsite\Core\Response;
use FastWebsite\Services\AuthService;

final class RequireAuth
{
    public function __construct(private readonly AuthService $auth)
    {
    }

    /**
     * @param callable(Request): Response $next
     */
    public function handle(Request $request, callable $next): Response
    {
        if ($this->auth->user() === null) {
            return Response::redirect($request->baseUrl() . 'login');
        }

        return $next($request);
    }
}

