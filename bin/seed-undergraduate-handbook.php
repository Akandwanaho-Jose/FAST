<?php

declare(strict_types=1);

use FastWebsite\Core\Database;
use FastWebsite\Repositories\ProgrammeRepository;
use FastWebsite\Repositories\UserRepository;
use FastWebsite\Services\AuditService;
use FastWebsite\Services\AuthorizationService;
use FastWebsite\Services\DepartmentScopeService;
use FastWebsite\Services\ProgrammeService;
use FastWebsite\Validation\ProgrammeValidator;

require dirname(__DIR__) . '/bootstrap/autoload.php';

$apply = in_array('--apply', $argv, true);
/** @var Database $database */
$database = require dirname(__DIR__) . '/bootstrap/database.php';
$connection = $database->connection();
$users = new UserRepository($database);
$authorization = new AuthorizationService($users);
$scope = new DepartmentScopeService($users, $authorization);
$repository = new ProgrammeRepository($database, $scope);
$validator = new ProgrammeValidator($repository);
$service = new ProgrammeService(
    $repository,
    $authorization,
    new AuditService($database)
);
/** @var list<array<string,string>> $records */
$records = require dirname(__DIR__) . '/database/seeds/undergraduate-handbook-2025-2026.php';

$undergraduateLevelId = (int) $connection->query(
    'SELECT id FROM programme_levels
     WHERE code = "UG" AND is_active = 1 LIMIT 1'
)->fetchColumn();
$userId = (int) $connection->query(
    'SELECT u.id
     FROM users u
     INNER JOIN user_roles ur ON ur.user_id = u.id
     INNER JOIN role_permissions rp ON rp.role_id = ur.role_id
     INNER JOIN permissions p ON p.id = rp.permission_id
     WHERE u.is_active = 1 AND u.deleted_at IS NULL
       AND p.code IN ("settings.manage", "programmes.create")
     GROUP BY u.id
     HAVING COUNT(DISTINCT p.code) = 2
     ORDER BY u.id LIMIT 1'
)->fetchColumn();

if ($undergraduateLevelId < 1) {
    throw new RuntimeException('The active Undergraduate programme level is missing.');
}
if ($userId < 1) {
    throw new RuntimeException('No active global programme administrator is available for the seed audit trail.');
}

$result = [
    'mode' => $apply ? 'apply' : 'preview',
    'source' => 'FAST Undergraduate Handbook 2025/2026',
    'created' => [],
    'skipped' => [],
    'errors' => [],
];

if ($apply) {
    $connection->beginTransaction();
}

try {
    foreach ($records as $order => $record) {
        $department = $connection->prepare(
            'SELECT id, name FROM departments
             WHERE slug = :slug AND deleted_at IS NULL LIMIT 1'
        );
        $department->execute(['slug' => $record['department_slug']]);
        $departmentRow = $department->fetch();

        if (!is_array($departmentRow)) {
            $result['errors'][] = 'Department not found: ' . $record['department_slug'];
            continue;
        }

        $existing = $connection->prepare(
            'SELECT id, status FROM programmes
             WHERE slug = :slug AND deleted_at IS NULL LIMIT 1'
        );
        $existing->execute(['slug' => $record['slug']]);
        $existingRow = $existing->fetch();

        if (is_array($existingRow)) {
            $result['skipped'][] = [
                'name' => $record['name'],
                'reason' => 'already exists',
                'id' => (int) $existingRow['id'],
                'status' => (string) $existingRow['status'],
            ];
            continue;
        }

        $input = [
            'programme_level_id' => (string) $undergraduateLevelId,
            'department_id' => (string) $departmentRow['id'],
            'programme_code' => '',
            'name' => $record['name'],
            'award_title' => $record['award_title'],
            'slug' => $record['slug'],
            'overview' => $record['overview'],
            'why_study' => '',
            'objectives' => $record['objectives'],
            'learning_outcomes' => $record['learning_outcomes'],
            'entry_requirements' => $record['entry_requirements'],
            'career_opportunities' => '',
            'practical_training' => '',
            'duration_years' => '4',
            'duration_text' => '4 years',
            'study_mode' => 'full_time',
            'delivery_mode' => 'face_to_face',
            'accreditation' => '',
            'application_url' => '',
            'hero_media_id' => '',
            'display_order' => (string) ($order + 1),
        ];
        $validated = $validator->validate($input);

        if ($validated['errors'] !== []) {
            $result['errors'][] = [
                'name' => $record['name'],
                'validation' => $validated['errors'],
            ];
            continue;
        }

        if (!$apply) {
            $result['created'][] = [
                'name' => $record['name'],
                'department' => $departmentRow['name'],
                'status' => 'planned draft',
                'curriculum' => 'planned 2025/2026 shell',
            ];
            continue;
        }

        $id = $service->create(
            $validated['data'],
            (int) $validated['department_id'],
            $userId,
            '127.0.0.1',
            'FAST undergraduate handbook seed'
        );
        $connection->prepare(
            'UPDATE content_revisions
             SET revision_note = "Seeded from FAST Undergraduate Handbook 2025/2026"
             WHERE entity_type = "programme" AND entity_id = :id
               AND revision_number = 1'
        )->execute(['id' => $id]);
        $connection->prepare(
            'INSERT INTO curriculum_versions
                (programme_id, version_name, effective_year, expiry_year,
                 is_current, status)
             VALUES
                (:programme_id, "Undergraduate Handbook 2025/2026",
                 2025, 2026, 1, "draft")'
        )->execute(['programme_id' => $id]);

        $result['created'][] = [
            'id' => $id,
            'name' => $record['name'],
            'department' => $departmentRow['name'],
            'status' => 'draft',
            'curriculum' => '2025/2026 draft shell',
        ];
    }

    if ($result['errors'] !== []) {
        throw new RuntimeException('Seed validation failed; no changes were committed.');
    }
    if ($apply) {
        $connection->commit();
    }
} catch (Throwable $exception) {
    if ($apply && $connection->inTransaction()) {
        $connection->rollBack();
    }
    $result['fatal'] = $exception->getMessage();
    fwrite(STDERR, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    exit(1);
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
