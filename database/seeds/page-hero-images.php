<?php

declare(strict_types=1);

use FastWebsite\Core\Database;

require dirname(__DIR__, 2) . '/bootstrap/autoload.php';

/** @var Database $database */
$database = require dirname(__DIR__, 2) . '/bootstrap/database.php';
$connection = $database->connection();

$column = $connection->query(
    "SELECT 1 FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pages'
       AND COLUMN_NAME = 'hero_media_id' LIMIT 1"
)->fetchColumn();

if ($column === false) {
    $connection->exec(
        'ALTER TABLE pages ADD COLUMN hero_media_id BIGINT UNSIGNED NULL AFTER meta_description'
    );
    $connection->exec(
        'ALTER TABLE pages ADD INDEX idx_pages_hero_media_id (hero_media_id)'
    );
}

$defaultMediaId = $connection->query(
    'SELECT h.media_id
     FROM hero_slides h
     INNER JOIN media m ON m.id = h.media_id
        AND m.media_type = "image" AND m.status = "active" AND m.deleted_at IS NULL
     WHERE h.is_active = 1 ORDER BY h.display_order, h.id LIMIT 1'
)->fetchColumn();

if ($defaultMediaId === false) {
    $defaultMediaId = $connection->query(
        'SELECT id FROM media
         WHERE media_type = "image" AND status = "active" AND deleted_at IS NULL
         ORDER BY created_at DESC, id DESC LIMIT 1'
    )->fetchColumn();
}

if ($defaultMediaId !== false) {
    $statement = $connection->prepare(
        'UPDATE pages SET hero_media_id = :media
         WHERE slug IN
            ("faculty-overview", "history-of-the-faculty", "deans-message",
             "vision-and-mission", "core-values")
           AND hero_media_id IS NULL AND deleted_at IS NULL'
    );
    $statement->execute(['media' => (int) $defaultMediaId]);
}

fwrite(STDOUT, "Configurable page masthead images are ready.\n");
