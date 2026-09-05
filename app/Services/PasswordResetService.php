<?php

declare(strict_types=1);

namespace FastWebsite\Services;

use FastWebsite\Repositories\PasswordResetRepository;
use FastWebsite\Repositories\UserRepository;
use FastWebsite\Validation\PasswordPolicy;

/**
 * The "forgot password" workflow: request a reset link by email, redeem it
 * to set a new password. See PasswordResetController for the routes and
 * app/Views/auth/forgot-password.php / reset-password.php for the forms.
 */
final class PasswordResetService
{
    private const TOKEN_TTL_MINUTES = 60;

    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordResetRepository $tokens,
        private readonly AuthService $auth,
        private readonly AuditService $audit,
        private readonly LoginThrottle $throttle,
        private readonly Mailer $mailer,
        private readonly PasswordPolicy $passwordPolicy,
        private readonly string $appUrl
    ) {
    }

    /**
     * Deliberately has no return value: whether the email matched an
     * account, and whether sending succeeded, must never be distinguishable
     * to the caller - the controller shows the identical response either
     * way. This is what stops the form being used to discover which email
     * addresses have accounts (email enumeration).
     *
     * The emailed link is built from a fixed, admin-configured $appUrl
     * (APP_URL in .env), not from the incoming request's Host header -
     * trusting that header here would let someone forge it and get a
     * phishing link emailed out with a genuine, valid reset token attached.
     */
    public function requestReset(string $email, string $ipAddress, string $userAgent): void
    {
        $email = strtolower(trim($email));

        if ($this->throttle->isBlocked($email, $ipAddress)) {
            $this->audit->record(
                null,
                'auth.password_reset_blocked',
                'user',
                null,
                $ipAddress,
                $userAgent,
                ['email_hash' => hash('sha256', $email)]
            );

            return;
        }

        $this->throttle->recordFailure($email, $ipAddress);

        $user = $this->users->findByEmail($email);

        if ($user === null
            || $user['deleted_at'] !== null
            || (int) $user['is_active'] !== 1
        ) {
            $this->audit->record(
                null,
                'auth.password_reset_requested',
                'user',
                null,
                $ipAddress,
                $userAgent,
                ['email_hash' => hash('sha256', $email), 'matched' => false]
            );

            return;
        }

        $userId = (int) $user['id'];
        $this->tokens->deleteForUser($userId);

        $rawToken = bin2hex(random_bytes(32));
        $this->tokens->create($userId, hash('sha256', $rawToken), self::TOKEN_TTL_MINUTES);

        $link = rtrim($this->appUrl, '/') . '/password/reset/' . $rawToken;
        $this->mailer->send(
            (string) $user['email'],
            (string) $user['name'],
            'Reset your FAST website password',
            $this->emailBody((string) $user['name'], $link)
        );

        $this->audit->record(
            $userId,
            'auth.password_reset_requested',
            'user',
            $userId,
            $ipAddress,
            $userAgent,
            ['matched' => true]
        );
    }

    /**
     * @return list<string> validation/token errors; empty means success
     */
    public function reset(
        string $rawToken,
        string $newPassword,
        string $confirmation,
        string $ipAddress,
        string $userAgent
    ): array {
        $token = $this->tokens->findValid(hash('sha256', $rawToken));

        if ($token === null) {
            return ['This reset link is invalid or has expired. Request a new one.'];
        }

        $errors = $this->passwordPolicy->validate($newPassword);

        if ($newPassword !== $confirmation) {
            $errors[] = 'The new password confirmation does not match.';
        }

        if ($errors !== []) {
            return $errors;
        }

        $userId = (int) $token['user_id'];
        $this->auth->resetPassword($userId, $newPassword, $ipAddress, $userAgent);
        $this->tokens->markUsed((int) $token['id']);
        $this->tokens->deleteForUser($userId);

        return [];
    }

    private function emailBody(string $name, string $link): string
    {
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeLink = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');

        return <<<HTML
            <p>Hello {$safeName},</p>
            <p>We received a request to reset your FAST website password. Click the button below to choose a new one:</p>
            <p><a href="{$safeLink}" style="display:inline-block;padding:12px 24px;background:#214d29;color:#ffffff;text-decoration:none;border-radius:4px;">Reset your password</a></p>
            <p>Or copy this link into your browser:<br>{$safeLink}</p>
            <p>This link expires in one hour and can only be used once.</p>
            <p>If you didn't request this, you can safely ignore this email - your password will not be changed.</p>
            HTML;
    }
}
