<?php

declare(strict_types=1);

use FastWebsite\Core\Database;
use FastWebsite\Core\HttpException;
use FastWebsite\Repositories\PublicationRepository;
use FastWebsite\Repositories\StaffRepository;
use FastWebsite\Repositories\UserRepository;
use FastWebsite\Services\AuditService;
use FastWebsite\Services\AuthorizationService;
use FastWebsite\Services\DepartmentScopeService;
use FastWebsite\Services\PublicationService;
use FastWebsite\Services\StaffService;

return static function (): void {
    /** @var Database $database */
    $database = require dirname(__DIR__, 2) . '/bootstrap/database.php';
    $connection = $database->connection();
    $suffix = bin2hex(random_bytes(6));
    $connection->beginTransaction();

    try {
        $facultyId = (int) $connection->query(
            'SELECT id FROM faculties WHERE deleted_at IS NULL ORDER BY id LIMIT 1'
        )->fetchColumn();
        $publicationTypeId = (int) $connection->query(
            'SELECT id FROM publication_types LIMIT 1'
        )->fetchColumn();

        if (min($facultyId, $publicationTypeId) < 1) {
            throw new RuntimeException('Staff self-service flow prerequisites are missing.');
        }

        $users = new UserRepository($database);
        $authorization = new AuthorizationService($users);
        $scope = new DepartmentScopeService($users, $authorization);
        $staffRepository = new StaffRepository($database, $scope);
        $publicationRepository = new PublicationRepository($database, $scope);
        $audit = new AuditService($database);
        $staffService = new StaffService($staffRepository, $authorization, $scope, $audit);
        $publicationService = new PublicationService($publicationRepository, $authorization, $audit);

        // A staff self-service account has zero RBAC permissions - that's
        // exactly how AuthController tells it apart from an admin account.
        $connection->prepare(
            'INSERT INTO users (name, email, password_hash, is_active, must_change_password)
             VALUES (:name, :email, :hash, 1, 0)'
        )->execute([
            'name' => 'Self Service Test User',
            'email' => 'self-service-' . $suffix . '@example.invalid',
            'hash' => password_hash('irrelevant', PASSWORD_DEFAULT),
        ]);
        $userId = (int) $connection->lastInsertId();
        if ($authorization->permissions($userId) !== []) {
            throw new RuntimeException('A freshly created user unexpectedly has RBAC permissions.');
        }

        $connection->prepare(
            'INSERT INTO staff
                (faculty_id, first_name, last_name, slug, staff_category,
                 short_biography, institutional_email, status, published_at)
             VALUES
                (:faculty, :first, :last, :slug, "academic",
                 "Original bio.", :email, "published", NOW())'
        )->execute([
            'faculty' => $facultyId,
            'first' => 'SelfService',
            'last' => 'Test-' . $suffix,
            'slug' => 'self-service-test-' . $suffix,
            'email' => 'staff-self-service-' . $suffix . '@example.invalid',
        ]);
        $staffId = (int) $connection->lastInsertId();

        // Not linked to any user yet: staffIdForUser() must return null,
        // not resolve to a stray/unrelated staff row.
        if ($staffRepository->staffIdForUser($userId) !== null) {
            throw new RuntimeException('staffIdForUser() resolved before the account was linked.');
        }

        $connection->prepare('UPDATE staff SET user_id = :user WHERE id = :id')
            ->execute(['user' => $userId, 'id' => $staffId]);

        if ($staffRepository->staffIdForUser($userId) !== $staffId) {
            throw new RuntimeException('staffIdForUser() did not resolve the linked staff row.');
        }
        if ($staffRepository->findOwn($staffId, $userId) === null) {
            throw new RuntimeException('findOwn() did not return the linked staff profile.');
        }
        // A different, unrelated user must never resolve to this staff row.
        $connection->prepare(
            'INSERT INTO users (name, email, password_hash, is_active, must_change_password)
             VALUES (:name, :email, :hash, 1, 0)'
        )->execute([
            'name' => 'Other Test User',
            'email' => 'other-' . $suffix . '@example.invalid',
            'hash' => password_hash('irrelevant', PASSWORD_DEFAULT),
        ]);
        $otherUserId = (int) $connection->lastInsertId();
        if ($staffRepository->findOwn($staffId, $otherUserId) !== null) {
            throw new RuntimeException('findOwn() leaked another account\'s staff profile.');
        }

        // Editing a *published* profile via self-service must not require
        // the admin canDirectPublish() gate, and must not change its status.
        $staffService->updateOwnProfile(
            $staffId,
            ['short_biography' => 'Updated via self-service.', 'public_phone' => '+256700000001'],
            $userId,
            'Self-service test edit',
            '127.0.0.11',
            'FAST self-service integration test'
        );
        $updated = $staffRepository->findForUpdate($staffId);
        if (!is_array($updated)
            || $updated['short_biography'] !== 'Updated via self-service.'
            || $updated['public_phone'] !== '+256700000001'
            || $updated['status'] !== 'published'
        ) {
            throw new RuntimeException('updateOwnProfile() did not persist the change or changed the profile status.');
        }
        if ((int) $connection->query(
            'SELECT COUNT(*) FROM content_revisions WHERE entity_type = "staff" AND entity_id = ' . $staffId
        )->fetchColumn() < 1) {
            throw new RuntimeException('updateOwnProfile() did not record a content revision.');
        }

        // Self-service must never be able to touch department/position
        // assignment - updateOwnProfile() takes no assignment argument at
        // all, so confirm the staff row's own faculty_id is untouched.
        if ((int) $updated['faculty_id'] !== $facultyId) {
            throw new RuntimeException('updateOwnProfile() unexpectedly altered faculty assignment.');
        }

        // Publications: createOwn() must both create the record and add the
        // creating staff member as its first author in one step.
        $publicationId = $publicationService->createOwn(
            [
                'publication_type_id' => $publicationTypeId,
                'title' => 'Self-service test publication ' . $suffix,
                'slug' => 'self-service-test-publication-' . $suffix,
                'abstract' => null, 'journal_name' => null, 'publisher' => null,
                'publication_year' => 2026, 'publication_date' => null,
                'volume' => null, 'issue' => null, 'page_range' => null,
                'doi' => null, 'isbn' => null, 'external_url' => null,
                'access_type' => 'unknown', 'citation_text' => null,
            ],
            null,
            null,
            $staffId,
            $userId,
            '127.0.0.11',
            'FAST self-service integration test'
        );
        if (!$publicationService->ownsPublication($publicationId, $staffId)) {
            throw new RuntimeException('createOwn() did not add the creating staff member as an author.');
        }

        // Ownership boundary: a staff member who is not an author must be
        // rejected (404), not silently allowed, on both read and write paths.
        $connection->prepare(
            'INSERT INTO staff
                (faculty_id, first_name, last_name, slug, staff_category, status)
             VALUES (:faculty, "Other", :last, :slug, "academic", "published")'
        )->execute([
            'faculty' => $facultyId,
            'last' => 'Author-' . $suffix,
            'slug' => 'other-author-test-' . $suffix,
        ]);
        $otherStaffId = (int) $connection->lastInsertId();
        if ($publicationRepository->findOwn($publicationId, $otherStaffId) !== null) {
            throw new RuntimeException('findOwn() let a non-author read a publication.');
        }
        try {
            $publicationService->updateOwn(
                $publicationId,
                ['title' => 'Hijacked'],
                null,
                null,
                $otherStaffId,
                $userId,
                '',
                '127.0.0.11',
                'FAST self-service integration test'
            );
            throw new RuntimeException('updateOwn() allowed a non-author to edit a publication.');
        } catch (HttpException $exception) {
            if ($exception->status() !== 404) {
                throw new RuntimeException('Non-author update did not fail with 404: ' . $exception->getMessage());
            }
        }

        // The actual author can edit their own publication in place.
        $publicationService->updateOwn(
            $publicationId,
            [
                'publication_type_id' => $publicationTypeId,
                'title' => 'Updated self-service publication ' . $suffix,
                'slug' => 'self-service-test-publication-' . $suffix,
                'abstract' => null, 'journal_name' => 'Journal of Self Service',
                'publisher' => null, 'publication_year' => 2026,
                'publication_date' => null, 'volume' => null, 'issue' => null,
                'page_range' => null, 'doi' => null, 'isbn' => null,
                'external_url' => null, 'access_type' => 'unknown', 'citation_text' => null,
            ],
            null,
            null,
            $staffId,
            $userId,
            'Self-service publication edit',
            '127.0.0.11',
            'FAST self-service integration test'
        );
        $publication = $publicationRepository->findForUpdate($publicationId);
        if (!is_array($publication) || $publication['journal_name'] !== 'Journal of Self Service') {
            throw new RuntimeException('updateOwn() did not persist the publication change.');
        }

        // Co-author management on an owned publication.
        $publicationService->addOwnAuthor(
            $publicationId, $staffId, null, 'External Collaborator', 'Partner University',
            '', 2, false, $userId, '127.0.0.11', 'FAST self-service integration test'
        );
        $authors = $publicationRepository->authors($publicationId);
        if (count($authors) !== 2) {
            throw new RuntimeException('addOwnAuthor() did not add the external co-author.');
        }
        $externalAuthorId = (int) array_values(array_filter(
            $authors,
            static fn (array $a): bool => $a['staff_id'] === null
        ))[0]['id'];
        $publicationService->removeOwnAuthor(
            $publicationId, $staffId, $externalAuthorId, $userId,
            '127.0.0.11', 'FAST self-service integration test'
        );
        if (count($publicationRepository->authors($publicationId)) !== 1) {
            throw new RuntimeException('removeOwnAuthor() did not remove the co-author.');
        }

        $myPublications = $publicationRepository->myPublications($staffId);
        if (count($myPublications) !== 1 || (int) $myPublications[0]['id'] !== $publicationId) {
            throw new RuntimeException('myPublications() did not return the expected publication.');
        }
    } finally {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
    }
};
