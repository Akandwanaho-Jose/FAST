<?php

declare(strict_types=1);

use FastWebsite\Core\Database;

/** @var Database $database */
$database = require dirname(__DIR__) . '/bootstrap/database.php';
$connection = $database->connection();
$statement = $connection->prepare(
    'SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "staff"
       AND COLUMN_NAME = "user_id"'
);
$statement->execute();

if ((int) $statement->fetchColumn() === 0) {
    $connection->exec(
        'ALTER TABLE staff
            ADD user_id BIGINT UNSIGNED NULL DEFAULT NULL AFTER institutional_email,
            ADD UNIQUE KEY uq_staff_user_id (user_id),
            ADD CONSTRAINT fk_staff_user_id FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL'
    );
    echo "Added staff.user_id.\n";
} else {
    echo "staff.user_id already exists.\n";
}
