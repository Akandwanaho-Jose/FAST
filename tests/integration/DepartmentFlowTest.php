<?php

declare(strict_types=1);

use FastWebsite\Core\Database;
use FastWebsite\Core\HttpException;
use FastWebsite\Repositories\DepartmentRepository;
use FastWebsite\Repositories\UserRepository;
use FastWebsite\Services\AuditService;
use FastWebsite\Services\AuthorizationService;
use FastWebsite\Services\ContentWorkflowService;
use FastWebsite\Services\DepartmentScopeService;
use FastWebsite\Services\DepartmentService;
use FastWebsite\Validation\DepartmentValidator;

return static function (): void {
    /** @var Database $database */
    $database = require dirname(__DIR__, 2) . '/bootstrap/database.php';
    $connection = $database->connection();
    $suffix = bin2hex(random_bytes(6));
    $connection->beginTransaction();

    try {
        $facultyId = (int) $connection->query(
            'SELECT id FROM faculties
             WHERE deleted_at IS NULL
             ORDER BY id
             LIMIT 1'
        )->fetchColumn();

        if ($facultyId < 1) {
            throw new RuntimeException('Department test requires a faculty.');
        }

        $insertUser = $connection->prepare(
            'INSERT INTO users
                (name, email, password_hash, is_active, must_change_password)
             VALUES
                (:name, :email, :password_hash, 1, 0)'
        );
        $insertUser->execute([
            'name' => 'Phase 4 Department Test',
            'email' => 'phase4-' . $suffix . '@example.invalid',
            'password_hash' => password_hash(
                'TransactionalTest#123',
                PASSWORD_DEFAULT
            ),
        ]);
        $userId = (int) $connection->lastInsertId();

        $connection->prepare(
            'INSERT INTO roles (name, code, description, is_system_role)
             VALUES (:name, :code, :description, 0)'
        )->execute([
            'name' => 'Phase 4 Test ' . $suffix,
            'code' => 'phase4_test_' . $suffix,
            'description' => 'Transactional department workflow role.',
        ]);
        $roleId = (int) $connection->lastInsertId();
        $connection->prepare(
            'INSERT INTO user_roles (user_id, role_id)
             VALUES (:user_id, :role_id)'
        )->execute(['user_id' => $userId, 'role_id' => $roleId]);
        $permissionStatement = $connection->prepare(
            'INSERT INTO role_permissions (role_id, permission_id)
             SELECT :role_id, id FROM permissions WHERE code = :code'
        );

        foreach ([
            'departments.view',
            'departments.create',
            'departments.edit',
            'departments.publish',
            'content.review',
            'content.approve',
            'settings.manage',
        ] as $permission) {
            $permissionStatement->execute([
                'role_id' => $roleId,
                'code' => $permission,
            ]);

            if ($permissionStatement->rowCount() !== 1) {
                throw new RuntimeException(
                    sprintf('Required permission %s is missing.', $permission)
                );
            }
        }

        $users = new UserRepository($database);
        $authorization = new AuthorizationService($users);
        $scope = new DepartmentScopeService($users, $authorization);
        $departments = new DepartmentRepository($database, $scope);
        $validator = new DepartmentValidator($departments);
        $workflow = new ContentWorkflowService($authorization, $scope);
        $service = new DepartmentService(
            $departments,
            $authorization,
            $scope,
            $workflow,
            new AuditService($database)
        );
        $input = [
            'faculty_id' => (string) $facultyId,
            'name' => 'Transactional Department ' . $suffix,
            'short_name' => 'TDT',
            'slug' => 'transactional-department-' . $suffix,
            'overview' => 'Verified only as transactional test content.',
            'history' => '',
            'vision' => '',
            'mission' => '',
            'strategic_direction' => '',
            'hod_message' => '',
            'email' => 'department-' . $suffix . '@example.invalid',
            'phone' => '',
            'location_id' => '',
            'hero_media_id' => '',
            'display_order' => '99',
        ];
        $validation = $validator->validate($input);

        if ($validation['errors'] !== []) {
            throw new RuntimeException(
                'Valid department was rejected: '
                . json_encode($validation['errors'])
            );
        }

        $invalidRelationships = $input;
        $invalidRelationships['location_id'] = 'not-an-id';
        $invalidRelationships['hero_media_id'] = '-3';

        if (count($validator->validate($invalidRelationships)['errors']) < 2) {
            throw new RuntimeException(
                'Invalid location/media relationships were accepted.'
            );
        }

        $departmentId = $service->create(
            $validation['data'],
            $userId,
            '127.0.0.9',
            'FAST department integration test'
        );
        $created = $departments->findAdmin($departmentId, $userId);

        if (!is_array($created) || $created['status'] !== 'draft') {
            throw new RuntimeException('Department was not created as draft.');
        }

        $connection->prepare(
            'INSERT INTO media
                (media_type, original_name, stored_name, file_path, mime_type,
                 file_extension, file_size, alt_text, uploaded_by, status)
             VALUES
                ("image", :original_name, :stored_name, :file_path,
                 "image/png", "png", 68, :alt_text, :uploaded_by, "active")'
        )->execute([
            'original_name' => 'Transactional HOD.png',
            'stored_name' => 'transactional-hod-' . $suffix . '.png',
            'file_path' => 'public/uploads/departments/transactional-hod-'
                . $suffix
                . '.png',
            'alt_text' => 'Portrait of the transactional head of department',
            'uploaded_by' => $userId,
        ]);
        $headMediaId = (int) $connection->lastInsertId();
        $connection->prepare(
            'INSERT INTO staff
                (faculty_id, honorific_title, first_name, last_name, slug,
                 staff_category, short_biography, institutional_email,
                 profile_media_id, status, published_at)
             VALUES
                (:faculty_id, "Dr", "Test", "Head", :slug, "academic",
                 :biography, :email, :profile_media_id, "published", NOW())'
        )->execute([
            'faculty_id' => $facultyId,
            'slug' => 'transactional-head-' . $suffix,
            'biography' => 'Published transactional leadership profile.',
            'email' => 'head-' . $suffix . '@example.invalid',
            'profile_media_id' => $headMediaId,
        ]);
        $headStaffId = (int) $connection->lastInsertId();
        $connection->prepare(
            'INSERT INTO staff_positions
                (staff_id, position_id, faculty_id, department_id, is_current)
             SELECT :staff_id, id, :faculty_id, :department_id, 1
             FROM positions
             WHERE code = "head_of_department" AND is_active = 1'
        )->execute([
            'staff_id' => $headStaffId,
            'faculty_id' => $facultyId,
            'department_id' => $departmentId,
        ]);
        $resolvedHead = $departments->findPublishedHead($departmentId);

        if (!is_array($resolvedHead)
            || (int) $resolvedHead['id'] !== $headStaffId
            || $resolvedHead['profile_path'] !== (
                'public/uploads/departments/transactional-hod-'
                . $suffix
                . '.png'
            )
            || $resolvedHead['position_title'] !== 'Head of Department'
        ) {
            throw new RuntimeException(
                'Current published HOD was not resolved from the staff directory.'
            );
        }

        $adminSearch = $departments->paginateAdmin(
            $userId,
            $suffix,
            'draft',
            1
        );

        if ($adminSearch['total'] !== 1) {
            throw new RuntimeException('Admin search/filter did not find the draft.');
        }

        if ($departments->findPublishedBySlug($input['slug']) !== null) {
            throw new RuntimeException('Draft department was publicly visible.');
        }

        $duplicate = $validator->validate($input);

        if ($duplicate['errors'] === []) {
            throw new RuntimeException('Duplicate department was accepted.');
        }

        $input['overview'] = 'Updated transactional test content.';
        $updatedValidation = $validator->validate($input, $departmentId);

        if ($updatedValidation['errors'] !== []) {
            throw new RuntimeException('Valid department revision was rejected.');
        }

        $service->update(
            $departmentId,
            $updatedValidation['data'],
            $userId,
            'Integration-test revision',
            '127.0.0.9',
            'FAST department integration test'
        );

        if ($service->transition(
            $departmentId,
            'submitted',
            $userId,
            'Transactional submission',
            '127.0.0.9',
            'FAST department integration test'
        ) !== 'under_review'
        ) {
            throw new RuntimeException('Draft was not submitted for review.');
        }

        try {
            $service->update(
                $departmentId,
                $updatedValidation['data'],
                $userId,
                'Invalid post-submission edit',
                '127.0.0.9',
                'FAST department integration test'
            );
            throw new RuntimeException('Under-review content was editable.');
        } catch (HttpException $exception) {
            if ($exception->status() !== 409) {
                throw $exception;
            }
        }

        if ($service->transition(
            $departmentId,
            'changes_requested',
            $userId,
            'Please correct the verified test content.',
            '127.0.0.9',
            'FAST department integration test'
        ) !== 'draft'
        ) {
            throw new RuntimeException('Changes request did not return a draft.');
        }

        foreach ([
            'submitted' => 'under_review',
            'approved' => 'approved',
            'published' => 'published',
        ] as $action => $expectedStatus) {
            $actualStatus = $service->transition(
                $departmentId,
                $action,
                $userId,
                'Transactional ' . $action,
                '127.0.0.9',
                'FAST department integration test'
            );

            if ($actualStatus !== $expectedStatus) {
                throw new RuntimeException(
                    sprintf('Workflow action %s produced %s.', $action, $actualStatus)
                );
            }
        }

        if ($departments->findPublishedBySlug($input['slug']) === null) {
            throw new RuntimeException('Published department was not public.');
        }

        if ($departments->paginatePublished($suffix, 1)['total'] !== 1) {
            throw new RuntimeException('Public department search did not find publication.');
        }

        $liveDepartmentData = $updatedValidation['data'];
        $liveDepartmentData['overview'] = 'Updated while the department remained published.';
        $service->updatePublished(
            $departmentId,
            $liveDepartmentData,
            $userId,
            'Live department correction',
            '127.0.0.9',
            'FAST department integration test'
        );
        $liveDepartment = $departments->findPublishedBySlug($input['slug']);

        if (!is_array($liveDepartment)
            || $liveDepartment['status'] !== 'published'
            || $liveDepartment['overview'] !== $liveDepartmentData['overview']
        ) {
            throw new RuntimeException(
                'Published department changes did not remain live and public.'
            );
        }

        if ($service->transition(
            $departmentId,
            'archived',
            $userId,
            'Transactional archive',
            '127.0.0.9',
            'FAST department integration test'
        ) !== 'archived'
        ) {
            throw new RuntimeException('Published department was not archived.');
        }

        if ($departments->findPublishedBySlug($input['slug']) !== null) {
            throw new RuntimeException('Archived department remained public.');
        }

        if ($service->transition(
            $departmentId,
            'restored',
            $userId,
            'Transactional restore',
            '127.0.0.9',
            'FAST department integration test'
        ) !== 'draft'
        ) {
            throw new RuntimeException('Archived department was not restored.');
        }

        $revisionCount = $connection->prepare(
            'SELECT COUNT(*) FROM content_revisions
             WHERE entity_type = "department" AND entity_id = :id'
        );
        $revisionCount->execute(['id' => $departmentId]);
        $approvalCount = $connection->prepare(
            'SELECT COUNT(*) FROM content_approvals
             WHERE entity_type = "department" AND entity_id = :id'
        );
        $approvalCount->execute(['id' => $departmentId]);
        $auditCount = $connection->prepare(
            'SELECT COUNT(*) FROM audit_logs
             WHERE user_id = :user_id
               AND entity_type = "department"
               AND entity_id = :id'
        );
        $auditCount->execute(['user_id' => $userId, 'id' => $departmentId]);

        if ((int) $revisionCount->fetchColumn() !== 3
            || (int) $approvalCount->fetchColumn() !== 7
            || (int) $auditCount->fetchColumn() !== 10
        ) {
            throw new RuntimeException(
                'Revision, approval, or audit history was incomplete.'
            );
        }

        $incompleteData = $validation['data'];
        $incompleteData['name'] = 'Incomplete Department ' . $suffix;
        $incompleteData['slug'] = 'incomplete-department-' . $suffix;
        $incompleteData['overview'] = null;
        $incompleteId = $service->create(
            $incompleteData,
            $userId,
            '127.0.0.9',
            'FAST department integration test'
        );

        try {
            $service->transition(
                $incompleteId,
                'submitted',
                $userId,
                'Incomplete submission test',
                '127.0.0.9',
                'FAST department integration test'
            );
            throw new RuntimeException(
                'Department without an overview entered review.'
            );
        } catch (HttpException $exception) {
            if ($exception->status() !== 409) {
                throw $exception;
            }
        }

        $completedData = $incompleteData;
        $completedData['overview'] = 'Completed for direct publication.';
        $service->updateAndPublish(
            $incompleteId,
            $completedData,
            $userId,
            'Completed and published in one action',
            'Direct publication integration test',
            '127.0.0.9',
            'FAST department integration test'
        );
        $directlyPublished = $departments->findPublishedBySlug(
            $completedData['slug']
        );
        $directApprovalCount = $connection->prepare(
            'SELECT COUNT(*) FROM content_approvals
             WHERE entity_type = "department" AND entity_id = :id'
        );
        $directApprovalCount->execute(['id' => $incompleteId]);

        if ($directlyPublished === null
            || (int) $directApprovalCount->fetchColumn() !== 3
        ) {
            throw new RuntimeException(
                'Direct publication did not preserve the complete workflow history.'
            );
        }

        $limitedEmail = 'phase4-limited-' . $suffix . '@example.invalid';
        $insertUser->execute([
            'name' => 'Phase 4 Limited Editor',
            'email' => $limitedEmail,
            'password_hash' => password_hash(
                'TransactionalTest#456',
                PASSWORD_DEFAULT
            ),
        ]);
        $limitedUserId = (int) $connection->lastInsertId();
        $connection->prepare(
            'INSERT INTO roles (name, code, description, is_system_role)
             VALUES (:name, :code, :description, 0)'
        )->execute([
            'name' => 'Phase 4 Limited ' . $suffix,
            'code' => 'phase4_limited_' . $suffix,
            'description' => 'Transactional department-limited editor.',
        ]);
        $limitedRoleId = (int) $connection->lastInsertId();
        $connection->prepare(
            'INSERT INTO user_roles (user_id, role_id)
             VALUES (:user_id, :role_id)'
        )->execute([
            'user_id' => $limitedUserId,
            'role_id' => $limitedRoleId,
        ]);

        foreach ([
            'departments.view',
            'departments.create',
            'departments.edit',
        ] as $permission) {
            $permissionStatement->execute([
                'role_id' => $limitedRoleId,
                'code' => $permission,
            ]);
        }

        $connection->prepare(
            'INSERT INTO user_departments
                (user_id, department_id, access_level)
             VALUES (:user_id, :department_id, "edit")'
        )->execute([
            'user_id' => $limitedUserId,
            'department_id' => $departmentId,
        ]);
        $limitedAuthorization = new AuthorizationService($users);
        $limitedScope = new DepartmentScopeService(
            $users,
            $limitedAuthorization
        );
        $limitedDepartments = new DepartmentRepository($database, $limitedScope);
        $limitedWorkflow = new ContentWorkflowService(
            $limitedAuthorization,
            $limitedScope
        );
        $limitedService = new DepartmentService(
            $limitedDepartments,
            $limitedAuthorization,
            $limitedScope,
            $limitedWorkflow,
            new AuditService($database)
        );
        $otherDepartmentId = (int) $connection->query(
            'SELECT id FROM departments
             WHERE id <> ' . $departmentId . '
               AND deleted_at IS NULL
             ORDER BY id
             LIMIT 1'
        )->fetchColumn();

        if ($limitedDepartments->findAdmin(
            $departmentId,
            $limitedUserId
        ) === null
            || ($otherDepartmentId > 0
                && $limitedDepartments->findAdmin(
                    $otherDepartmentId,
                    $limitedUserId
                ) !== null)
        ) {
            throw new RuntimeException(
                'Department-limited repository scope was not enforced.'
            );
        }

        $limitedActions = array_column(
            $limitedWorkflow->availableDepartmentActions(
                $limitedUserId,
                $departmentId,
                'draft'
            ),
            'action'
        );

        if ($limitedActions !== ['submitted']) {
            throw new RuntimeException(
                'Department edit-level workflow actions were incorrect.'
            );
        }

        if ($limitedWorkflow->canPublishDraftDirectly(
            $limitedUserId,
            $departmentId
        )) {
            throw new RuntimeException(
                'Limited editor was allowed to publish directly.'
            );
        }

        try {
            $limitedService->create(
                $validation['data'],
                $limitedUserId,
                '127.0.0.10',
                'FAST department integration test'
            );
            throw new RuntimeException(
                'Department-limited user created an unassigned department.'
            );
        } catch (HttpException $exception) {
            if ($exception->status() !== 403) {
                throw $exception;
            }
        }
    } finally {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
    }
};
