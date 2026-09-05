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

    /**
     * @return array{items: list<array<string, mixed>>, total: int, pages: int, page: int}
     */
    public function paginateAdmin(string $search, string $roleFilter, int $page, int $perPage = 20): array
    {
        $conditions = ['u.deleted_at IS NULL'];
        $parameters = [];

        if ($search !== '') {
            $conditions[] = '(u.name LIKE :search_name OR u.email LIKE :search_email)';
            $parameters['search_name'] = '%' . $search . '%';
            $parameters['search_email'] = '%' . $search . '%';
        }

        if ($roleFilter !== '') {
            $conditions[] = 'EXISTS (
                SELECT 1 FROM user_roles ur2
                INNER JOIN roles r2 ON r2.id = ur2.role_id
                WHERE ur2.user_id = u.id AND r2.code = :role_filter
            )';
            $parameters['role_filter'] = $roleFilter;
        }

        $where = implode(' AND ', $conditions);
        $count = $this->connection()->prepare('SELECT COUNT(*) FROM users u WHERE ' . $where);
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $statement = $this->connection()->prepare(
            'SELECT u.id, u.name, u.email, u.is_active, u.must_change_password, u.last_login_at,
                    GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ", ") AS role_names
             FROM users u
             LEFT JOIN user_roles ur ON ur.user_id = u.id
             LEFT JOIN roles r ON r.id = ur.role_id
             WHERE ' . $where . '
             GROUP BY u.id
             ORDER BY u.name
             LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage)
        );
        $statement->execute($parameters);
        $items = $statement->fetchAll();

        return ['items' => is_array($items) ? $items : [], 'total' => $total, 'pages' => $pages, 'page' => $page];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findAdminById(int $id): ?array
    {
        $statement = $this->connection()->prepare(
            'SELECT id, name, email, is_active, must_change_password, last_login_at
             FROM users
             WHERE id = :id AND deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $user = $statement->fetch();

        return is_array($user) ? $user : null;
    }

    public function emailExistsForAdmin(string $email, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM users WHERE email = :email AND deleted_at IS NULL';
        $parameters = ['email' => strtolower(trim($email))];

        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
            $parameters['exclude_id'] = $excludeId;
        }

        $statement = $this->connection()->prepare($sql);
        $statement->execute($parameters);

        return (int) $statement->fetchColumn() > 0;
    }

    /**
     * @return list<array{id: int, code: string, name: string}>
     */
    public function allRoles(): array
    {
        $rows = $this->connection()->query('SELECT id, code, name FROM roles ORDER BY name')->fetchAll();

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'code' => (string) $row['code'],
            'name' => (string) $row['name'],
        ], is_array($rows) ? $rows : []);
    }

    /**
     * @return list<int>
     */
    public function roleIdsForUser(int $userId): array
    {
        $statement = $this->connection()->prepare('SELECT role_id FROM user_roles WHERE user_id = :user_id');
        $statement->execute(['user_id' => $userId]);

        return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
    }

    public function roleIdByCode(string $code): ?int
    {
        $statement = $this->connection()->prepare('SELECT id FROM roles WHERE code = :code LIMIT 1');
        $statement->execute(['code' => $code]);
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    public function countUsersWithRole(string $code, ?int $excludeUserId = null): int
    {
        $sql = 'SELECT COUNT(DISTINCT ur.user_id)
                FROM user_roles ur
                INNER JOIN roles r ON r.id = ur.role_id
                INNER JOIN users u ON u.id = ur.user_id
                WHERE r.code = :code AND u.deleted_at IS NULL AND u.is_active = 1';
        $parameters = ['code' => $code];

        if ($excludeUserId !== null) {
            $sql .= ' AND ur.user_id != :exclude_id';
            $parameters['exclude_id'] = $excludeUserId;
        }

        $statement = $this->connection()->prepare($sql);
        $statement->execute($parameters);

        return (int) $statement->fetchColumn();
    }

    public function insertUser(string $name, string $email, string $passwordHash): int
    {
        $statement = $this->connection()->prepare(
            'INSERT INTO users (name, email, password_hash, is_active, must_change_password)
             VALUES (:name, :email, :password_hash, 1, 1)'
        );
        $statement->execute([
            'name' => $name,
            'email' => strtolower(trim($email)),
            'password_hash' => $passwordHash,
        ]);

        return (int) $this->connection()->lastInsertId();
    }

    public function updateUser(int $id, string $name, string $email): void
    {
        $statement = $this->connection()->prepare(
            'UPDATE users SET name = :name, email = :email WHERE id = :id AND deleted_at IS NULL'
        );
        $statement->execute(['name' => $name, 'email' => strtolower(trim($email)), 'id' => $id]);
    }

    /**
     * @param list<int> $roleIds
     */
    public function syncRoles(int $userId, array $roleIds, int $assignedBy): void
    {
        $delete = $this->connection()->prepare('DELETE FROM user_roles WHERE user_id = :user_id');
        $delete->execute(['user_id' => $userId]);

        $insert = $this->connection()->prepare(
            'INSERT INTO user_roles (user_id, role_id, assigned_by) VALUES (:user_id, :role_id, :assigned_by)'
        );

        foreach (array_unique($roleIds) as $roleId) {
            $insert->execute(['user_id' => $userId, 'role_id' => $roleId, 'assigned_by' => $assignedBy]);
        }
    }

    public function connection(): PDO
    {
        return $this->database->connection();
    }
}
