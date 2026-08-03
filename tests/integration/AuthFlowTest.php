<?php

declare(strict_types=1);

use FastWebsite\Core\Database;
use FastWebsite\Core\Session;
use FastWebsite\Repositories\UserRepository;
use FastWebsite\Services\AuditService;
use FastWebsite\Services\AuthService;
use FastWebsite\Services\LoginThrottle;

return static function (): void {
    /** @var Database $database */
    $database = require dirname(__DIR__, 2) . '/bootstrap/database.php';
    $connection = $database->connection();
    $email = 'phase2-' . bin2hex(random_bytes(6)) . '@example.invalid';
    $temporaryPassword = 'Temporary#12345';
    $newPassword = 'ChangedSecure#6789';
    $storage = sys_get_temp_dir()
        . DIRECTORY_SEPARATOR
        . 'fast-auth-integration-'
        . bin2hex(random_bytes(5));
    $session = new Session('fast_auth_test_' . bin2hex(random_bytes(4)), 5);
    $session->start();
    $connection->beginTransaction();

    try {
        $insert = $connection->prepare(
            'INSERT INTO users
                (name, email, password_hash, is_active, must_change_password)
             VALUES
                (:name, :email, :password_hash, 1, 1)'
        );
        $insert->execute([
            'name' => 'Phase 2 Transactional Test',
            'email' => $email,
            'password_hash' => password_hash(
                $temporaryPassword,
                PASSWORD_DEFAULT
            ),
        ]);
        $userId = (int) $connection->lastInsertId();
        $users = new UserRepository($database);
        $audit = new AuditService($database);
        $auth = new AuthService(
            $users,
            $audit,
            new LoginThrottle($storage, 5, 900, 900),
            $session,
            5,
            900
        );
        $result = $auth->attempt(
            $email,
            $temporaryPassword,
            '127.0.0.8',
            'FAST integration test'
        );

        if (!$result->successful
            || $result->user === null
            || (int) $result->user['must_change_password'] !== 1
        ) {
            throw new RuntimeException('Temporary-password login did not succeed.');
        }

        if (!$auth->changePassword(
            $result->user,
            $temporaryPassword,
            $newPassword,
            '127.0.0.8',
            'FAST integration test'
        )) {
            throw new RuntimeException('First-login password change did not succeed.');
        }

        $updated = $users->findActiveById($userId);

        if ($updated === null
            || (int) $updated['must_change_password'] !== 0
            || !password_verify($newPassword, (string) $updated['password_hash'])
        ) {
            throw new RuntimeException('Changed password was not stored securely.');
        }

        $auth->logout('127.0.0.8', 'FAST integration test');
        $auditStatement = $connection->prepare(
            'SELECT COUNT(*)
             FROM audit_logs
             WHERE user_id = :user_id
               AND action IN (
                   "auth.login_succeeded",
                   "auth.password_changed",
                   "auth.logout"
               )'
        );
        $auditStatement->execute(['user_id' => $userId]);

        if ((int) $auditStatement->fetchColumn() !== 3) {
            throw new RuntimeException('Expected authentication audit records were not written.');
        }

        $lockedEmail = 'phase2-lock-' . bin2hex(random_bytes(5)) . '@example.invalid';
        $insert->execute([
            'name' => 'Phase 2 Lock Test',
            'email' => $lockedEmail,
            'password_hash' => password_hash(
                'CorrectPassword#123',
                PASSWORD_DEFAULT
            ),
        ]);
        $lockedUserId = (int) $connection->lastInsertId();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $failedResult = $auth->attempt(
                $lockedEmail,
                'IncorrectPassword#123',
                '127.0.0.7',
                'FAST integration test'
            );

            if ($failedResult->successful) {
                throw new RuntimeException('An incorrect password was accepted.');
            }
        }

        $lockStatement = $connection->prepare(
            'SELECT failed_login_attempts,
                    locked_until IS NOT NULL
                        AND locked_until > NOW() AS is_locked
             FROM users
             WHERE id = :id'
        );
        $lockStatement->execute(['id' => $lockedUserId]);
        $lockState = $lockStatement->fetch();

        if (!is_array($lockState)
            || (int) $lockState['failed_login_attempts'] !== 5
            || (int) $lockState['is_locked'] !== 1
        ) {
            throw new RuntimeException(
                'Repeated failures did not lock the account. State: '
                . json_encode($lockState)
            );
        }

        if ($auth->attempt(
            $lockedEmail,
            'CorrectPassword#123',
            '127.0.0.7',
            'FAST integration test'
        )->successful) {
            throw new RuntimeException('A locked account was allowed to log in.');
        }
    } finally {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $session->destroy();
        }

        if (is_dir($storage)) {
            foreach (glob($storage . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }

            rmdir($storage);
        }
    }
};
