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

return static function (): void {
    /** @var Database $database */
    $database = require dirname(__DIR__, 2) . '/bootstrap/database.php';
    $connection = $database->connection();
    $suffix = bin2hex(random_bytes(6));
    $connection->beginTransaction();

    try {
        $userId = (int) $connection->query(
            'SELECT id FROM users WHERE is_active = 1 AND deleted_at IS NULL ORDER BY id LIMIT 1'
        )->fetchColumn();
        $departmentId = (int) $connection->query(
            'SELECT id FROM departments WHERE deleted_at IS NULL ORDER BY id LIMIT 1'
        )->fetchColumn();
        $levelId = (int) $connection->query(
            'SELECT id FROM programme_levels WHERE is_active = 1 ORDER BY display_order, id LIMIT 1'
        )->fetchColumn();
        if (min($userId, $departmentId, $levelId) < 1) {
            throw new RuntimeException('Programme flow prerequisites are missing.');
        }

        $users = new UserRepository($database);
        $authorization = new AuthorizationService($users);
        $scope = new DepartmentScopeService($users, $authorization);
        $repository = new ProgrammeRepository($database, $scope);
        $validator = new ProgrammeValidator($repository);
        $service = new ProgrammeService($repository, $authorization, new AuditService($database));
        $input = [
            'programme_level_id' => (string) $levelId,
            'department_id' => (string) $departmentId,
            'programme_code' => 'TEST-' . $suffix,
            'name' => 'Transactional Programme ' . $suffix,
            'award_title' => 'Test Award',
            'slug' => 'transactional-programme-' . $suffix,
            'overview' => 'Published only inside a rolled-back integration test.',
            'why_study' => 'A test reason.',
            'objectives' => '',
            'learning_outcomes' => '',
            'entry_requirements' => '',
            'career_opportunities' => '',
            'practical_training' => '',
            'duration_years' => '3',
            'duration_text' => 'Three years',
            'study_mode' => 'full_time',
            'delivery_mode' => 'face_to_face',
            'accreditation' => '',
            'application_url' => 'https://example.invalid/apply',
            'hero_media_id' => '',
            'display_order' => '1',
        ];
        $validated = $validator->validate($input);
        if ($validated['errors'] !== []) {
            throw new RuntimeException('Valid programme rejected: ' . json_encode($validated['errors']));
        }

        $id = $service->create($validated['data'], $departmentId, $userId, '127.0.0.12', 'FAST programme integration test');
        $draft = $repository->findAdmin($id, $userId);
        if (!is_array($draft) || $draft['status'] !== 'draft' || (int) $draft['department_id'] !== $departmentId) {
            throw new RuntimeException('Draft programme or lead department was not stored.');
        }

        $service->publishDraft($id, $userId, 'Completed programme published', '127.0.0.12', 'FAST programme integration test');
        if ($repository->findPublishedBySlug($input['slug']) === null) {
            throw new RuntimeException('Published programme was not publicly retrievable.');
        }
        $departmentSlug = (string) $connection->query(
            'SELECT slug FROM departments WHERE id = ' . $departmentId
        )->fetchColumn();
        $filtered = $repository->paginatePublished('', '', $departmentSlug, 1);
        if (!in_array($id, array_map(static fn (array $row): int => (int) $row['id'], $filtered['items']), true)) {
            throw new RuntimeException('Department programme filter did not return the published programme.');
        }
        $undergraduate = $repository->paginatePublished('', 'undergraduate', '', 1);
        if (!in_array($id, array_map(static fn (array $row): int => (int) $row['id'], $undergraduate['items']), true)) {
            throw new RuntimeException('Undergraduate programme category did not return the published programme.');
        }

        $liveData = $validated['data'];
        $liveData['overview'] = 'Updated while the programme remained published.';
        $service->updatePublished($id, $liveData, $departmentId, $userId, 'Live correction', '127.0.0.12', 'FAST programme integration test');
        $live = $repository->findPublishedBySlug($input['slug']);
        if (!is_array($live) || $live['status'] !== 'published' || $live['overview'] !== $liveData['overview']) {
            throw new RuntimeException('Published programme changes did not remain public.');
        }

        if ($service->transition($id, 'archived', $userId, 'Test archive', '127.0.0.12', 'FAST programme integration test') !== 'archived'
            || $repository->findPublishedBySlug($input['slug']) !== null
        ) {
            throw new RuntimeException('Archived programme remained public.');
        }
        if ($service->transition($id, 'restored', $userId, 'Test restore', '127.0.0.12', 'FAST programme integration test') !== 'draft') {
            throw new RuntimeException('Archived programme did not restore to draft.');
        }

        $directData = $validated['data'];
        $directData['programme_code'] = 'DIRECT-' . $suffix;
        $directData['slug'] = 'direct-programme-' . $suffix;
        $directId = $service->createAndPublish($directData, $departmentId, $userId, '127.0.0.12', 'FAST programme integration test');
        if ($directId < 1 || $repository->findPublishedBySlug($directData['slug']) === null) {
            throw new RuntimeException('Create-and-publish did not create a public programme.');
        }
    } finally {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
    }
};
