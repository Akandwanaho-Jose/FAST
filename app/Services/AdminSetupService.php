<?php

declare(strict_types=1);

namespace FastWebsite\Services;

use FastWebsite\Validation\PasswordPolicy;
use PDO;
use RuntimeException;
use Throwable;

final class AdminSetupService
{
    public function __construct(
        private readonly PDO $connection,
        private readonly PasswordPolicy $passwordPolicy,
        private readonly AuditService $audit
    ) {
    }

    public function isAvailable(): bool
    {
        $userCount = (int) $this->connection
            ->query('SELECT COUNT(*) FROM users')
            ->fetchColumn();
        $roleStatement = $this->connection->prepare(
            'SELECT COUNT(*) FROM roles WHERE code = :code'
        );
        $roleStatement->execute(['code' => 'super_admin']);

        return $userCount === 0 && (int) $roleStatement->fetchColumn() === 1;
    }

    public function create(string $name, string $email, string $temporaryPassword): int
    {
        $name = trim($name);
        $email = strtolower(trim($email));

        if (strlen($name) < 2 || strlen($name) > 200) {
            throw new RuntimeException('Administrator name must be 2 to 200 characters.');
        }

        if (strlen($email) > 190
            || filter_var($email, FILTER_VALIDATE_EMAIL) === false
        ) {
            throw new RuntimeException('Administrator email address is invalid.');
        }

        $passwordErrors = $this->passwordPolicy->validate($temporaryPassword);

        if ($passwordErrors !== []) {
            throw new RuntimeException(implode(' ', $passwordErrors));
        }

        $this->connection->beginTransaction();

        try {
            if (!$this->isAvailable()) {
                throw new RuntimeException(
                    'Administrator setup is disabled because a user already exists '
                    . 'or the required role is unavailable.'
                );
            }

            $roleStatement = $this->connection->prepare(
                'SELECT id FROM roles WHERE code = :code LIMIT 1'
            );
            $roleStatement->execute(['code' => 'super_admin']);
            $roleId = $roleStatement->fetchColumn();

            if ($roleId === false) {
                throw new RuntimeException('The Super Administrator role is unavailable.');
            }

            $userStatement = $this->connection->prepare(
                'INSERT INTO users
                    (name, email, password_hash, is_active, must_change_password)
                 VALUES
                    (:name, :email, :password_hash, 1, 1)'
            );
            $userStatement->execute([
                'name' => $name,
                'email' => $email,
                'password_hash' => password_hash(
                    $temporaryPassword,
                    PASSWORD_DEFAULT
                ),
            ]);
            $userId = (int) $this->connection->lastInsertId();

            $assignment = $this->connection->prepare(
                'INSERT INTO user_roles (user_id, role_id, assigned_by)
                 VALUES (:user_id, :role_id, :assigned_by)'
            );
            $assignment->execute([
                'user_id' => $userId,
                'role_id' => (int) $roleId,
                'assigned_by' => $userId,
            ]);

            $this->audit->record(
                $userId,
                'auth.admin_created',
                'user',
                $userId,
                '127.0.0.1',
                'FAST CLI administrator setup',
                ['must_change_password' => true]
            );
            $this->connection->commit();

            return $userId;
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }
    }
}
