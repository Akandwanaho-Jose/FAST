<?php

declare(strict_types=1);

namespace FastWebsite\Middleware;

use FastWebsite\Core\Request;
use FastWebsite\Core\Response;
use FastWebsite\Services\AuthService;

final class RequirePasswordChange
{
    public function __construct(private readonly AuthService $auth)
    {
    }

    /**
     * @param callable(Request): Response $next
     */
    public function handle(Request $request, callable $next): Response
    {
        $user = $this->auth->user();

        if ($user !== null && (int) $user['must_change_password'] === 1) {
            return Response::redirect($request->baseUrl() . 'password/change');
        }

        return $next($request);
    }
}

