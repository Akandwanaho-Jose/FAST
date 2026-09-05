<?php

declare(strict_types=1);

use FastWebsite\Core\Database;

/** @var Database $database */
$database = require dirname(__DIR__) . '/bootstrap/database.php';
$connection = $database->connection();
$statement = $connection->prepare(
    'SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "password_reset_tokens"'
);
$statement->execute();

if ((int) $statement->fetchColumn() === 0) {
    $connection->exec(
        'CREATE TABLE `password_reset_tokens` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` BIGINT UNSIGNED NOT NULL,
            `token_hash` CHAR(64) NOT NULL,
            `expires_at` DATETIME NOT NULL,
            `used_at` DATETIME NULL DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_password_reset_tokens_hash` (`token_hash`),
            KEY `idx_password_reset_tokens_user` (`user_id`),
            CONSTRAINT `fk_password_reset_tokens_user` FOREIGN KEY (`user_id`)
                REFERENCES `users` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    echo "Created password_reset_tokens.\n";
} else {
    echo "password_reset_tokens already exists.\n";
}
