<?php

declare(strict_types=1);

use FastWebsite\Core\Database;
use FastWebsite\Repositories\DepartmentRepository;
use FastWebsite\Repositories\MediaRepository;
use FastWebsite\Repositories\ProgrammeRepository;
use FastWebsite\Repositories\StaffRepository;
use FastWebsite\Repositories\UserRepository;
use FastWebsite\Services\AuditService;
use FastWebsite\Services\AuthorizationService;
use FastWebsite\Services\ContentWorkflowService;
use FastWebsite\Services\DepartmentScopeService;
use FastWebsite\Services\DepartmentService;
use FastWebsite\Services\ProgrammeService;
use FastWebsite\Services\StaffService;
use FastWebsite\Validation\DepartmentValidator;
use FastWebsite\Validation\ProgrammeValidator;
use FastWebsite\Validation\StaffValidator;

require dirname(__DIR__) . '/bootstrap/autoload.php';

$check = in_array('--check', $argv, true);
$apply = in_array('--apply', $argv, true) || $check;
$planPublish = in_array('--plan-publish', $argv, true);
$publish = in_array('--publish', $argv, true) || $planPublish;

if (in_array('--publish', $argv, true) && !$apply) {
    fwrite(STDERR, "Use --publish together with --apply.\n");
    exit(1);
}

/** @var Database $database */
$database = require dirname(__DIR__) . '/bootstrap/database.php';
$connection = $database->connection();
$users = new UserRepository($database);
$authorization = new AuthorizationService($users);
$scope = new DepartmentScopeService($users, $authorization);
$audit = new AuditService($database);
$workflow = new ContentWorkflowService($authorization, $scope);
$departmentRepository = new DepartmentRepository($database, $scope);
$departmentValidator = new DepartmentValidator($departmentRepository);
$departmentService = new DepartmentService(
    $departmentRepository,
    $authorization,
    $scope,
    $workflow,
    $audit
);
$staffRepository = new StaffRepository($database, $scope);
$mediaRepository = new MediaRepository($database);
$staffValidator = new StaffValidator($staffRepository);
$staffService = new StaffService(
    $staffRepository,
    $authorization,
    $scope,
    $audit
);
$programmeRepository = new ProgrammeRepository($database, $scope);
$programmeValidator = new ProgrammeValidator($programmeRepository);
$programmeService = new ProgrammeService(
    $programmeRepository,
    $authorization,
    $audit
);

$requiredPermissions = [
    'settings.manage',
    'departments.edit',
    'departments.publish',
    'staff.create',
    'staff.edit',
    'staff.publish',
    'programmes.publish',
    'content.approve',
];
$permissionPlaceholders = implode(', ', array_fill(0, count($requiredPermissions), '?'));
$administrator = $connection->prepare(
    'SELECT u.id
     FROM users u
     INNER JOIN user_roles ur ON ur.user_id = u.id
     INNER JOIN role_permissions rp ON rp.role_id = ur.role_id
     INNER JOIN permissions p ON p.id = rp.permission_id
     WHERE u.is_active = 1 AND u.deleted_at IS NULL
       AND p.code IN (' . $permissionPlaceholders . ')
     GROUP BY u.id
     HAVING COUNT(DISTINCT p.code) = ?
     ORDER BY u.id LIMIT 1'
);
$administrator->execute([...$requiredPermissions, count($requiredPermissions)]);
$userId = (int) $administrator->fetchColumn();
$facultyId = (int) $connection->query(
    'SELECT id FROM faculties WHERE deleted_at IS NULL ORDER BY id LIMIT 1'
)->fetchColumn();

if ($userId < 1 || $facultyId < 1) {
    throw new RuntimeException('The handbook batch requires an active global publisher and faculty.');
}

$result = [
    'mode' => $check
        ? ($publish ? 'check_publish_transaction' : 'check_draft_transaction')
        : ($apply
            ? ($publish ? 'apply_and_publish' : 'apply_drafts')
            : ($planPublish ? 'preview_publish' : 'preview_drafts')),
    'source' => 'FAST Undergraduate Handbook 2025/2026',
    'departments' => ['planned' => [], 'updated' => [], 'skipped' => []],
    'staff' => ['planned' => [], 'created' => [], 'updated' => [], 'published' => [], 'skipped' => []],
    'media' => ['planned' => [], 'created' => [], 'reused' => [], 'missing' => []],
    'programmes' => ['updated' => [], 'published' => [], 'skipped' => []],
    'locations_planned' => [],
    'warnings' => [],
    'errors' => [],
];

/** @var list<array{slug:string,overview:string,hod_message:string}> $departmentSeeds */
$departmentSeeds = require dirname(__DIR__) . '/database/seeds/departments-handbook-2025-2026.php';
/** @var list<array<string,string>> $programmeSeeds */
$programmeSeeds = require dirname(__DIR__) . '/database/seeds/undergraduate-handbook-2025-2026.php';
$staffSeeds = readStaffSeeds(dirname(__DIR__) . '/database/seeds/staff-handbook-2025-2026.csv');
$staffImages = indexStaffImages(readStaffSeeds(
    dirname(__DIR__) . '/database/seeds/staff-handbook-2025-2026-images.csv'
));

foreach ($staffImages as $slug => $imageSeed) {
    $absolutePath = dirname(__DIR__) . '/' . $imageSeed['public_path'];
    if (!is_file($absolutePath)) {
        $result['errors'][] = 'Extracted staff portrait is missing: ' . $slug;
    }
}

if ($apply) {
    $connection->beginTransaction();
}

try {
    foreach ($departmentSeeds as $seed) {
        $statement = $connection->prepare(
            'SELECT * FROM departments
             WHERE slug = :slug AND deleted_at IS NULL LIMIT 1'
        );
        $statement->execute(['slug' => $seed['slug']]);
        $department = $statement->fetch();
        if (!is_array($department)) {
            $result['errors'][] = 'Department not found: ' . $seed['slug'];
            continue;
        }

        $changes = [];
        foreach (['overview', 'hod_message'] as $field) {
            if (trim((string) ($department[$field] ?? '')) !== trim($seed[$field])) {
                $department[$field] = $seed[$field];
                $changes[] = $field;
            }
        }
        if ($changes === [] && !($publish && $department['status'] === 'draft')) {
            $result['departments']['skipped'][] = [
                'id' => (int) $department['id'],
                'name' => $department['name'],
                'reason' => 'handbook fields already populated',
                'status' => $department['status'],
            ];
            continue;
        }
        if (!in_array($department['status'], ['draft', 'published'], true)) {
            $result['errors'][] = 'Department has an unsupported workflow state: ' . $department['name'];
            continue;
        }

        $input = array_map(
            static fn (mixed $value): ?string => $value === null ? null : (string) $value,
            array_intersect_key($department, array_flip([
                'faculty_id', 'name', 'short_name', 'slug', 'overview', 'history',
                'vision', 'mission', 'strategic_direction', 'hod_message', 'email',
                'phone', 'location_id', 'hero_media_id', 'display_order',
            ]))
        );
        $validated = $departmentValidator->validate($input, (int) $department['id']);
        if ($validated['errors'] !== []) {
            $result['errors'][] = ['department' => $department['name'], 'validation' => $validated['errors']];
            continue;
        }

        $plan = [
            'id' => (int) $department['id'],
            'name' => $department['name'],
            'fields' => $changes,
            'target_status' => $publish ? 'published' : $department['status'],
        ];
        if (!$apply) {
            $result['departments']['planned'][] = $plan;
            continue;
        }
        if ($department['status'] === 'published') {
            if ($changes !== []) {
                $departmentService->updatePublished(
                    (int) $department['id'],
                    $validated['data'],
                    $userId,
                    'Handbook 2025/2026 department seed',
                    '127.0.0.1',
                    'FAST handbook batch seed'
                );
            }
        } elseif ($publish) {
            $departmentService->updateAndPublish(
                (int) $department['id'],
                $validated['data'],
                $userId,
                'Handbook 2025/2026 department seed',
                'Approved handbook 2025/2026 batch',
                '127.0.0.1',
                'FAST handbook batch seed'
            );
        } else {
            $departmentService->update(
                (int) $department['id'],
                $validated['data'],
                $userId,
                'Handbook 2025/2026 department seed',
                '127.0.0.1',
                'FAST handbook batch seed'
            );
        }
        $result['departments']['updated'][] = $plan;
    }

    $positions = [];
    foreach ($connection->query('SELECT id, code FROM positions WHERE is_active = 1')->fetchAll() as $position) {
        $positions[(string) $position['code']] = (int) $position['id'];
    }
    $departments = [];
    $departmentNames = [];
    foreach ($connection->query('SELECT id, slug, name FROM departments WHERE deleted_at IS NULL')->fetchAll() as $department) {
        $departments[(string) $department['slug']] = (int) $department['id'];
        $departmentNames[(string) $department['slug']] = (string) $department['name'];
    }

    foreach ($staffSeeds as $seed) {
        $slug = slugify(trim(implode(' ', array_filter([
            $seed['first_name'], $seed['middle_name'], $seed['last_name'],
        ]))));
        $departmentId = $seed['department_slug'] !== ''
            ? ($departments[$seed['department_slug']] ?? null)
            : null;
        $positionId = $positions[$seed['position_code']] ?? null;
        $imageSeed = $staffImages[$slug] ?? null;
        $seedMediaId = null;
        if (is_array($imageSeed)) {
            if ($apply) {
                $media = findOrCreateSeedImage(
                    $connection,
                    $mediaRepository,
                    $imageSeed,
                    $userId,
                    dirname(__DIR__)
                );
                $seedMediaId = $media['id'];
                $result['media'][$media['created'] ? 'created' : 'reused'][] = $imageSeed['public_path'];
            } else {
                $result['media']['planned'][] = $imageSeed['public_path'];
            }
        } else {
            $result['media']['missing'][] = $slug;
        }
        if ($seed['department_slug'] !== '' && $departmentId === null) {
            $result['errors'][] = 'Staff department not found: ' . $seed['department_slug'];
            continue;
        }
        if ($positionId === null) {
            $result['errors'][] = 'Staff position not found: ' . $seed['position_code'];
            continue;
        }

        $existing = findExistingStaff($connection, $seed, $slug);
        if (is_array($existing)) {
            $profile = $staffRepository->findAdmin((int) $existing['id'], $userId);
            if (!is_array($profile)) {
                $result['errors'][] = 'Existing staff profile is outside seed access: ' . displayName($seed);
                continue;
            }
            if (!in_array($profile['status'], ['draft', 'published'], true)) {
                $result['errors'][] = 'Existing staff profile has an unsupported workflow state: ' . displayName($seed);
                continue;
            }
            $existingLocationId = isset($profile['office_location_id'])
                ? (int) $profile['office_location_id']
                : 0;
            if ($seed['office_room'] !== '' && $apply) {
                $existingLocationId = findOrCreateRoom($connection, $seed['office_room']);
            }
            $existingInput = [
                'faculty_id' => (string) $profile['faculty_id'],
                'department_id' => $departmentId === null ? '' : (string) $departmentId,
                'position_id' => (string) $positionId,
                'title_override' => $seed['title_override'],
                'staff_number' => (string) ($profile['staff_number'] ?? ''),
                'honorific_title' => $seed['honorific_title'] !== ''
                    ? $seed['honorific_title'] : (string) ($profile['honorific_title'] ?? ''),
                'first_name' => $seed['first_name'],
                'middle_name' => $seed['middle_name'] !== ''
                    ? $seed['middle_name'] : (string) ($profile['middle_name'] ?? ''),
                'last_name' => $seed['last_name'],
                'post_nominals' => (string) ($profile['post_nominals'] ?? ''),
                'slug' => (string) $profile['slug'],
                'staff_category' => $seed['staff_category'],
                'short_biography' => trim((string) ($profile['short_biography'] ?? '')) !== ''
                    ? (string) $profile['short_biography']
                    : displayName($seed) . ' serves as ' . ($seed['title_override'] ?: positionLabel($seed['position_code'])) . '.',
                'biography' => (string) ($profile['biography'] ?? ''),
                'research_summary' => (string) ($profile['research_summary'] ?? ''),
                'teaching_summary' => (string) ($profile['teaching_summary'] ?? ''),
                'supervision_interests' => (string) ($profile['supervision_interests'] ?? ''),
                'institutional_email' => $seed['institutional_email'] !== ''
                    ? $seed['institutional_email'] : (string) ($profile['institutional_email'] ?? ''),
                'alternative_email' => $seed['alternative_email'] !== ''
                    ? $seed['alternative_email'] : (string) ($profile['alternative_email'] ?? ''),
                'public_phone' => (string) ($profile['public_phone'] ?? ''),
                'profile_media_id' => is_array($imageSeed) && $seedMediaId !== null
                    ? (string) $seedMediaId
                    : (isset($profile['profile_media_id']) && (int) $profile['profile_media_id'] > 0
                        ? (string) $profile['profile_media_id'] : ''),
                'office_location_id' => $existingLocationId > 0 ? (string) $existingLocationId : '',
                'consultation_hours' => (string) ($profile['consultation_hours'] ?? ''),
                'supervision_available' => (int) ($profile['supervision_available'] ?? 0) === 1 ? '1' : '0',
                'display_order' => (string) ($profile['display_order'] ?? 0),
            ];
            $existingValidation = $staffValidator->validate($existingInput, (int) $profile['id']);
            if ($existingValidation['errors'] !== []) {
                $result['errors'][] = ['staff' => displayName($seed), 'validation' => $existingValidation['errors']];
                continue;
            }
            $changes = [];
            if ($seed['institutional_email'] !== ''
                && strcasecmp(trim((string) ($profile['institutional_email'] ?? '')), $seed['institutional_email']) !== 0
            ) {
                $changes[] = 'institutional_email';
            }
            if ($seed['alternative_email'] !== ''
                && strcasecmp(trim((string) ($profile['alternative_email'] ?? '')), $seed['alternative_email']) !== 0
            ) {
                $changes[] = 'alternative_email';
            }
            foreach (['honorific_title', 'first_name', 'last_name', 'staff_category'] as $field) {
                if ((string) ($profile[$field] ?? '') !== $seed[$field]) {
                    $changes[] = $field;
                }
            }
            if ($seed['middle_name'] !== ''
                && (string) ($profile['middle_name'] ?? '') !== $seed['middle_name']
            ) {
                $changes[] = 'middle_name';
            }
            if (is_array($imageSeed)
                && (string) ($profile['profile_path'] ?? '') !== $imageSeed['public_path']
            ) {
                $changes[] = 'profile_image';
            }
            if ($seed['office_room'] !== ''
                && strcasecmp(trim((string) ($profile['room'] ?? '')), $seed['office_room']) !== 0
            ) {
                $changes[] = 'office_location';
            }
            if ((int) ($profile['department_id'] ?? 0) !== (int) ($departmentId ?? 0)
                || (int) ($profile['position_id'] ?? 0) !== $positionId
                || (string) ($profile['title_override'] ?? '') !== $seed['title_override']
            ) {
                $changes[] = 'appointment';
            }
            if ($changes !== []) {
                $updatePlan = [
                    'id' => (int) $profile['id'],
                    'name' => trim(implode(' ', array_filter([
                        $profile['honorific_title'], $profile['first_name'],
                        $profile['middle_name'], $profile['last_name'],
                    ]))),
                    'fields' => $changes,
                    'role' => $seed['title_override'] ?: positionLabel($seed['position_code']),
                    'target_status' => $publish ? 'published' : $profile['status'],
                    'image' => $imageSeed['public_path'] ?? null,
                ];
                if ($apply) {
                    if ($profile['status'] === 'published') {
                        $staffService->updatePublished(
                            (int) $profile['id'],
                            $existingValidation['data'],
                            $existingValidation['assignment'],
                            $userId,
                            'Handbook 2025/2026 profile reconciliation',
                            '127.0.0.1',
                            'FAST handbook batch seed'
                        );
                    } elseif ($publish) {
                        $staffService->updateAndPublish(
                            (int) $profile['id'],
                            $existingValidation['data'],
                            $existingValidation['assignment'],
                            $userId,
                            'Handbook 2025/2026 profile reconciliation',
                            '127.0.0.1',
                            'FAST handbook batch seed'
                        );
                    } else {
                        $staffService->update(
                            (int) $profile['id'],
                            $existingValidation['data'],
                            $existingValidation['assignment'],
                            $userId,
                            'Handbook 2025/2026 profile reconciliation',
                            '127.0.0.1',
                            'FAST handbook batch seed'
                        );
                    }
                }
                $result['staff']['updated'][] = $updatePlan;
            } elseif ($publish && $profile['status'] === 'draft') {
                if ($apply) {
                    $staffService->publishDraft(
                        (int) $profile['id'], $userId,
                        'Approved handbook 2025/2026 batch',
                        '127.0.0.1', 'FAST handbook batch seed'
                    );
                }
                $result['staff']['published'][] = [
                    'id' => (int) $profile['id'],
                    'name' => displayName($seed),
                ];
            } else {
                $result['staff']['skipped'][] = [
                    'id' => (int) $existing['id'],
                    'name' => displayName($seed),
                    'status' => $profile['status'],
                ];
            }
            if ($seed['source_note'] !== '') {
                $result['warnings'][] = displayName($seed) . ': ' . $seed['source_note'];
            }
            if (!is_array($imageSeed)) {
                $result['warnings'][] = displayName($seed) . ': No portrait is supplied in the handbook table';
            }
            continue;
        }

        $locationId = null;
        if ($seed['office_room'] !== '') {
            if (!in_array($seed['office_room'], $result['locations_planned'], true)) {
                $result['locations_planned'][] = $seed['office_room'];
            }
            if ($apply) {
                $locationId = findOrCreateRoom($connection, $seed['office_room']);
            }
        }
        $role = $seed['title_override'] !== ''
            ? $seed['title_override']
            : positionLabel($seed['position_code']);
        $workplace = $seed['department_slug'] !== ''
            ? ' in the ' . $departmentNames[$seed['department_slug']]
            : ' in the Faculty of Applied Sciences and Technology';
        $input = [
            'faculty_id' => (string) $facultyId,
            'department_id' => $departmentId === null ? '' : (string) $departmentId,
            'position_id' => (string) $positionId,
            'title_override' => $seed['title_override'],
            'staff_number' => '',
            'honorific_title' => $seed['honorific_title'],
            'first_name' => $seed['first_name'],
            'middle_name' => $seed['middle_name'],
            'last_name' => $seed['last_name'],
            'post_nominals' => '',
            'slug' => $slug,
            'staff_category' => $seed['staff_category'],
            'short_biography' => displayName($seed) . ' serves as ' . $role . $workplace . '.',
            'biography' => '',
            'research_summary' => '',
            'teaching_summary' => '',
            'supervision_interests' => '',
            'institutional_email' => $seed['institutional_email'],
            'alternative_email' => $seed['alternative_email'],
            'public_phone' => '',
            'profile_media_id' => $seedMediaId === null ? '' : (string) $seedMediaId,
            'office_location_id' => $locationId === null ? '' : (string) $locationId,
            'consultation_hours' => '',
            'supervision_available' => '0',
            'display_order' => '0',
        ];
        $validated = $staffValidator->validate($input);
        if ($validated['errors'] !== []) {
            $result['errors'][] = ['staff' => displayName($seed), 'validation' => $validated['errors']];
            continue;
        }
        if ($seed['source_note'] !== '') {
            $result['warnings'][] = displayName($seed) . ': ' . $seed['source_note'];
        }
        $plan = [
            'name' => displayName($seed),
            'role' => $role,
            'department' => $seed['department_slug'] === ''
                ? 'Faculty-wide'
                : $departmentNames[$seed['department_slug']],
            'target_status' => $publish ? 'published' : 'draft',
            'image' => $imageSeed['public_path'] ?? null,
        ];
        if (!is_array($imageSeed)) {
            $result['warnings'][] = displayName($seed) . ': No portrait is supplied in the handbook table';
        }
        if (!$apply) {
            $result['staff']['planned'][] = $plan;
            continue;
        }
        $id = $publish
            ? $staffService->createAndPublish(
                $validated['data'], $validated['assignment'], $userId,
                '127.0.0.1', 'FAST handbook batch seed'
            )
            : $staffService->create(
                $validated['data'], $validated['assignment'], $userId,
                '127.0.0.1', 'FAST handbook batch seed'
            );
        $connection->prepare(
            'UPDATE content_revisions
             SET revision_note = "Seeded from FAST Undergraduate Handbook 2025/2026"
             WHERE entity_type = "staff" AND entity_id = :id
               AND revision_number = 1'
        )->execute(['id' => $id]);
        $result['staff']['created'][] = ['id' => $id, ...$plan];
    }

    foreach ($programmeSeeds as $seed) {
        $statement = $connection->prepare(
            'SELECT id, name, status FROM programmes
             WHERE slug = :slug AND deleted_at IS NULL LIMIT 1'
        );
        $statement->execute(['slug' => $seed['slug']]);
        $programme = $statement->fetch();
        if (!is_array($programme)) {
            $result['errors'][] = 'Seeded programme is missing: ' . $seed['name'];
            continue;
        }
        $profile = $programmeRepository->findAdmin((int) $programme['id'], $userId);
        $departmentId = $departments[$seed['department_slug']] ?? null;
        if (!is_array($profile) || $departmentId === null) {
            $result['errors'][] = 'Programme source reconciliation failed: ' . $seed['name'];
            continue;
        }
        if (!in_array($profile['status'], ['draft', 'published'], true)) {
            $result['errors'][] = 'Programme has an unsupported workflow state: ' . $seed['name'];
            continue;
        }
        $input = [
            'programme_level_id' => (string) $profile['programme_level_id'],
            'department_id' => (string) $departmentId,
            'programme_code' => (string) ($profile['programme_code'] ?? ''),
            'name' => $seed['name'],
            'award_title' => $seed['award_title'],
            'slug' => (string) $profile['slug'],
            'overview' => $seed['overview'],
            'why_study' => (string) ($profile['why_study'] ?? ''),
            'objectives' => $seed['objectives'],
            'learning_outcomes' => $seed['learning_outcomes'],
            'entry_requirements' => $seed['entry_requirements'],
            'career_opportunities' => (string) ($profile['career_opportunities'] ?? ''),
            'practical_training' => (string) ($profile['practical_training'] ?? ''),
            'duration_years' => (string) ($profile['duration_years'] ?? ''),
            'duration_text' => (string) ($profile['duration_text'] ?? ''),
            'study_mode' => (string) $profile['study_mode'],
            'delivery_mode' => (string) $profile['delivery_mode'],
            'accreditation' => (string) ($profile['accreditation'] ?? ''),
            'application_url' => (string) ($profile['application_url'] ?? ''),
            'hero_media_id' => isset($profile['hero_media_id']) && (int) $profile['hero_media_id'] > 0
                ? (string) $profile['hero_media_id'] : '',
            'display_order' => (string) $profile['display_order'],
        ];
        $validated = $programmeValidator->validate($input, (int) $programme['id']);
        if ($validated['errors'] !== []) {
            $result['errors'][] = ['programme' => $seed['name'], 'validation' => $validated['errors']];
            continue;
        }
        $changes = [];
        foreach (['name', 'award_title', 'overview', 'objectives', 'learning_outcomes', 'entry_requirements'] as $field) {
            if (trim((string) ($profile[$field] ?? '')) !== trim($seed[$field])) {
                $changes[] = $field;
            }
        }
        if ((int) ($profile['department_id'] ?? 0) !== $departmentId) {
            $changes[] = 'lead_department';
        }
        if ($changes !== []) {
            if ($apply && $profile['status'] === 'published') {
                $programmeService->updatePublished(
                    (int) $programme['id'], $validated['data'], $departmentId, $userId,
                    'Handbook 2025/2026 source-of-truth reconciliation',
                    '127.0.0.1', 'FAST handbook batch seed'
                );
            } elseif ($apply && $publish) {
                $programmeService->updateAndPublish(
                    (int) $programme['id'], $validated['data'], $departmentId, $userId,
                    'Handbook 2025/2026 source-of-truth reconciliation',
                    '127.0.0.1', 'FAST handbook batch seed'
                );
            } elseif ($apply) {
                $programmeService->update(
                    (int) $programme['id'], $validated['data'], $departmentId, $userId,
                    'Handbook 2025/2026 source-of-truth reconciliation',
                    '127.0.0.1', 'FAST handbook batch seed'
                );
            }
            $result['programmes']['updated'][] = [
                'id' => (int) $programme['id'],
                'name' => $seed['name'],
                'fields' => $changes,
                'target_status' => $publish ? 'published' : $profile['status'],
            ];
            if ($publish && $profile['status'] === 'draft') {
                $result['programmes']['published'][] = [
                    'id' => (int) $programme['id'], 'name' => $seed['name'],
                ];
            }
        } elseif ($publish && $programme['status'] === 'draft') {
            if ($apply) {
                $programmeService->publishDraft(
                    (int) $programme['id'], $userId,
                    'Approved handbook 2025/2026 batch',
                    '127.0.0.1', 'FAST handbook batch seed'
                );
            }
            $result['programmes']['published'][] = [
                'id' => (int) $programme['id'],
                'name' => $programme['name'],
            ];
        } elseif ($changes === []) {
            $result['programmes']['skipped'][] = [
                'id' => (int) $programme['id'],
                'name' => $programme['name'],
                'status' => $programme['status'],
            ];
        }
    }

    if ($result['errors'] !== []) {
        throw new RuntimeException('Handbook batch validation failed; no changes were committed.');
    }
    if ($check) {
        $connection->rollBack();
        $result['rolled_back'] = true;
    } elseif ($apply) {
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

/** @return list<array<string,string>> */
function readStaffSeeds(string $path): array
{
    $handle = fopen($path, 'rb');
    if ($handle === false) {
        throw new RuntimeException('The handbook staff seed file could not be opened.');
    }
    $headers = fgetcsv($handle, null, ',', '"', '');
    if (!is_array($headers)) {
        throw new RuntimeException('The handbook staff seed header is missing.');
    }
    $records = [];
    while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
        if ($row === [null] || $row === []) {
            continue;
        }
        if (count($row) !== count($headers)) {
            throw new RuntimeException('A handbook staff seed row has the wrong column count.');
        }
        $records[] = array_combine($headers, array_map(
            static fn (?string $value): string => trim((string) $value),
            $row
        ));
    }
    fclose($handle);
    return $records;
}

/**
 * @param list<array<string,string>> $records
 * @return array<string,array<string,string>>
 */
function indexStaffImages(array $records): array
{
    $images = [];
    foreach ($records as $record) {
        $slug = $record['staff_slug'] ?? '';
        if ($slug === '' || isset($images[$slug])) {
            throw new RuntimeException('A handbook portrait has a missing or duplicate staff slug.');
        }
        $expectedPrefix = 'public/uploads/staff/handbook-2025-2026/';
        if (!str_starts_with($record['public_path'] ?? '', $expectedPrefix)
            || pathinfo($record['public_path'], PATHINFO_EXTENSION) !== 'webp'
        ) {
            throw new RuntimeException('A handbook portrait path is outside the approved upload directory.');
        }
        $images[$slug] = $record;
    }
    return $images;
}

/**
 * @param array<string,string> $seed
 * @return array{id:int,created:bool}
 */
function findOrCreateSeedImage(
    PDO $connection,
    MediaRepository $repository,
    array $seed,
    int $userId,
    string $root
): array {
    $statement = $connection->prepare(
        'SELECT id FROM media
         WHERE file_path = :path AND media_type = "image"
           AND status = "active" AND deleted_at IS NULL LIMIT 1'
    );
    $statement->execute(['path' => $seed['public_path']]);
    $id = (int) $statement->fetchColumn();
    if ($id > 0) {
        return ['id' => $id, 'created' => false];
    }

    $absolutePath = $root . '/' . $seed['public_path'];
    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($absolutePath);
    $dimensions = getimagesize($absolutePath);
    if ($mimeType !== 'image/webp' || !is_array($dimensions)) {
        throw new RuntimeException('An extracted handbook portrait is not a valid WebP image.');
    }
    $size = filesize($absolutePath);
    if ($size === false || $size < 1 || $size > 5 * 1024 * 1024) {
        throw new RuntimeException('An extracted handbook portrait exceeds the upload size policy.');
    }
    $storedName = basename($seed['public_path']);
    $id = $repository->createImage([
        'original_name' => 'FAST handbook - ' . $seed['staff_slug'] . '.webp',
        'stored_name' => $storedName,
        'file_path' => $seed['public_path'],
        'mime_type' => $mimeType,
        'file_extension' => 'webp',
        'file_size' => $size,
        'width' => (int) $dimensions[0],
        'height' => (int) $dimensions[1],
        'alt_text' => $seed['alt_text'],
        'uploaded_by' => $userId,
    ]);
    return ['id' => $id, 'created' => true];
}

/** @param array<string,string> $seed @return array<string,mixed>|null */
function findExistingStaff(PDO $connection, array $seed, string $slug): ?array
{
    $conditions = ['slug = :slug'];
    $parameters = ['slug' => $slug];
    if ($seed['institutional_email'] !== '') {
        $conditions[] = 'institutional_email = :institutional_email';
        $parameters['institutional_email'] = $seed['institutional_email'];
    }
    if ($seed['alternative_email'] !== '') {
        $conditions[] = 'alternative_email = :alternative_email';
        $parameters['alternative_email'] = $seed['alternative_email'];
    }
    $statement = $connection->prepare(
        'SELECT id, status FROM staff WHERE deleted_at IS NULL AND ('
        . implode(' OR ', $conditions) . ') LIMIT 1'
    );
    $statement->execute($parameters);
    $row = $statement->fetch();
    return is_array($row) ? $row : null;
}

function findOrCreateRoom(PDO $connection, string $room): int
{
    $statement = $connection->prepare(
        'SELECT id FROM locations
         WHERE campus IS NULL AND building IS NULL AND floor IS NULL
           AND room = :room LIMIT 1'
    );
    $statement->execute(['room' => $room]);
    $id = (int) $statement->fetchColumn();
    if ($id > 0) {
        return $id;
    }
    $connection->prepare('INSERT INTO locations (room) VALUES (:room)')->execute(['room' => $room]);
    return (int) $connection->lastInsertId();
}

/** @param array<string,string> $seed */
function displayName(array $seed): string
{
    return trim(implode(' ', array_filter([
        $seed['honorific_title'], $seed['first_name'],
        $seed['middle_name'], $seed['last_name'],
    ])));
}

function positionLabel(string $code): string
{
    return ucwords(str_replace('_', ' ', $code));
}

function slugify(string $value): string
{
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    $slug = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $ascii ?: $value));
    return trim($slug, '-');
}
