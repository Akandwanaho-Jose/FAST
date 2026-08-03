<?php

declare(strict_types=1);

use FastWebsite\Core\Database;

require dirname(__DIR__) . '/bootstrap/autoload.php';

try {
    /** @var Database $database */
    $database = require dirname(__DIR__) . '/bootstrap/database.php';
    $connection = $database->connection();
    $counts = $connection->query(
        'SELECT COUNT(*) AS total,
                SUM(CASE WHEN deleted_at IS NULL THEN 1 ELSE 0 END) AS non_deleted,
                SUM(
                    CASE
                        WHEN deleted_at IS NULL AND is_active = 1 THEN 1
                        ELSE 0
                    END
                ) AS active
         FROM users'
    )->fetch();

    if (!is_array($counts)) {
        throw new RuntimeException('Application user counts could not be read.');
    }

    $result = [
        'database_account' => (string) $connection
            ->query('SELECT CURRENT_USER()')
            ->fetchColumn(),
        'application_users' => [
            'total' => (int) $counts['total'],
            'non_deleted' => (int) $counts['non_deleted'],
            'active' => (int) $counts['active'],
        ],
    ];

    fwrite(
        STDOUT,
        json_encode(
            $result,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ) . PHP_EOL
    );
    exit(0);
} catch (Throwable) {
    fwrite(STDERR, "Application user check could not complete.\n");
    exit(1);
}

