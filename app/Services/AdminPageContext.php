<?php

declare(strict_types=1);

namespace FastWebsite\Services;

use FastWebsite\Core\Csrf;
use FastWebsite\Core\Request;
use FastWebsite\Core\Session;

final class AdminPageContext
{
    public function __construct(
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly AuthorizationService $authorization,
        private readonly DepartmentScopeService $scope,
        private readonly AdminNavigation $navigation
    ) {
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public function data(Request $request, array $user): array
    {
        $userId = (int) $user['id'];
        $success = $this->session->get('_flash_success');
        $this->session->remove('_flash_success');

        return [
            'metaDescription' => 'FAST website administration area.',
            'currentPath' => $request->path(),
            'baseUrl' => $request->baseUrl(),
            'user' => $user,
            'csrfToken' => $this->csrf->token(),
            'adminNavigation' => $this->navigation->visible(
                $userId,
                $this->authorization
            ),
            'roles' => $this->authorization->roles($userId),
            'scopeLabel' => $this->scope->hasGlobalScope($userId)
                ? 'All departments'
                : sprintf(
                    '%d assigned department%s',
                    count($this->scope->departmentIds($userId)),
                    count($this->scope->departmentIds($userId)) === 1 ? '' : 's'
                ),
            'success' => is_string($success) ? $success : null,
        ];
    }
}
