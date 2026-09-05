<?php

declare(strict_types=1);

namespace FastWebsite\Services;

use FastWebsite\Core\Session;
use FastWebsite\Repositories\UserRepository;

final class AuthService
{
    private const SESSION_USER_ID = 'auth_user_id';
    private const DUMMY_PASSWORD_HASH =
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';

    public function __construct(
        private readonly UserRepository $users,
        private readonly AuditService $audit,
        private readonly LoginThrottle $throttle,
        private readonly Session $session,
        private readonly int $maxAttempts,
        private readonly int $lockSeconds
    ) {
    }

    public function attempt(
        string $email,
        string $password,
        string $ipAddress,
        string $userAgent
    ): AuthResult {
        $email = strtolower(trim($email));
        $emailHash = hash('sha256', $email);

        if ($this->throttle->isBlocked($email, $ipAddress)) {
            $this->audit->record(
                null,
                'auth.login_blocked',
                'user',
                null,
                $ipAddress,
                $userAgent,
                ['email_hash' => $emailHash]
            );

            return AuthResult::failure();
        }

        $user = $this->users->findByEmail($email);
        $reason = 'invalid_credentials';
        $passwordMatches = password_verify(
            $password,
            $user !== null
                ? (string) $user['password_hash']
                : self::DUMMY_PASSWORD_HASH
        );

        if ($user !== null
            && $user['deleted_at'] === null
            && (int) $user['is_active'] === 1
            && !$this->isDatabaseLocked($user)
            && $passwordMatches
        ) {
            $newHash = password_needs_rehash(
                (string) $user['password_hash'],
                PASSWORD_DEFAULT
            ) ? password_hash($password, PASSWORD_DEFAULT) : null;

            $this->users->recordSuccessfulLogin((int) $user['id'], $newHash);
            $this->throttle->clear($email, $ipAddress);
            $this->session->regenerate();
            $this->session->put(self::SESSION_USER_ID, (int) $user['id']);
            $this->audit->record(
                (int) $user['id'],
                'auth.login_succeeded',
                'user',
                (int) $user['id'],
                $ipAddress,
                $userAgent
            );

            return AuthResult::success($user);
        }

        if ($user !== null) {
            if ($user['deleted_at'] !== null) {
                $reason = 'deleted';
            } elseif ((int) $user['is_active'] !== 1) {
                $reason = 'inactive';
            } elseif ($this->isDatabaseLocked($user)) {
                $reason = 'locked';
            } else {
                $this->users->recordFailedLogin(
                    (int) $user['id'],
                    $this->maxAttempts,
                    $this->lockSeconds
                );
            }
        }

        $this->throttle->recordFailure($email, $ipAddress);
        $this->audit->record(
            $user !== null ? (int) $user['id'] : null,
            'auth.login_failed',
            'user',
            $user !== null ? (int) $user['id'] : null,
            $ipAddress,
            $userAgent,
            ['reason' => $reason, 'email_hash' => $emailHash]
        );

        return AuthResult::failure();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function user(): ?array
    {
        $userId = $this->session->get(self::SESSION_USER_ID);

        if (!is_int($userId) && !ctype_digit((string) $userId)) {
            return null;
        }

        $user = $this->users->findActiveById((int) $userId);

        if ($user === null) {
            $this->session->remove(self::SESSION_USER_ID);
        }

        return $user;
    }

    public function logout(string $ipAddress, string $userAgent): void
    {
        $user = $this->user();

        if ($user !== null) {
            $this->audit->record(
                (int) $user['id'],
                'auth.logout',
                'user',
                (int) $user['id'],
                $ipAddress,
                $userAgent
            );
        }

        $this->session->destroy();
    }

    public function changePassword(
        array $user,
        string $currentPassword,
        string $newPassword,
        string $ipAddress,
        string $userAgent
    ): bool {
        if (!password_verify($currentPassword, (string) $user['password_hash'])) {
            $this->audit->record(
                (int) $user['id'],
                'auth.password_change_failed',
                'user',
                (int) $user['id'],
                $ipAddress,
                $userAgent,
                ['reason' => 'current_password_invalid']
            );

            return false;
        }

        $this->users->changePassword(
            (int) $user['id'],
            password_hash($newPassword, PASSWORD_DEFAULT)
        );
        $this->session->regenerate();
        $this->audit->record(
            (int) $user['id'],
            'auth.password_changed',
            'user',
            (int) $user['id'],
            $ipAddress,
            $userAgent
        );

        return true;
    }

    /**
     * Sets a new password without requiring the current one - used by the
     * "forgot password" flow, where a valid single-use emailed token is the
     * proof of identity instead. Reuses UserRepository::changePassword(),
     * so this also clears must_change_password, unlocks the account, and
     * invalidates the remember-me token, exactly like a normal change does.
     */
    public function resetPassword(
        int $userId,
        string $newPassword,
        string $ipAddress,
        string $userAgent
    ): void {
        $this->users->changePassword(
            $userId,
            password_hash($newPassword, PASSWORD_DEFAULT)
        );
        $this->session->regenerate();
        $this->audit->record(
            $userId,
            'auth.password_reset',
            'user',
            $userId,
            $ipAddress,
            $userAgent
        );
    }

    /**
     * @param array<string, mixed> $user
     */
    private function isDatabaseLocked(array $user): bool
    {
        if ($user['locked_until'] === null) {
            return false;
        }

        $lockedUntil = strtotime((string) $user['locked_until']);

        return $lockedUntil !== false && $lockedUntil > time();
    }
}
