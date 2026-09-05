<?php

declare(strict_types=1);

use FastWebsite\Core\Database;
use FastWebsite\Core\Logger;
use FastWebsite\Core\Session;
use FastWebsite\Repositories\PasswordResetRepository;
use FastWebsite\Repositories\UserRepository;
use FastWebsite\Services\AuditService;
use FastWebsite\Services\AuthService;
use FastWebsite\Services\LoginThrottle;
use FastWebsite\Services\Mailer;
use FastWebsite\Services\PasswordResetService;
use FastWebsite\Validation\PasswordPolicy;

return static function (): void {
    /** @var Database $database */
    $database = require dirname(__DIR__, 2) . '/bootstrap/database.php';
    $connection = $database->connection();
    $suffix = bin2hex(random_bytes(6));
    $connection->beginTransaction();

    try {
        $throttlePath = sys_get_temp_dir() . '/fast-password-reset-test-' . $suffix;
        $users = new UserRepository($database);
        $tokens = new PasswordResetRepository($database);
        $audit = new AuditService($database);
        // AuthService::resetPassword() calls Session::regenerate(), which
        // needs an active session to exist first (matches bootstrap/app.php
        // starting one for every real request, authenticated or not).
        $session = new Session('fast_password_reset_test', 120);
        $session->start();
        $auth = new AuthService($users, $audit, new LoginThrottle($throttlePath, 5, 900, 900), $session, 5, 900);
        $mailer = new Mailer('', '', '', new Logger(sys_get_temp_dir() . '/fast-password-reset-test-' . $suffix . '.log'));
        $throttle = new LoginThrottle($throttlePath . '-request', 3, 3600, 3600);
        $service = new PasswordResetService(
            $users,
            $tokens,
            $auth,
            $audit,
            $throttle,
            $mailer,
            new PasswordPolicy(),
            'http://localhost/FAST/public'
        );

        $email = 'password-reset-test-' . $suffix . '@example.invalid';
        $originalHash = password_hash('OriginalPass!2026xyz', PASSWORD_DEFAULT);
        $connection->prepare(
            'INSERT INTO users (name, email, password_hash, is_active, must_change_password)
             VALUES (:name, :email, :hash, 1, 0)'
        )->execute(['name' => 'Reset Test User', 'email' => $email, 'hash' => $originalHash]);
        $userId = (int) $connection->lastInsertId();

        // Requesting a reset for an email that doesn't exist must be
        // indistinguishable (from the outside) from a real one - no
        // exception, no token created, no crash.
        $service->requestReset(
            'no-such-account-' . $suffix . '@example.invalid',
            '127.0.0.11',
            'FAST password reset integration test'
        );
        $noMatchTokenCount = (int) $connection->query(
            'SELECT COUNT(*) FROM password_reset_tokens'
        )->fetchColumn();
        if ($noMatchTokenCount !== 0) {
            throw new RuntimeException('requestReset() created a token for a non-existent email.');
        }

        // A real request must create exactly one valid token for that user.
        $service->requestReset($email, '127.0.0.11', 'FAST password reset integration test');
        $tokenRow = $connection->query(
            'SELECT id, user_id, token_hash, expires_at, used_at FROM password_reset_tokens
             WHERE user_id = ' . $userId
        )->fetch();
        if (!is_array($tokenRow) || $tokenRow['used_at'] !== null) {
            throw new RuntimeException('requestReset() did not create a fresh, unused token.');
        }
        // Compared against MySQL's own clock, not PHP's time() - the two
        // are not guaranteed to agree on timezone (this is exactly the bug
        // that made every token expire on creation before this was fixed).
        $dbNow = strtotime((string) $connection->query('SELECT NOW()')->fetchColumn());
        $expiresAt = strtotime((string) $tokenRow['expires_at']);
        if ($expiresAt <= $dbNow || $expiresAt > $dbNow + 3660) {
            throw new RuntimeException('Token expiry was not set to roughly one hour from now.');
        }

        // A garbage token must be rejected, not crash and not reveal
        // anything about whether a real one exists.
        $garbageResult = $service->reset(
            'not-a-real-token',
            'NewSecurePass!2026abc',
            'NewSecurePass!2026abc',
            '127.0.0.11',
            'FAST password reset integration test'
        );
        if ($garbageResult === []) {
            throw new RuntimeException('reset() accepted a token that was never issued.');
        }

        // Requesting a second reset must invalidate the first token
        // (defence in depth: only the latest emailed link should work).
        $service->requestReset($email, '127.0.0.11', 'FAST password reset integration test');
        $tokenCountAfterSecondRequest = (int) $connection->query(
            'SELECT COUNT(*) FROM password_reset_tokens WHERE user_id = ' . $userId
        )->fetchColumn();
        if ($tokenCountAfterSecondRequest !== 1) {
            throw new RuntimeException('A second reset request did not replace the first token.');
        }

        // We don't have the raw token from requestReset() (only its hash is
        // ever stored - that's the point), so issue one directly through
        // the repository to test redemption end-to-end.
        $connection->prepare('DELETE FROM password_reset_tokens WHERE user_id = :id')
            ->execute(['id' => $userId]);
        $rawToken = bin2hex(random_bytes(32));
        $tokens->create($userId, hash('sha256', $rawToken), 60);

        $mismatchResult = $service->reset(
            $rawToken,
            'NewSecurePass!2026abc',
            'DoesNotMatch!2026abc',
            '127.0.0.11',
            'FAST password reset integration test'
        );
        if ($mismatchResult === []) {
            throw new RuntimeException('reset() accepted mismatched password confirmation.');
        }

        $successResult = $service->reset(
            $rawToken,
            'NewSecurePass!2026abc',
            'NewSecurePass!2026abc',
            '127.0.0.11',
            'FAST password reset integration test'
        );
        if ($successResult !== []) {
            throw new RuntimeException('reset() rejected a valid token and matching passwords: ' . json_encode($successResult));
        }

        $updatedUser = $users->findByEmail($email);
        if (!is_array($updatedUser)
            || !password_verify('NewSecurePass!2026abc', (string) $updatedUser['password_hash'])
            || password_verify('OriginalPass!2026xyz', (string) $updatedUser['password_hash'])
        ) {
            throw new RuntimeException('reset() did not actually change the stored password.');
        }

        // The token must be single-use: redeeming it again must fail.
        $reuseResult = $service->reset(
            $rawToken,
            'AnotherPass!2026def',
            'AnotherPass!2026def',
            '127.0.0.11',
            'FAST password reset integration test'
        );
        if ($reuseResult === []) {
            throw new RuntimeException('A single-use token was redeemed a second time.');
        }
    } finally {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        if (isset($throttlePath)) {
            foreach (glob($throttlePath . '*') ?: [] as $path) {
                is_dir($path) ? @rmdir($path) : @unlink($path);
            }
            foreach (glob($throttlePath . '-request/*') ?: [] as $path) {
                @unlink($path);
            }
            @rmdir($throttlePath . '-request');
        }
    }
};
