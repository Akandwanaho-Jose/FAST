<?php

declare(strict_types=1);

namespace FastWebsite\Repositories;

use FastWebsite\Core\Database;
use PDO;

final class UserRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email): ?array
    {
        $statement = $this->connection()->prepare(
            'SELECT id, name, email, password_hash, is_active,
                    must_change_password, failed_login_attempts,
                    locked_until, deleted_at
             FROM users
             WHERE email = :email
             LIMIT 1'
        );
        $statement->execute(['email' => strtolower(trim($email))]);
        $user = $statement->fetch();

        return is_array($user) ? $user : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findActiveById(int $id): ?array
    {
        $statement = $this->connection()->prepare(
            'SELECT id, name, email, password_hash, is_active,
                    must_change_password, failed_login_attempts,
                    locked_until
             FROM users
             WHERE id = :id
               AND deleted_at IS NULL
               AND is_active = 1
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $user = $statement->fetch();

        return is_array($user) ? $user : null;
    }

    public function recordFailedLogin(
        int $userId,
        int $maxAttempts,
        int $lockSeconds
    ): void {
        $lockSeconds = max(1, $lockSeconds);
        $sql = sprintf(
            'UPDATE users
             SET locked_until = CASE
                    WHEN failed_login_attempts + 1 >= :max_attempts
                    THEN DATE_ADD(NOW(), INTERVAL %d SECOND)
                    ELSE locked_until
                 END,
                 failed_login_attempts = failed_login_attempts + 1
             WHERE id = :id',
            $lockSeconds
        );
        $statement = $this->connection()->prepare($sql);
        $statement->execute([
            'max_attempts' => $maxAttempts,
            'id' => $userId,
        ]);
    }

    public function recordSuccessfulLogin(int $userId, ?string $newHash = null): void
    {
        $sql = 'UPDATE users
                SET failed_login_attempts = 0,
                    locked_until = NULL,
                    last_login_at = NOW()';
        $parameters = ['id' => $userId];

        if ($newHash !== null) {
            $sql .= ', password_hash = :password_hash';
            $parameters['password_hash'] = $newHash;
        }

        $sql .= ' WHERE id = :id';
        $statement = $this->connection()->prepare($sql);
        $statement->execute($parameters);
    }

    public function changePassword(int $userId, string $passwordHash): void
    {
        $statement = $this->connection()->prepare(
            'UPDATE users
             SET password_hash = :password_hash,
                 must_change_password = 0,
                 password_changed_at = NOW(),
                 remember_token_hash = NULL,
                 failed_login_attempts = 0,
                 locked_until = NULL
             WHERE id = :id
               AND deleted_at IS NULL
               AND is_active = 1'
        );
        $statement->execute([
            'password_hash' => $passwordHash,
            'id' => $userId,
        ]);
    }

    /**
     * @return list<string>
     */
    public function permissionCodes(int $userId): array
    {
        $statement = $this->connection()->prepare(
            'SELECT DISTINCT p.code
             FROM permissions p
             INNER JOIN role_permissions rp ON rp.permission_id = p.id
             INNER JOIN user_roles ur ON ur.role_id = rp.role_id
             WHERE ur.user_id = :user_id
             ORDER BY p.code'
        );
        $statement->execute(['user_id' => $userId]);

        return array_values(array_map(
            static fn (mixed $code): string => (string) $code,
            $statement->fetchAll(PDO::FETCH_COLUMN)
        ));
    }

    /**
     * @return list<array{code: string, name: string}>
     */
    public function roles(int $userId): array
    {
        $statement = $this->connection()->prepare(
            'SELECT r.code, r.name
             FROM roles r
             INNER JOIN user_roles ur ON ur.role_id = r.id
             WHERE ur.user_id = :user_id
             ORDER BY r.name'
        );
        $statement->execute(['user_id' => $userId]);

        /** @var list<array{code: string, name: string}> $roles */
        $roles = $statement->fetchAll();

        return $roles;
    }

    /**
     * @return array<int, string>
     */
    public function departmentAccess(int $userId): array
    {
        $statement = $this->connection()->prepare(
            'SELECT department_id, access_level
             FROM user_departments
             WHERE user_id = :user_id'
        );
        $statement->execute(['user_id' => $userId]);
        $access = [];

        foreach ($statement->fetchAll() as $row) {
            $access[(int) $row['department_id']] = (string) $row['access_level'];
        }

        return $access;
    }

    private function connection(): PDO
    {
        return $this->database->connection();
    }
}
