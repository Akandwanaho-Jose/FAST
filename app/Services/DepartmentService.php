<?php

declare(strict_types=1);

namespace FastWebsite\Services;

use FastWebsite\Core\HttpException;
use FastWebsite\Repositories\DepartmentRepository;
use Throwable;

final class DepartmentService
{
    public function __construct(
        private readonly DepartmentRepository $departments,
        private readonly AuthorizationService $authorization,
        private readonly DepartmentScopeService $scope,
        private readonly ContentWorkflowService $workflow,
        private readonly AuditService $audit
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(
        array $data,
        int $userId,
        string $ipAddress,
        string $userAgent
    ): int {
        if (!$this->authorization->can($userId, 'departments.create')
            || !$this->scope->hasGlobalScope($userId)
        ) {
            throw new HttpException(
                403,
                'Creating a department requires global department scope.'
            );
        }

        $connection = $this->departments->connection();
        $ownsTransaction = !$connection->inTransaction();

        if ($ownsTransaction) {
            $connection->beginTransaction();
        }

        try {
            $id = $this->departments->create($data);
            $created = [...$data, 'id' => $id, 'status' => 'draft'];
            $this->departments->recordRevision(
                $id,
                null,
                $created,
                $userId,
                'Initial department record'
            );
            $this->audit->record(
                $userId,
                'department.created',
                'department',
                $id,
                $ipAddress,
                $userAgent,
                ['status' => 'draft']
            );
            if ($ownsTransaction) {
                $connection->commit();
            }

            return $id;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(
        int $id,
        array $data,
        int $userId,
        string $note,
        string $ipAddress,
        string $userAgent
    ): void {
        if (!$this->authorization->can($userId, 'departments.edit')
            || !$this->scope->canAccess($userId, $id, 'edit')
        ) {
            throw new HttpException(403, 'You cannot edit this department.');
        }

        $connection = $this->departments->connection();
        $ownsTransaction = !$connection->inTransaction();

        if ($ownsTransaction) {
            $connection->beginTransaction();
        }

        try {
            $previous = $this->departments->findForUpdate($id);

            if ($previous === null) {
                throw new HttpException(404, 'Department not found.');
            }

            if ($previous['status'] !== 'draft') {
                throw new HttpException(
                    409,
                    'Only draft departments can be edited. Use the workflow first.'
                );
            }

            $this->departments->update($id, $data);
            $revised = [...$data, 'id' => $id, 'status' => $previous['status']];
            $this->departments->recordRevision(
                $id,
                $previous,
                $revised,
                $userId,
                $note !== '' ? $note : 'Department details updated'
            );
            $this->audit->record(
                $userId,
                'department.updated',
                'department',
                $id,
                $ipAddress,
                $userAgent,
                ['status' => (string) $previous['status']]
            );
            if ($ownsTransaction) {
                $connection->commit();
            }
        } catch (Throwable $exception) {
            if ($ownsTransaction && $connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updatePublished(
        int $id,
        array $data,
        int $userId,
        string $note,
        string $ipAddress,
        string $userAgent
    ): void {
        if (!$this->workflow->canPublishDraftDirectly($userId, $id)) {
            throw new HttpException(
                403,
                'Editing a published department requires publishing access.'
            );
        }

        $connection = $this->departments->connection();
        $ownsTransaction = !$connection->inTransaction();

        if ($ownsTransaction) {
            $connection->beginTransaction();
        }

        try {
            $previous = $this->departments->findForUpdate($id);

            if ($previous === null) {
                throw new HttpException(404, 'Department not found.');
            }

            if ($previous['status'] !== 'published') {
                throw new HttpException(
                    409,
                    'The department is no longer published. Reload before editing.'
                );
            }

            if (trim((string) ($data['overview'] ?? '')) === '') {
                throw new HttpException(
                    409,
                    'Published departments require a verified overview.'
                );
            }

            $this->departments->update($id, $data);
            $this->departments->recordRevision(
                $id,
                $previous,
                [...$data, 'id' => $id, 'status' => 'published'],
                $userId,
                $note !== '' ? $note : 'Published department updated'
            );
            $this->audit->record(
                $userId,
                'department.published_updated',
                'department',
                $id,
                $ipAddress,
                $userAgent,
                ['status' => 'published']
            );

            if ($ownsTransaction) {
                $connection->commit();
            }
        } catch (Throwable $exception) {
            if ($ownsTransaction && $connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    public function transition(
        int $id,
        string $action,
        int $userId,
        ?string $comment,
        string $ipAddress,
        string $userAgent
    ): string {
        $connection = $this->departments->connection();
        $ownsTransaction = !$connection->inTransaction();

        if ($ownsTransaction) {
            $connection->beginTransaction();
        }

        try {
            $department = $this->departments->findForUpdate($id);

            if ($department === null) {
                throw new HttpException(404, 'Department not found.');
            }

            $target = $this->workflow->departmentTargetStatus(
                $userId,
                $id,
                (string) $department['status'],
                $action
            );

            if ($action === 'changes_requested'
                && trim((string) $comment) === ''
            ) {
                throw new HttpException(
                    409,
                    'A comment is required when requesting changes.'
                );
            }

            if ($target === 'under_review'
                && trim((string) ($department['overview'] ?? '')) === ''
            ) {
                throw new HttpException(
                    409,
                    'Add a verified overview before submitting this department.'
                );
            }

            if ($target === 'published'
                && trim((string) ($department['overview'] ?? '')) === ''
            ) {
                throw new HttpException(
                    409,
                    'Add a verified overview before publishing this department.'
                );
            }

            $this->departments->changeStatus($id, $target);
            $this->departments->recordApproval(
                $id,
                $action,
                $userId,
                $comment
            );
            $this->audit->record(
                $userId,
                'department.' . $action,
                'department',
                $id,
                $ipAddress,
                $userAgent,
                [
                    'from_status' => (string) $department['status'],
                    'to_status' => $target,
                ]
            );
            if ($ownsTransaction) {
                $connection->commit();
            }

            return $target;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateAndPublish(
        int $id,
        array $data,
        int $userId,
        string $note,
        string $comment,
        string $ipAddress,
        string $userAgent
    ): void {
        $connection = $this->departments->connection();
        $ownsTransaction = !$connection->inTransaction();

        if ($ownsTransaction) {
            $connection->beginTransaction();
        }

        try {
            if (!$this->workflow->canPublishDraftDirectly($userId, $id)) {
                throw new HttpException(
                    403,
                    'You do not have all permissions required to publish directly.'
                );
            }

            $this->update(
                $id,
                $data,
                $userId,
                $note,
                $ipAddress,
                $userAgent
            );
            $this->publishDraft(
                $id,
                $userId,
                $comment,
                $ipAddress,
                $userAgent
            );

            if ($ownsTransaction) {
                $connection->commit();
            }
        } catch (Throwable $exception) {
            if ($ownsTransaction && $connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    public function publishDraft(
        int $id,
        int $userId,
        string $comment,
        string $ipAddress,
        string $userAgent
    ): void {
        $connection = $this->departments->connection();
        $ownsTransaction = !$connection->inTransaction();

        if ($ownsTransaction) {
            $connection->beginTransaction();
        }

        try {
            if (!$this->workflow->canPublishDraftDirectly($userId, $id)) {
                throw new HttpException(
                    403,
                    'You do not have all permissions required to publish directly.'
                );
            }

            foreach ([
                'submitted' => $comment,
                'approved' => 'Approved during direct publication.',
                'published' => 'Published during direct publication.',
            ] as $action => $actionComment) {
                $this->transition(
                    $id,
                    $action,
                    $userId,
                    $actionComment,
                    $ipAddress,
                    $userAgent
                );
            }

            if ($ownsTransaction) {
                $connection->commit();
            }
        } catch (Throwable $exception) {
            if ($ownsTransaction && $connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }
}
