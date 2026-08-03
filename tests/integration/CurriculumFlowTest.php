<?php

declare(strict_types=1);

use FastWebsite\Core\Database;
use FastWebsite\Repositories\CurriculumRepository;
use FastWebsite\Repositories\ProgrammeRepository;
use FastWebsite\Repositories\UserRepository;
use FastWebsite\Services\AuditService;
use FastWebsite\Services\AuthorizationService;
use FastWebsite\Services\CurriculumService;
use FastWebsite\Services\DepartmentScopeService;
use FastWebsite\Services\ProgrammeService;

return static function (): void {
    /** @var Database $database */
    $database = require dirname(__DIR__, 2) . '/bootstrap/database.php';
    $connection = $database->connection();
    $connection->beginTransaction();
    try {
        $suffix = bin2hex(random_bytes(5));
        $userId = (int) $connection->query('SELECT id FROM users WHERE is_active = 1 AND deleted_at IS NULL ORDER BY id LIMIT 1')->fetchColumn();
        $departmentId = (int) $connection->query('SELECT id FROM departments WHERE deleted_at IS NULL ORDER BY id LIMIT 1')->fetchColumn();
        $levelId = (int) $connection->query('SELECT id FROM programme_levels WHERE is_active = 1 ORDER BY id LIMIT 1')->fetchColumn();
        if (min($userId, $departmentId, $levelId) < 1) {
            throw new RuntimeException('Curriculum flow prerequisites are missing.');
        }
        $users = new UserRepository($database);
        $authorization = new AuthorizationService($users);
        $scope = new DepartmentScopeService($users, $authorization);
        $programmes = new ProgrammeRepository($database, $scope);
        $audit = new AuditService($database);
        $programmeService = new ProgrammeService($programmes, $authorization, $audit);
        $programmeId = $programmeService->createAndPublish([
            'programme_level_id' => $levelId,
            'programme_code' => 'CUR-' . $suffix,
            'name' => 'Curriculum Test ' . $suffix,
            'award_title' => 'Test Award',
            'slug' => 'curriculum-test-' . $suffix,
            'overview' => 'Integration test programme.',
            'why_study' => null, 'objectives' => null, 'learning_outcomes' => null,
            'entry_requirements' => null, 'career_opportunities' => null,
            'practical_training' => null, 'duration_years' => 4,
            'duration_text' => 'Four years', 'study_mode' => 'full_time',
            'delivery_mode' => 'face_to_face', 'accreditation' => null,
            'application_url' => null, 'hero_media_id' => null, 'display_order' => 0,
        ], $departmentId, $userId, '127.0.0.14', 'FAST curriculum integration test');

        $curricula = new CurriculumRepository($database);
        $service = new CurriculumService($curricula, $programmes, $authorization, $audit);
        $placementId = $service->savePlacement(
            $programmeId,
            null,
            null,
            null,
            ['version_name' => 'Test 2026', 'effective_year' => 2026, 'expiry_year' => null, 'approval_reference' => null, 'approval_date' => null],
            ['course_code' => 'TST' . random_int(1000, 9999), 'title' => 'Test Course', 'description' => null, 'default_credit_units' => '3.00', 'course_type' => 'core'],
            ['study_year' => 1, 'semester' => 1, 'requirement_type' => 'core', 'credit_units_override' => '3.00', 'display_order' => 1],
            $userId,
            '127.0.0.14',
            'FAST curriculum integration test'
        );
        $version = $curricula->versionForAdmin($programmeId);
        if (!is_array($version) || $version['status'] !== 'draft' || $placementId < 1) {
            throw new RuntimeException('Draft curriculum and placement were not created.');
        }
        $service->publish($programmeId, (int) $version['id'], $userId, '127.0.0.14', 'FAST curriculum integration test');
        $published = $curricula->publishedVersion($programmeId);
        if (!is_array($published) || count($curricula->placements((int) $published['id'], true)) !== 1) {
            throw new RuntimeException('Published curriculum is not publicly retrievable.');
        }
        $row = $curricula->placements((int) $published['id'])[0];
        $service->savePlacement(
            $programmeId,
            (int) $published['id'],
            (int) $row['placement_id'],
            (int) $row['course_id'],
            ['version_name' => 'Test 2026', 'effective_year' => 2026, 'expiry_year' => null, 'approval_reference' => 'LIVE-1', 'approval_date' => null],
            ['course_code' => $row['course_code'], 'title' => 'Updated Test Course', 'description' => null, 'default_credit_units' => '4.00', 'course_type' => 'core'],
            ['study_year' => 1, 'semester' => 2, 'requirement_type' => 'core', 'credit_units_override' => '4.00', 'display_order' => 1],
            $userId,
            '127.0.0.14',
            'FAST curriculum integration test'
        );
        $updated = $curricula->placements((int) $published['id'], true)[0];
        if ($updated['title'] !== 'Updated Test Course' || (int) $updated['semester'] !== 2 || $curricula->publishedVersion($programmeId) === null) {
            throw new RuntimeException('A published curriculum could not be edited while remaining public.');
        }
    } finally {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
    }
};
