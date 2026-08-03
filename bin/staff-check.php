<?php

declare(strict_types=1);

use FastWebsite\Core\Database;

require dirname(__DIR__) . '/bootstrap/autoload.php';

/** @var Database $database */
$database = require dirname(__DIR__) . '/bootstrap/database.php';
$connection = $database->connection();

$leadership = $connection->query(
    'SELECT p.code,
            CONCAT_WS(" ", s.honorific_title, s.first_name,
                      NULLIF(s.middle_name, ""), s.last_name) AS name,
            COALESCE(d.short_name, d.name, "Faculty-wide") AS scope
     FROM staff_positions sp
     INNER JOIN positions p ON p.id = sp.position_id
     INNER JOIN staff s ON s.id = sp.staff_id
     LEFT JOIN departments d ON d.id = sp.department_id
     WHERE sp.is_current = 1 AND s.status = "published"
       AND s.deleted_at IS NULL
       AND p.code IN ("dean", "deputy_dean", "head_of_department")
     ORDER BY FIELD(p.code, "dean", "deputy_dean", "head_of_department"), scope'
)->fetchAll();

echo json_encode([
    'published_staff' => (int) $connection->query(
        'SELECT COUNT(*) FROM staff WHERE status = "published" AND deleted_at IS NULL'
    )->fetchColumn(),
    'published_staff_with_images' => (int) $connection->query(
        'SELECT COUNT(*) FROM staff
         WHERE status = "published" AND profile_media_id IS NOT NULL
           AND deleted_at IS NULL'
    )->fetchColumn(),
    'handbook_staff_media' => (int) $connection->query(
        'SELECT COUNT(*) FROM media
         WHERE file_path LIKE "public/uploads/staff/handbook-2025-2026/%"
           AND status = "active" AND deleted_at IS NULL'
    )->fetchColumn(),
    'current_leadership' => $leadership,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
