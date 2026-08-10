<?php

declare(strict_types=1);

use FastWebsite\Core\Database;
use FastWebsite\Repositories\DepartmentRepository;
use FastWebsite\Repositories\StaffRepository;
use FastWebsite\Repositories\UserRepository;
use FastWebsite\Services\AuditService;
use FastWebsite\Services\AuthorizationService;
use FastWebsite\Services\DepartmentScopeService;
use FastWebsite\Services\StaffService;
use FastWebsite\Validation\StaffValidator;

return static function (): void {
    /** @var Database $database */
    $database = require dirname(__DIR__, 2) . '/bootstrap/database.php';
    $connection = $database->connection();
    $suffix = bin2hex(random_bytes(6));
    $connection->beginTransaction();

    try {
        $userId = (int) $connection->query(
            'SELECT id FROM users WHERE is_active = 1 AND deleted_at IS NULL
             ORDER BY id LIMIT 1'
        )->fetchColumn();
        $facultyId = (int) $connection->query(
            'SELECT id FROM faculties WHERE deleted_at IS NULL ORDER BY id LIMIT 1'
        )->fetchColumn();
        $departmentId = (int) $connection->query(
            'SELECT id FROM departments WHERE deleted_at IS NULL ORDER BY id LIMIT 1'
        )->fetchColumn();
        $positionId = (int) $connection->query(
            'SELECT id FROM positions WHERE code = "head_of_department" LIMIT 1'
        )->fetchColumn();

        if (min($userId, $facultyId, $departmentId, $positionId) < 1) {
            throw new RuntimeException('Staff flow prerequisites are missing.');
        }

        $users = new UserRepository($database);
        $authorization = new AuthorizationService($users);
        $scope = new DepartmentScopeService($users, $authorization);
        $repository = new StaffRepository($database, $scope);
        $validator = new StaffValidator($repository);
        $service = new StaffService(
            $repository,
            $authorization,
            $scope,
            new AuditService($database)
        );
        $input = [
            'faculty_id' => (string) $facultyId,
            'department_id' => (string) $departmentId,
            'position_id' => (string) $positionId,
            'title_override' => '',
            'staff_number' => 'TEST-' . $suffix,
            'honorific_title' => 'Dr',
            'first_name' => 'Transactional',
            'middle_name' => '',
            'last_name' => 'Head',
            'post_nominals' => 'PhD',
            'slug' => 'transactional-head-' . $suffix,
            'staff_category' => 'academic',
            'short_biography' => 'Published only for a rolled-back integration test.',
            'biography' => 'Full transactional biography.',
            'research_summary' => '',
            'teaching_summary' => '',
            'supervision_interests' => '',
            'institutional_email' => 'staff-' . $suffix . '@example.invalid',
            'alternative_email' => '',
            'public_phone' => '',
            'profile_media_id' => '',
            'office_location_id' => '',
            'office_room' => 'TF01',
            'consultation_hours' => '',
            'supervision_available' => '1',
            'display_order' => '1',
        ];
        $validated = $validator->validate($input);
        if ($validated['errors'] !== []) {
            throw new RuntimeException(
                'Valid staff profile rejected: ' . json_encode($validated['errors'])
            );
        }

        $id = $service->create(
            $validated['data'],
            $validated['assignment'],
            $userId,
            '127.0.0.11',
            'FAST staff integration test'
        );
        $profile = $repository->findAdmin($id, $userId);
        if (!is_array($profile) || $profile['status'] !== 'draft'
            || (int) $profile['department_id'] !== $departmentId
            || $profile['office_room'] !== 'TF01'
        ) {
            throw new RuntimeException('Draft staff profile or assignment was not stored.');
        }

        $service->publishDraft(
            $id,
            $userId,
            'Published from the completed draft',
            '127.0.0.11',
            'FAST staff integration test'
        );
        $published = $repository->findPublishedBySlug($input['slug']);
        $departments = new DepartmentRepository($database, $scope);
        $head = $departments->findPublishedHead($departmentId);
        if (!is_array($published) || !is_array($head) || (int) $head['id'] !== $id) {
            throw new RuntimeException(
                'Published staff profile did not power the department HOD relationship.'
            );
        }

        $liveData = $validated['data'];
        $liveData['short_biography'] = 'Updated while the profile remained published.';
        $service->updatePublished(
            $id,
            $liveData,
            $validated['assignment'],
            $userId,
            'Live profile correction',
            '127.0.0.11',
            'FAST staff integration test'
        );
        $liveUpdated = $repository->findPublishedBySlug($input['slug']);

        if (!is_array($liveUpdated)
            || $liveUpdated['status'] !== 'published'
            || $liveUpdated['short_biography'] !== $liveData['short_biography']
        ) {
            throw new RuntimeException(
                'Published staff changes did not remain live and public.'
            );
        }

        if ($service->transition(
            $id, 'archived', $userId, 'Test archive',
            '127.0.0.11', 'FAST staff integration test'
        ) !== 'archived'
            || $repository->findPublishedBySlug($input['slug']) !== null
        ) {
            throw new RuntimeException('Archived staff remained public.');
        }
        if ($service->transition(
            $id, 'restored', $userId, 'Test restore',
            '127.0.0.11', 'FAST staff integration test'
        ) !== 'draft') {
            throw new RuntimeException('Archived staff did not restore to draft.');
        }

        $expertise = $connection->prepare(
            'INSERT INTO expertise_areas (name, slug, status)
             VALUES (:name, :slug, "active")'
        );
        $expertise->execute([
            'name' => 'Transactional Expertise ' . $suffix,
            'slug' => 'transactional-expertise-' . $suffix,
        ]);
        $expertiseId = (int) $connection->lastInsertId();

        $directData = $validated['data'];
        $directData['staff_number'] = 'DIRECT-' . $suffix;
        $directData['slug'] = 'directly-published-staff-' . $suffix;
        $directData['institutional_email'] = 'direct-' . $suffix . '@example.invalid';
        $directAssignment = $validated['assignment'];
        $directAssignment['position_id'] = null;
        $directId = $service->createAndPublish(
            $directData,
            $directAssignment,
            $userId,
            '127.0.0.11',
            'FAST staff integration test',
            [
                'expertise_ids' => [$expertiseId],
                'qualifications' => [[
                    'qualification' => 'PhD',
                    'field_of_study' => 'Transactional Systems',
                    'institution' => 'FAST Test University',
                    'country' => null,
                    'completion_year' => null,
                ]],
                'links' => [[
                    'link_type' => 'orcid',
                    'label' => 'Test ORCID',
                    'url' => 'https://example.invalid/orcid/' . $suffix,
                ]],
            ]
        );

        if ($directId < 1
            || $repository->findPublishedBySlug($directData['slug']) === null
        ) {
            throw new RuntimeException(
                'Create-and-publish did not create a public staff profile.'
            );
        }

        $filtered = $repository->paginatePublished(
            'Transactional',
            $departmentId,
            'academic',
            $expertiseId,
            1
        );
        if ($filtered['total'] !== 1
            || (int) $filtered['items'][0]['id'] !== $directId
            || !str_contains((string) $filtered['items'][0]['expertise_names'], $suffix)
        ) {
            throw new RuntimeException('Combined public staff filters did not return the expected profile.');
        }
        if (count($repository->publicQualifications($directId)) !== 1
            || count($repository->publicExpertise($directId)) !== 1
            || count($repository->publicLinks($directId)) !== 1
        ) {
            throw new RuntimeException('Public staff profile metadata was not retrievable.');
        }
    } finally {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
    }
};
