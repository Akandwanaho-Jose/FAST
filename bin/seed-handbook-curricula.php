<?php

declare(strict_types=1);

/**
 * Preview or import the 2025/2026 handbook curricula.
 *
 * Usage:
 *   php bin/seed-handbook-curricula.php
 *   php bin/seed-handbook-curricula.php --check --resolve-bce3123=split
 *   php bin/seed-handbook-curricula.php --apply --resolve-bce3123=split
 *   php bin/seed-handbook-curricula.php --apply --publish --resolve-bce3123=split
 */

$apply = in_array('--apply', $argv, true);
$check = in_array('--check', $argv, true);
$publish = in_array('--publish', $argv, true);
$write = $apply || $check;
$resolution = '';
foreach ($argv as $argument) {
    if (str_starts_with($argument, '--resolve-bce3123=')) {
        $resolution = substr($argument, strlen('--resolve-bce3123='));
    }
}
if ($publish && !$apply && !$check) {
    fwrite(STDERR, "Use --publish with --apply or --check.\n");
    exit(1);
}

$root = dirname(__DIR__);
$handle = fopen($root . '/database/seeds/curriculum-handbook-2025-2026.csv', 'rb');
if ($handle === false) {
    throw new RuntimeException('Curriculum seed CSV is missing.');
}
$headers = fgetcsv($handle, null, ',', '"', '');
$rows = [];
while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
    if ($row !== [] && $row !== [null]) {
        $record = array_combine($headers, $row);
        if (!is_array($record)) {
            throw new RuntimeException('Malformed curriculum seed row.');
        }
        $rows[] = $record;
    }
}
fclose($handle);

$canonicalTitles = [
    'DVS1203' => 'Political Economy of Africa and Development',
    'MIE4106' => 'Finite Element Analysis in Mechanical Engineering',
    'BCE4223' => 'Entrepreneurship and Business Management',
    'BCE1104' => 'Information and Communication Technology',
    'EEE4207' => 'Final Year Project',
    'PEEM3101' => 'HSE and Management Systems',
];
$unresolved = array_values(array_filter($rows, static fn (array $row): bool =>
    $row['course_code'] === 'BCE3123' && $row['course_title'] === 'Internet of Things (IoT)'
));
$errors = [];
if ($unresolved !== [] && $resolution !== 'split') {
    $errors[] = 'BCE3123 is both Signals and Systems and Internet of Things in the handbook. Use --resolve-bce3123=split to preserve both as BCE3123 and BCE3123-IOT.';
}

$programmes = [];
$byProgramme = [];
$courseDefinitions = [];
foreach ($rows as $row) {
    if ($row['course_code'] === 'BCE3123' && $row['course_title'] === 'Internet of Things (IoT)' && $resolution === 'split') {
        $row['course_code'] = 'BCE3123-IOT';
        $row['source_note'] = trim($row['source_note'] . '; Local suffix preserves the handbook code collision.');
    }
    $row['course_title'] = $canonicalTitles[$row['course_code']] ?? $row['course_title'];
    $byProgramme[$row['programme_slug']][] = $row;
    if (!isset($courseDefinitions[$row['course_code']])) {
        $courseDefinitions[$row['course_code']] = [
            'title' => $row['course_title'],
            'credit_units' => (float) $row['credit_units'],
            'course_type' => $row['requirement_type'] === 'elective' ? 'elective' : 'core',
        ];
    } else {
        $courseDefinitions[$row['course_code']]['credit_units'] = max(
            $courseDefinitions[$row['course_code']]['credit_units'],
            (float) $row['credit_units']
        );
    }
}

$database = require $root . '/bootstrap/database.php';
$connection = $database->connection();
foreach ($connection->query('SELECT id, slug, name FROM programmes WHERE deleted_at IS NULL')->fetchAll() as $programme) {
    $programmes[$programme['slug']] = $programme;
}
foreach (array_keys($byProgramme) as $slug) {
    if (!isset($programmes[$slug])) {
        $errors[] = 'Programme not found: ' . $slug;
    }
}

$result = [
    'mode' => $check ? 'check_transaction' : ($apply ? ($publish ? 'apply_and_publish' : 'apply_drafts') : 'preview'),
    'source' => 'FAST Undergraduate Handbook 2025/2026',
    'placements' => count($rows),
    'courses' => count($courseDefinitions),
    'programmes' => array_map('count', $byProgramme),
    'resolution' => $resolution !== '' ? $resolution : null,
    'errors' => $errors,
];

if (!$write || $errors !== []) {
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
    exit($errors === [] ? 0 : 2);
}

$connection->beginTransaction();
try {
    $courseIds = [];
    foreach ($courseDefinitions as $code => $definition) {
        $find = $connection->prepare('SELECT id, status FROM courses WHERE course_code = :code AND deleted_at IS NULL LIMIT 1');
        $find->execute(['code' => $code]);
        $existing = $find->fetch();
        $status = $publish || (is_array($existing) && $existing['status'] === 'published') ? 'published' : 'draft';
        if (is_array($existing)) {
            $statement = $connection->prepare(
                'UPDATE courses SET title = :title, default_credit_units = :credits,
                        course_type = :type, status = :status WHERE id = :id'
            );
            $statement->execute([
                'title' => $definition['title'], 'credits' => $definition['credit_units'],
                'type' => $definition['course_type'], 'status' => $status, 'id' => $existing['id'],
            ]);
            $courseIds[$code] = (int) $existing['id'];
        } else {
            $slug = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $code), '-'));
            $statement = $connection->prepare(
                'INSERT INTO courses (course_code, title, slug, default_credit_units, course_type, status)
                 VALUES (:code, :title, :slug, :credits, :type, :status)'
            );
            $statement->execute([
                'code' => $code, 'title' => $definition['title'], 'slug' => $slug,
                'credits' => $definition['credit_units'], 'type' => $definition['course_type'], 'status' => $status,
            ]);
            $courseIds[$code] = (int) $connection->lastInsertId();
        }
    }

    foreach ($byProgramme as $slug => $placements) {
        $programmeId = (int) $programmes[$slug]['id'];
        $find = $connection->prepare(
            'SELECT id FROM curriculum_versions
             WHERE programme_id = :programme_id AND version_name = "Undergraduate Handbook 2025/2026"
             ORDER BY id DESC LIMIT 1'
        );
        $find->execute(['programme_id' => $programmeId]);
        $versionId = (int) $find->fetchColumn();
        if ($versionId < 1) {
            $statement = $connection->prepare(
                'INSERT INTO curriculum_versions
                    (programme_id, version_name, effective_year, status, is_current)
                 VALUES (:programme_id, "Undergraduate Handbook 2025/2026", 2025, "draft", 0)'
            );
            $statement->execute(['programme_id' => $programmeId]);
            $versionId = (int) $connection->lastInsertId();
        }
        $connection->prepare('DELETE FROM curriculum_courses WHERE curriculum_version_id = :id')->execute(['id' => $versionId]);
        $insert = $connection->prepare(
            'INSERT INTO curriculum_courses
                (curriculum_version_id, course_id, study_year, semester,
                 requirement_type, credit_units_override, display_order)
             VALUES (:version_id, :course_id, :study_year, :semester,
                     :requirement_type, :credit_units, :display_order)'
        );
        $total = 0.0;
        foreach ($placements as $placement) {
            $insert->execute([
                'version_id' => $versionId,
                'course_id' => $courseIds[$placement['course_code']],
                'study_year' => (int) $placement['study_year'],
                'semester' => (int) $placement['semester'],
                'requirement_type' => $placement['requirement_type'],
                'credit_units' => (float) $placement['credit_units'],
                'display_order' => (int) $placement['display_order'],
            ]);
            $total += (float) $placement['credit_units'];
        }
        if ($publish) {
            $connection->prepare('UPDATE curriculum_versions SET is_current = 0 WHERE programme_id = :programme_id')->execute(['programme_id' => $programmeId]);
        }
        $statement = $connection->prepare(
            'UPDATE curriculum_versions SET effective_year = 2025,
                    total_credit_units = :total, status = :status, is_current = :current
             WHERE id = :id'
        );
        $statement->execute([
            'total' => $total,
            'status' => $publish ? 'published' : 'draft',
            'current' => $publish ? 1 : 0,
            'id' => $versionId,
        ]);
    }
    if ($check) {
        $connection->rollBack();
        $result['transaction'] = 'validated and rolled back';
    } else {
        $connection->commit();
        $result['transaction'] = 'committed';
    }
} catch (Throwable $exception) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
    throw $exception;
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
