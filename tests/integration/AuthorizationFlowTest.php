<?php

declare(strict_types=1);

use FastWebsite\Core\Database;
use FastWebsite\Repositories\DashboardRepository;
use FastWebsite\Repositories\UserRepository;
use FastWebsite\Services\AdminNavigation;
use FastWebsite\Services\AuthorizationService;
use FastWebsite\Services\DepartmentScopeService;

return static function (): void {
    /** @var Database $database */
    $database = require dirname(__DIR__, 2) . '/bootstrap/database.php';
    $connection = $database->connection();
    $suffix = bin2hex(random_bytes(6));
    $connection->beginTransaction();

    try {
        $department = $connection->query(
            'SELECT id, name FROM departments
             WHERE deleted_at IS NULL
             ORDER BY id
             LIMIT 1'
        )->fetch();

        if (!is_array($department)) {
            throw new RuntimeException('Authorization test requires one department.');
        }

        $insertUser = $connection->prepare(
            'INSERT INTO users
                (name, email, password_hash, is_active, must_change_password)
             VALUES
                (:name, :email, :password_hash, 1, 0)'
        );
        $insertUser->execute([
            'name' => 'Phase 3 Authorization Test',
            'email' => 'phase3-' . $suffix . '@example.invalid',
            'password_hash' => password_hash(
                'TransactionalTest#123',
                PASSWORD_DEFAULT
            ),
        ]);
        $userId = (int) $connection->lastInsertId();

        $insertRole = $connection->prepare(
            'INSERT INTO roles (name, code, description, is_system_role)
             VALUES (:name, :code, :description, 0)'
        );
        $insertRole->execute([
            'name' => 'Phase 3 Test ' . $suffix,
            'code' => 'phase3_test_' . $suffix,
            'description' => 'Transactional authorization test role.',
        ]);
        $roleId = (int) $connection->lastInsertId();
        $connection->prepare(
            'INSERT INTO user_roles (user_id, role_id)
             VALUES (:user_id, :role_id)'
        )->execute(['user_id' => $userId, 'role_id' => $roleId]);

        $assignPermission = $connection->prepare(
            'INSERT INTO role_permissions (role_id, permission_id)
             SELECT :role_id, id
             FROM permissions
             WHERE code = :code'
        );

        foreach (['dashboard.view', 'departments.view'] as $permission) {
            $assignPermission->execute([
                'role_id' => $roleId,
                'code' => $permission,
            ]);

            if ($assignPermission->rowCount() !== 1) {
                throw new RuntimeException(
                    sprintf('Required permission %s does not exist.', $permission)
                );
            }
        }

        $connection->prepare(
            'INSERT INTO user_departments
                (user_id, department_id, access_level)
             VALUES
                (:user_id, :department_id, "edit")'
        )->execute([
            'user_id' => $userId,
            'department_id' => (int) $department['id'],
        ]);

        $users = new UserRepository($database);
        $authorization = new AuthorizationService($users);
        $scope = new DepartmentScopeService($users, $authorization);

        if (!$authorization->can($userId, 'dashboard.view')
            || !$authorization->can($userId, 'departments.view')
            || $authorization->can($userId, 'users.manage')
        ) {
            throw new RuntimeException('Database role permissions were not enforced.');
        }

        if ($scope->hasGlobalScope($userId)
            || !$scope->canAccess($userId, (int) $department['id'], 'edit')
            || $scope->canAccess($userId, (int) $department['id'], 'publish')
        ) {
            throw new RuntimeException('Department access level was not enforced.');
        }

        $visibleKeys = array_column(
            (new AdminNavigation())->visible($userId, $authorization),
            'key'
        );

        if ($visibleKeys !== ['dashboard', 'departments']) {
            throw new RuntimeException(
                'Navigation did not match effective permissions: '
                . json_encode($visibleKeys)
            );
        }

        $dashboard = new DashboardRepository(
            $database,
            $authorization,
            $scope
        );
        $counts = $dashboard->counts($userId);

        if (count($counts) !== 1
            || $counts[0]['label'] !== 'Departments'
            || $counts[0]['value'] !== 1
        ) {
            throw new RuntimeException(
                'Dashboard counts were not permission/scope filtered: '
                . json_encode($counts)
            );
        }

        $recent = $dashboard->recentContent($userId);

        if (count($recent) !== 1
            || $recent[0]['type'] !== 'Department'
            || $recent[0]['title'] !== $department['name']
        ) {
            throw new RuntimeException(
                'Recent content escaped department scope: '
                . json_encode($recent)
            );
        }

        foreach ([
            'settings.manage',
            'staff.view',
            'programmes.view',
            'research.view',
            'news.view',
            'events.manage',
            'pages.manage',
            'documents.manage',
            'content.review',
            'content.approve',
        ] as $permission) {
            $assignPermission->execute([
                'role_id' => $roleId,
                'code' => $permission,
            ]);

            if ($assignPermission->rowCount() !== 1) {
                throw new RuntimeException(
                    sprintf('Required permission %s does not exist.', $permission)
                );
            }
        }

        $freshAuthorization = new AuthorizationService($users);
        $freshScope = new DepartmentScopeService($users, $freshAuthorization);

        if (!$freshScope->hasGlobalScope($userId)) {
            throw new RuntimeException(
                'The database global-scope permission was not honoured.'
            );
        }

        $globalDashboard = new DashboardRepository(
            $database,
            $freshAuthorization,
            $freshScope
        );
        $globalCounts = $globalDashboard->counts($userId);
        $globalDashboard->recentContent($userId);
        $globalDashboard->workflowQueue($userId, 'draft');
        $globalDashboard->workflowQueue($userId, 'under_review');

        if (count($globalCounts) !== 10) {
            throw new RuntimeException(
                'Global dashboard did not return all permitted count groups.'
            );
        }
    } finally {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
    }
};
