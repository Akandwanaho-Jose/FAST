<?php

declare(strict_types=1);

$database = require dirname(__DIR__) . '/bootstrap/database.php';
$connection = $database->connection();
$result = [
    'programmes' => [],
    'published_programmes' => 0,
    'programme_revisions' => 0,
    'programme_workflow_actions' => 0,
    'current_curriculum_versions' => 0,
];

foreach ($connection->query(
    'SELECT status, COUNT(*) AS total FROM programmes
     WHERE deleted_at IS NULL GROUP BY status ORDER BY status'
)->fetchAll() as $row) {
    $result['programmes'][(string) $row['status']] = (int) $row['total'];
}
$result['published_programmes'] = (int) $connection->query(
    'SELECT COUNT(*) FROM programmes WHERE status = "published"
     AND published_at IS NOT NULL AND published_at <= NOW() AND deleted_at IS NULL'
)->fetchColumn();
$result['programme_revisions'] = (int) $connection->query(
    'SELECT COUNT(*) FROM content_revisions WHERE entity_type = "programme"'
)->fetchColumn();
$result['programme_workflow_actions'] = (int) $connection->query(
    'SELECT COUNT(*) FROM content_approvals WHERE entity_type = "programme"'
)->fetchColumn();
$result['current_curriculum_versions'] = (int) $connection->query(
    'SELECT COUNT(*) FROM curriculum_versions WHERE is_current = 1'
)->fetchColumn();

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
