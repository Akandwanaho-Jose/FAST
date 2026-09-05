<?php

declare(strict_types=1);

use FastWebsite\Core\Database;

/** @var Database $database */
$database = require dirname(__DIR__) . '/bootstrap/database.php';
$connection = $database->connection();
$statement = $connection->prepare(
    'SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "news_media"'
);
$statement->execute();

if ((int) $statement->fetchColumn() === 0) {
    $connection->exec(
        'CREATE TABLE `news_media` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `news_id` BIGINT UNSIGNED NOT NULL,
            `media_id` BIGINT UNSIGNED NOT NULL,
            `display_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_news_media_pair` (`news_id`, `media_id`),
            KEY `idx_news_media_news_order` (`news_id`, `display_order`),
            CONSTRAINT `fk_news_media_news` FOREIGN KEY (`news_id`)
                REFERENCES `news` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_news_media_media` FOREIGN KEY (`media_id`)
                REFERENCES `media` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    echo "Created news_media.\n";
} else {
    echo "news_media already exists.\n";
}
