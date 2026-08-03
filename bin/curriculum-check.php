<?php

declare(strict_types=1);

$database = require dirname(__DIR__) . '/bootstrap/database.php';
$connection = $database->connection();
$result = [
    'courses_by_status' => [],
    'curricula' => [],
    'published_current_curricula' => 0,
    'placements' => (int) $connection->query('SELECT COUNT(*) FROM curriculum_courses')->fetchColumn(),
    'approved_code_split' => $connection->query(
        'SELECT course_code, title, status FROM courses
         WHERE course_code IN ("BCE3123", "BCE3123-IOT") ORDER BY course_code'
    )->fetchAll(),
    'duplicate_placements' => (int) $connection->query(
        'SELECT COUNT(*) FROM (
            SELECT curriculum_version_id, course_id, study_year, semester, COUNT(*) AS total
            FROM curriculum_courses
            GROUP BY curriculum_version_id, course_id, study_year, semester
            HAVING total > 1
         ) duplicate_rows'
    )->fetchColumn(),
];
foreach ($connection->query('SELECT status, COUNT(*) AS total FROM courses WHERE deleted_at IS NULL GROUP BY status') as $row) {
    $result['courses_by_status'][$row['status']] = (int) $row['total'];
}
foreach ($connection->query(
    'SELECT p.programme_code, p.name, cv.version_name, cv.status, cv.is_current,
            cv.total_credit_units, COUNT(cc.id) AS placements
     FROM curriculum_versions cv INNER JOIN programmes p ON p.id = cv.programme_id
     LEFT JOIN curriculum_courses cc ON cc.curriculum_version_id = cv.id
     GROUP BY cv.id ORDER BY p.name, cv.effective_year DESC'
) as $row) {
    $result['curricula'][] = $row;
}
$result['published_current_curricula'] = (int) $connection->query(
    'SELECT COUNT(*) FROM curriculum_versions WHERE status = "published" AND is_current = 1'
)->fetchColumn();
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
