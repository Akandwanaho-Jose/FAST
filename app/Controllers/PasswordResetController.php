<?php

declare(strict_types=1);

namespace FastWebsite\Controllers;

use FastWebsite\Core\Controller;
use FastWebsite\Core\Csrf;
use FastWebsite\Core\Request;
use FastWebsite\Core\Response;
use FastWebsite\Services\PasswordResetService;

final class PasswordResetController extends Controller
{
    public function __construct(
        \FastWebsite\Core\View $view,
        private readonly PasswordResetService $service,
        private readonly Csrf $csrf
    ) {
        parent::__construct($view);
    }

    public function forgotForm(Request $request): Response
    {
        return $this->view(
            'auth/forgot-password',
            [
                'pageTitle' => 'Reset your password',
                'metaDescription' => 'Request a password reset link for FAST website access.',
                'currentPath' => $request->path(),
                'baseUrl' => $request->baseUrl(),
                'csrfToken' => $this->csrf->token(),
                'sent' => false,
            ]
        )->withHeader('Cache-Control', 'no-store');
    }

    public function forgotSubmit(Request $request): Response
    {
        if (!$this->csrf->validate($request->input('_token'))) {
            return $this->csrfFailure($request);
        }

        $email = trim((string) $request->input('email', ''));

        if ($email !== ''
            && strlen($email) <= 190
            && filter_var($email, FILTER_VALIDATE_EMAIL) !== false
        ) {
            $this->service->requestReset($email, $request->ipAddress(), $request->userAgent());
        }

        // Same response whether or not the email matched an account, or
        // whether sending succeeded - see PasswordResetService::requestReset().
        $this->csrf->rotate();

        return $this->view(
            'auth/forgot-password',
            [
                'pageTitle' => 'Reset your password',
                'metaDescription' => 'Request a password reset link for FAST website access.',
                'currentPath' => $request->path(),
                'baseUrl' => $request->baseUrl(),
                'csrfToken' => $this->csrf->token(),
                'sent' => true,
            ]
        )->withHeader('Cache-Control', 'no-store');
    }

    public function resetForm(Request $request): Response
    {
        return $this->renderReset($request, (string) $request->route('token', ''), []);
    }

    public function resetSubmit(Request $request): Response
    {
        $token = (string) $request->route('token', '');

        if (!$this->csrf->validate($request->input('_token'))) {
            return $this->csrfFailure($request);
        }

        $newPassword = (string) $request->input('new_password', '');
        $confirmation = (string) $request->input('new_password_confirmation', '');

        $errors = $this->service->reset(
            $token,
            $newPassword,
            $confirmation,
            $request->ipAddress(),
            $request->userAgent()
        );

        if ($errors !== []) {
            return $this->renderReset($request, $token, $errors);
        }

        $this->csrf->rotate();

        return $this->view(
            'auth/reset-password',
            [
                'pageTitle' => 'Password updated',
                'metaDescription' => 'Your FAST website password has been reset.',
                'currentPath' => $request->path(),
                'baseUrl' => $request->baseUrl(),
                'csrfToken' => $this->csrf->token(),
                'token' => $token,
                'errors' => [],
                'success' => true,
            ]
        )->withHeader('Cache-Control', 'no-store');
    }

    /** @param list<string> $errors */
    private function renderReset(Request $request, string $token, array $errors): Response
    {
        return $this->view(
            'auth/reset-password',
            [
                'pageTitle' => 'Choose a new password',
                'metaDescription' => 'Set a new password for your FAST website account.',
                'currentPath' => $request->path(),
                'baseUrl' => $request->baseUrl(),
                'csrfToken' => $this->csrf->token(),
                'token' => $token,
                'errors' => $errors,
                'success' => false,
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
