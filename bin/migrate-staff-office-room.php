<?php

declare(strict_types=1);

use FastWebsite\Core\Database;

/** @var Database $database */
$database = require dirname(__DIR__) . '/bootstrap/database.php';
$connection = $database->connection();
$statement = $connection->prepare(
    'SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "staff"
       AND COLUMN_NAME = "office_room"'
);
$statement->execute();

if ((int) $statement->fetchColumn() === 0) {
    $connection->exec(
        'ALTER TABLE staff ADD office_room VARCHAR(120) NULL AFTER office_location_id'
    );
    echo "Added staff.office_room.\n";
} else {
    echo "staff.office_room already exists.\n";
}
