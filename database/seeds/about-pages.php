<?php

declare(strict_types=1);

use FastWebsite\Core\Database;

require dirname(__DIR__, 2) . '/bootstrap/autoload.php';

/** @var Database $database */
$database = require dirname(__DIR__, 2) . '/bootstrap/database.php';
$connection = $database->connection();

$pages = [
    ['Faculty Overview', 'faculty-overview', 'An introduction to the Faculty of Applied Sciences and Technology.', 10],
    ['History of the Faculty', 'history-of-the-faculty', 'Learn about the establishment and development of FAST.', 20],
    ['Dean\'s Message', 'deans-message', 'A message from the Dean of the Faculty of Applied Sciences and Technology.', 30],
    ['Vision and Mission', 'vision-and-mission', 'The vision and mission that guide the faculty.', 40],
    ['Core Values', 'core-values', 'The values that guide teaching, research, innovation and service at FAST.', 50],
];

$statement = $connection->prepare(
    'INSERT INTO pages
        (parent_page_id, title, slug, page_template, meta_description,
         display_order, show_in_navigation, status, published_at)
     VALUES
        (NULL, :title, :slug, "standard", :description,
         :display_order, 1, "published", NOW())
     ON DUPLICATE KEY UPDATE
        title = VALUES(title),
        meta_description = COALESCE(meta_description, VALUES(meta_description)),
        display_order = VALUES(display_order),
        show_in_navigation = 1'
);

$connection->beginTransaction();

try {
    foreach ($pages as [$title, $slug, $description, $displayOrder]) {
        $statement->execute([
            'title' => $title,
            'slug' => $slug,
            'description' => $description,
            'display_order' => $displayOrder,
        ]);
    }

    $connection->commit();
    fwrite(STDOUT, "About FAST pages are ready.\n");
} catch (Throwable $exception) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }

    throw $exception;
}
