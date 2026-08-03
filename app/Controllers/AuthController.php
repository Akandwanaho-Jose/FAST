<?php

declare(strict_types=1);

namespace FastWebsite\Controllers;

use FastWebsite\Core\Controller;
use FastWebsite\Core\Csrf;
use FastWebsite\Core\Request;
use FastWebsite\Core\Response;
use FastWebsite\Core\Session;
use FastWebsite\Services\AuthService;
use FastWebsite\Validation\PasswordPolicy;

final class AuthController extends Controller
{
    public function __construct(
        \FastWebsite\Core\View $view,
        private readonly AuthService $auth,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly PasswordPolicy $passwordPolicy
    ) {
        parent::__construct($view);
    }

    public function loginForm(Request $request): Response
    {
        $user = $this->auth->user();

        if ($user !== null) {
            return Response::redirect(
                $request->baseUrl()
                . ((int) $user['must_change_password'] === 1
                    ? 'password/change'
                    : 'admin')
            );
        }

        return $this->renderLogin($request);
    }

    public function login(Request $request): Response
    {
        if (!$this->csrf->validate($request->input('_token'))) {
            return $this->csrfFailure($request);
        }

        $email = trim((string) $request->input('email', ''));
        $password = (string) $request->input('password', '');

        if ($email === ''
            || $password === ''
            || strlen($email) > 190
            || filter_var($email, FILTER_VALIDATE_EMAIL) === false
        ) {
            return $this->renderLogin(
                $request,
                'The email address or password is incorrect.',
                $email
            );
        }

        $result = $this->auth->attempt(
            $email,
            $password,
            $request->ipAddress(),
            $request->userAgent()
        );

        if (!$result->successful || $result->user === null) {
            return $this->renderLogin(
                $request,
                'The email address or password is incorrect.',
                $email
            );
        }

        $this->csrf->rotate();

        return Response::redirect(
            $request->baseUrl()
            . ((int) $result->user['must_change_password'] === 1
                ? 'password/change'
                : 'admin')
        );
    }

    public function passwordForm(Request $request): Response
    {
        $user = $this->auth->user();

        if ($user === null) {
            return Response::redirect($request->baseUrl() . 'login');
        }

        return $this->renderPasswordForm($request, $user);
    }

    public function changePassword(Request $request): Response
    {
        $user = $this->auth->user();

        if ($user === null) {
            return Response::redirect($request->baseUrl() . 'login');
        }

        if (!$this->csrf->validate($request->input('_token'))) {
            return $this->csrfFailure($request);
        }

        $currentPassword = (string) $request->input('current_password', '');
        $newPassword = (string) $request->input('new_password', '');
        $confirmation = (string) $request->input('new_password_confirmation', '');
        $errors = $this->passwordPolicy->validate($newPassword);

        if ($newPassword !== $confirmation) {
            $errors[] = 'The new password confirmation does not match.';
        }

        if ($currentPassword === '') {
            $errors[] = 'Enter your current password.';
        }

        if ($errors !== []) {
            return $this->renderPasswordForm($request, $user, $errors);
        }

        if (!$this->auth->changePassword(
            $user,
            $currentPassword,
            $newPassword,
            $request->ipAddress(),
            $request->userAgent()
        )) {
            return $this->renderPasswordForm(
                $request,
                $user,
                ['The current password is incorrect.']
            );
        }

        $this->csrf->rotate();
        $this->session->put(
            '_flash_success',
            'Your password has been changed securely.'
        );

        return Response::redirect($request->baseUrl() . 'admin');
    }

    public function logout(Request $request): Response
    {
        if (!$this->csrf->validate($request->input('_token'))) {
            return $this->csrfFailure($request);
        }

        $this->auth->logout($request->ipAddress(), $request->userAgent());

        return Response::redirect($request->baseUrl() . 'login');
    }

    private function renderLogin(
        Request $request,
        ?string $error = null,
        string $email = ''
    ): Response {
        return $this->view(
            'auth/login',
            [
                'pageTitle' => 'Administrator login',
                'metaDescription' => 'Secure FAST website administration login.',
                'currentPath' => $request->path(),
                'baseUrl' => $request->baseUrl(),
                'csrfToken' => $this->csrf->token(),
                'error' => $error,
                'email' => $email,
            ]
        )->withHeader('Cache-Control', 'no-store');
    }

    /**
     * @param array<string, mixed> $user
     * @param list<string> $errors
     */
    private function renderPasswordForm(
        Request $request,
        array $user,
        array $errors = []
    ): Response {
        return $this->view(
            'auth/change-password',
            [
                'pageTitle' => 'Change password',
                'metaDescription' => 'Secure FAST administrator password change.',
                'currentPath' => $request->path(),
                'baseUrl' => $request->baseUrl(),
                'csrfToken' => $this->csrf->token(),
                'user' => $user,
                'errors' => $errors,
                'firstLogin' => (int) $user['must_change_password'] === 1,
            ]
        )->withHeader('Cache-Control', 'no-store');
    }

    private function csrfFailure(Request $request): Response
    {
        return $this->view(
            'errors/403',
            [
                'pageTitle' => 'Session expired',
                'metaDescription' => 'The secure form session expired.',
                'baseUrl' => $request->baseUrl(),
            ],
            403
        )->withHeader('Cache-Control', 'no-store');
    }
}
