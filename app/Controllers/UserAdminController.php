<?php

declare(strict_types=1);

namespace FastWebsite\Controllers;

use FastWebsite\Core\Controller;
use FastWebsite\Core\Csrf;
use FastWebsite\Core\HttpException;
use FastWebsite\Core\Request;
use FastWebsite\Core\Response;
use FastWebsite\Core\Session;
use FastWebsite\Core\View;
use FastWebsite\Repositories\UserRepository;
use FastWebsite\Services\AdminPageContext;
use FastWebsite\Services\AuthService;
use FastWebsite\Services\AuthorizationService;
use FastWebsite\Services\DepartmentScopeService;
use FastWebsite\Services\UserService;
use FastWebsite\Validation\UserValidator;

final class UserAdminController extends Controller
{
    public function __construct(
        View $view,
        private readonly AuthService $auth,
        private readonly AuthorizationService $authorization,
        private readonly DepartmentScopeService $scope,
        private readonly AdminPageContext $context,
        private readonly UserRepository $users,
        private readonly UserValidator $validator,
        private readonly UserService $service,
        private readonly Csrf $csrf,
        private readonly Session $session
    ) {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        $user = $this->user();
        $this->assert((int) $user['id']);
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $roleFilter = mb_substr(trim((string) $request->query('role', '')), 0, 100);
        $page = ctype_digit((string) $request->query('page', '1'))
            ? max(1, (int) $request->query('page', '1'))
            : 1;

        return $this->adminView($request, $user, 'admin/users/index', [
            'pageTitle' => 'Users',
            'result' => $this->users->paginateAdmin($search, $roleFilter, $page),
            'search' => $search,
            'roleFilter' => $roleFilter,
            'roles' => $this->users->allRoles(),
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $this->user();
        $this->assert((int) $user['id']);

        return $this->form($request, $user);
    }

    public function store(Request $request): Response
    {
        $user = $this->user();
        $this->assert((int) $user['id']);
        $this->csrf($request);
        $values = $this->values($request);
        $validation = $this->validator->validate($values);

        if ($validation['errors'] !== []) {
            return $this->form($request, $user, $values, $validation['errors']);
        }

        $this->service->create(
            $validation['data'],
            $validation['roleIds'],
            (int) $user['id'],
            $request->ipAddress(),
            $request->userAgent()
        );
        $this->session->put('_flash_success', 'User created. A password setup email has been sent.');

        return Response::redirect($request->baseUrl() . 'admin/users');
    }

    public function edit(Request $request): Response
    {
        $user = $this->user();
        $this->assert((int) $user['id']);
        $id = $this->id($request);
        $existing = $this->users->findAdminById($id);

        if ($existing === null) {
            throw new HttpException(404, 'User not found.');
        }

        return $this->form($request, $user, [
            'name' => $existing['name'],
            'email' => $existing['email'],
            'role_ids' => $this->users->roleIdsForUser($id),
        ], [], $id);
    }

    public function update(Request $request): Response
    {
        $user = $this->user();
        $this->assert((int) $user['id']);
        $this->csrf($request);
        $id = $this->id($request);

        if ($this->users->findAdminById($id) === null) {
            throw new HttpException(404, 'User not found.');
        }

        $values = $this->values($request);
        $validation = $this->validator->validate($values, $id);

        if ($validation['errors'] !== []) {
            return $this->form($request, $user, $values, $validation['errors'], $id);
        }

        $this->service->update(
            $id,
            $validation['data'],
            $validation['roleIds'],
            (int) $user['id'],
            $request->ipAddress(),
            $request->userAgent()
        );
        $this->session->put('_flash_success', 'User updated.');

        return Response::redirect($request->baseUrl() . 'admin/users');
    }

    /** @param array<string,mixed> $user @param array<string,mixed> $values @param list<string> $errors */
    private function form(
        Request $request,
        array $user,
        array $values = [],
        array $errors = [],
        ?int $id = null
    ): Response {
        return $this->adminView($request, $user, 'admin/users/form', [
            'pageTitle' => $id === null ? 'Add user' : 'Edit user',
            'userId' => $id,
            'values' => $values,
            'errors' => $errors,
            'roles' => $this->users->allRoles(),
        ]);
    }

    /** @param array<string,mixed> $user @param array<string,mixed> $data */
    private function adminView(Request $request, array $user, string $template, array $data): Response
    {
        return $this->view(
            $template,
            array_merge($this->context->data($request, $user), $data),
            200,
            'layouts/admin'
        )->withHeader('Cache-Control', 'no-store');
    }

    /** @return array<string,mixed> */
    private function user(): array
    {
        return $this->auth->user() ?? throw new HttpException(403, 'Authentication required.');
    }

    private function id(Request $request): int
    {
        $value = (string) $request->route('id', '');
        if (!ctype_digit($value) || (int) $value < 1) {
            throw new HttpException(404, 'User not found.');
        }
        return (int) $value;
    }

    /** @return array<string,mixed> */
    private function values(Request $request): array
    {
        return [
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'role_ids' => $request->arrayInput('role_ids'),
        ];
    }

    private function assert(int $userId): void
    {
        if (!$this->authorization->can($userId, 'users.manage') || !$this->scope->hasGlobalScope($userId)) {
            throw new HttpException(403, 'This user management action is not allowed.');
        }
    }

    private function csrf(Request $request): void
    {
        if (!$this->csrf->validate($request->input('_token'))) {
            throw new HttpException(403, 'The secure form session expired.');
        }
    }
}
