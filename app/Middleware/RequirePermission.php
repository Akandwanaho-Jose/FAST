<?php

declare(strict_types=1);

namespace FastWebsite\Middleware;

use FastWebsite\Core\Request;
use FastWebsite\Core\Response;
use FastWebsite\Core\View;
use FastWebsite\Services\AuthService;
use FastWebsite\Services\AuthorizationService;

final class RequirePermission
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly AuthorizationService $authorization,
        private readonly View $view
    ) {
    }

    /**
     * @param callable(Request): Response $next
     */
    public function handle(
        Request $request,
        string $permission,
        callable $next
    ): Response {
        $user = $this->auth->user();

        if ($user === null) {
            return Response::redirect($request->baseUrl() . 'login');
        }

        if (!$this->authorization->can((int) $user['id'], $permission)) {
            return Response::html(
                $this->view->render(
                    'errors/forbidden',
                    [
                        'pageTitle' => 'Access denied',
                        'metaDescription' => 'You do not have permission to access this area.',
                        'baseUrl' => $request->baseUrl(),
                    ]
                ),
                403
            )->withHeader('Cache-Control', 'no-store');
        }

        return $next($request);
    }
}

