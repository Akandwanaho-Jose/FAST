<?php

declare(strict_types=1);

use FastWebsite\Core\Database;

require dirname(__DIR__) . '/bootstrap/autoload.php';

/** @var Database $database */
$database = require dirname(__DIR__) . '/bootstrap/database.php';
$connection = $database->connection();
$statusRows = $connection->query(
    'SELECT status, COUNT(*) AS total
     FROM departments
     WHERE deleted_at IS NULL
     GROUP BY status
     ORDER BY status'
)->fetchAll();
$statuses = [
    'draft' => 0,
    'under_review' => 0,
    'approved' => 0,
    'published' => 0,
    'archived' => 0,
];

foreach ($statusRows as $row) {
    $statuses[(string) $row['status']] = (int) $row['total'];
}

$summary = [
    'faculties' => (int) $connection->query(
        'SELECT COUNT(*) FROM faculties WHERE deleted_at IS NULL'
    )->fetchColumn(),
    'departments' => array_sum($statuses),
    'statuses' => $statuses,
    'publicly_visible' => (int) $connection->query(
        'SELECT COUNT(*) FROM departments
         WHERE status = "published"
           AND published_at IS NOT NULL
           AND published_at <= NOW()
           AND deleted_at IS NULL'
    )->fetchColumn(),
    'department_revisions' => (int) $connection->query(
        'SELECT COUNT(*) FROM content_revisions
         WHERE entity_type = "department"'
    )->fetchColumn(),
    'department_workflow_actions' => (int) $connection->query(
        'SELECT COUNT(*) FROM content_approvals
         WHERE entity_type = "department"'
    )->fetchColumn(),
    'published_staff_profiles' => (int) $connection->query(
        'SELECT COUNT(*) FROM staff
         WHERE status = "published"
           AND published_at IS NOT NULL
           AND published_at <= NOW()
           AND deleted_at IS NULL'
    )->fetchColumn(),
    'current_published_heads' => (int) $connection->query(
        'SELECT COUNT(DISTINCT sp.department_id)
         FROM staff_positions sp
         INNER JOIN positions p
            ON p.id = sp.position_id
           AND p.code = "head_of_department"
           AND p.is_active = 1
         INNER JOIN staff s
            ON s.id = sp.staff_id
           AND s.status = "published"
           AND s.published_at IS NOT NULL
           AND s.published_at <= NOW()
           AND s.deleted_at IS NULL
         WHERE sp.is_current = 1
           AND (sp.start_date IS NULL OR sp.start_date <= CURDATE())
           AND (sp.end_date IS NULL OR sp.end_date >= CURDATE())'
    )->fetchColumn(),
];

fwrite(
    STDOUT,
    json_encode(
        $summary,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    ) . PHP_EOL
);
