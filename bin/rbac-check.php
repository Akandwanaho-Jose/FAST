<?php

declare(strict_types=1);

use FastWebsite\Core\Database;

require dirname(__DIR__) . '/bootstrap/autoload.php';

/** @var Database $database */
$database = require dirname(__DIR__) . '/bootstrap/database.php';
$statement = $database->connection()->query(
    'SELECT
        COUNT(DISTINCT u.id) AS active_users,
        COUNT(DISTINCT ur.role_id) AS assigned_roles,
        COUNT(DISTINCT rp.permission_id) AS effective_permissions
     FROM users u
     LEFT JOIN user_roles ur ON ur.user_id = u.id
     LEFT JOIN role_permissions rp ON rp.role_id = ur.role_id
     WHERE u.deleted_at IS NULL
       AND u.is_active = 1'
);
$summary = $statement->fetch();
$permissionCodes = $database->connection()->query(
    'SELECT code FROM permissions ORDER BY code'
)->fetchAll(PDO::FETCH_COLUMN);

fwrite(
    STDOUT,
    json_encode(
        [
            'active_users' => (int) ($summary['active_users'] ?? 0),
            'assigned_roles' => (int) ($summary['assigned_roles'] ?? 0),
            'effective_permissions' => (int) (
                $summary['effective_permissions'] ?? 0
            ),
            'permission_codes' => $permissionCodes,
        ],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    ) . PHP_EOL
);
