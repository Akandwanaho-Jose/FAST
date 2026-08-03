<?php

declare(strict_types=1);

namespace FastWebsite\Services;

use FastWebsite\Core\HttpException;
use FastWebsite\Repositories\ProgrammeRepository;
use Throwable;

final class ProgrammeService
{
    private const TRANSITIONS = [
        'submitted' => ['draft', 'under_review'],
        'changes_requested' => ['under_review', 'draft'],
        'approved' => ['under_review', 'approved'],
        'published' => ['approved', 'published'],
        'archived' => ['published', 'archived'],
        'restored' => ['archived', 'draft'],
    ];

    public function __construct(
        private readonly ProgrammeRepository $programmes,
        private readonly AuthorizationService $authorization,
        private readonly AuditService $audit
    ) {
    }

    /** @param array<string,mixed> $data */
    public function create(array $data, int $departmentId, int $userId, string $ip, string $agent): int
    {
        $this->requireDepartment($userId, 'programmes.create', $departmentId);
        return $this->transaction(function () use ($data, $departmentId, $userId, $ip, $agent): int {
            $id = $this->programmes->create($data);
            $this->programmes->replaceLeadDepartment($id, $departmentId);
            $this->programmes->recordRevision($id, null, [...$data, 'department_id' => $departmentId], $userId, 'Initial programme record');
            $this->audit->record($userId, 'programme.created', 'programme', $id, $ip, $agent, ['status' => 'draft']);
            return $id;
        });
    }

    /** @param array<string,mixed> $data */
    public function createAndPublish(array $data, int $departmentId, int $userId, string $ip, string $agent): int
    {
        return $this->transaction(function () use ($data, $departmentId, $userId, $ip, $agent): int {
            if (!$this->canDirectPublish($userId, null, $departmentId)) {
                throw new HttpException(403, 'You do not have all permissions required to publish this programme.');
            }
            $id = $this->create($data, $departmentId, $userId, $ip, $agent);
            $this->publishDraft($id, $userId, 'Created and published directly.', $ip, $agent);
            return $id;
        });
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data, int $departmentId, int $userId, string $note, string $ip, string $agent): void
    {
        $this->requireProgramme($userId, 'programmes.edit', $id);
        $this->requireDepartment($userId, 'programmes.edit', $departmentId);
        $this->transaction(function () use ($id, $data, $departmentId, $userId, $note, $ip, $agent): void {
            $previous = $this->programmes->findForUpdate($id);
            if ($previous === null) {
                throw new HttpException(404, 'Programme not found.');
            }
            if ($previous['status'] !== 'draft') {
                throw new HttpException(409, 'Only draft programmes can be edited.');
            }
            $this->saveRevision($id, $data, $departmentId, $previous, $userId, $note, $ip, $agent, 'programme.updated');
        });
    }

    /** @param array<string,mixed> $data */
    public function updatePublished(int $id, array $data, int $departmentId, int $userId, string $note, string $ip, string $agent): void
    {
        if (!$this->canDirectPublish($userId, $id, $departmentId)) {
            throw new HttpException(403, 'Editing a published programme requires publishing access.');
        }
        $this->transaction(function () use ($id, $data, $departmentId, $userId, $note, $ip, $agent): void {
            $previous = $this->programmes->findForUpdate($id);
            if ($previous === null) {
                throw new HttpException(404, 'Programme not found.');
            }
            if ($previous['status'] !== 'published') {
                throw new HttpException(409, 'The programme is no longer published. Reload before editing.');
            }
            $this->requireOverview($data);
            $this->saveRevision($id, $data, $departmentId, $previous, $userId, $note !== '' ? $note : 'Published programme updated', $ip, $agent, 'programme.published_updated');
        });
    }

    /** @param array<string,mixed> $data */
    public function updateAndPublish(int $id, array $data, int $departmentId, int $userId, string $note, string $ip, string $agent): void
    {
        $this->transaction(function () use ($id, $data, $departmentId, $userId, $note, $ip, $agent): void {
            $this->update($id, $data, $departmentId, $userId, $note, $ip, $agent);
            $this->publishDraft($id, $userId, 'Direct publication.', $ip, $agent);
        });
    }

    public function publishDraft(int $id, int $userId, string $comment, string $ip, string $agent): void
    {
        $this->transaction(function () use ($id, $userId, $comment, $ip, $agent): void {
            if (!$this->canDirectPublish($userId, $id)) {
                throw new HttpException(403, 'You do not have all permissions required to publish this programme.');
            }
            foreach (['submitted', 'approved', 'published'] as $action) {
                $this->transition($id, $action, $userId, $comment, $ip, $agent);
            }
        });
    }

    public function transition(int $id, string $action, int $userId, string $comment, string $ip, string $agent): string
    {
        return $this->transaction(function () use ($id, $action, $userId, $comment, $ip, $agent): string {
            $programme = $this->programmes->findForUpdate($id);
            if ($programme === null) {
                throw new HttpException(404, 'Programme not found.');
            }
            if (!isset(self::TRANSITIONS[$action])) {
                throw new HttpException(403, 'That programme workflow action is unavailable.');
            }
            if ($action === 'changes_requested' && trim($comment) === '') {
                throw new HttpException(409, 'A comment is required when requesting changes.');
            }
            [$from, $target] = self::TRANSITIONS[$action];
            if ($programme['status'] !== $from) {
                throw new HttpException(409, 'The programme status has changed.');
            }
            $permission = match ($action) {
                'submitted' => 'programmes.edit',
                'approved', 'changes_requested' => 'content.approve',
                default => 'programmes.publish',
            };
            $this->requireProgramme($userId, $permission, $id);
            if (in_array($target, ['under_review', 'published'], true)) {
                $this->requireOverview($programme);
            }
            $this->programmes->changeStatus($id, $target);
            $this->programmes->recordApproval($id, $action, $userId, $comment);
            $this->audit->record($userId, 'programme.' . $action, 'programme', $id, $ip, $agent, ['from_status' => $from, 'to_status' => $target]);
            return $target;
        });
    }

    /** @return list<array{action:string,label:string}> */
    public function actions(int $userId, int $id, string $status): array
    {
        if (!$this->programmes->canAccess($userId, $id)) {
            return [];
        }
        $definitions = [
            'submitted' => ['draft', 'programmes.edit', 'Submit for review'],
            'changes_requested' => ['under_review', 'content.approve', 'Request changes'],
            'approved' => ['under_review', 'content.approve', 'Approve'],
            'published' => ['approved', 'programmes.publish', 'Publish'],
            'archived' => ['published', 'programmes.publish', 'Archive'],
            'restored' => ['archived', 'programmes.publish', 'Restore as draft'],
        ];
        $actions = [];
        foreach ($definitions as $action => [$from, $permission, $label]) {
            if ($status === $from && $this->authorization->can($userId, $permission)) {
                $actions[] = ['action' => $action, 'label' => $label];
            }
        }
        return $actions;
    }

    public function canDirectPublish(int $userId, ?int $programmeId = null, ?int $departmentId = null): bool
    {
        $scope = $programmeId !== null
            ? $this->programmes->canAccess($userId, $programmeId)
            : ($departmentId !== null && $this->programmes->canUseDepartment($userId, $departmentId));
        return $scope
            && $this->authorization->can($userId, 'programmes.edit')
            && $this->authorization->can($userId, 'content.approve')
            && $this->authorization->can($userId, 'programmes.publish');
    }

    public function canCreate(int $userId): bool
    {
        return $this->authorization->can($userId, 'programmes.create')
            && ($this->programmes->departments() !== []);
    }

    /** @param array<string,mixed> $data @param array<string,mixed> $previous */
    private function saveRevision(int $id, array $data, int $departmentId, array $previous, int $userId, string $note, string $ip, string $agent, string $action): void
    {
        $this->programmes->update($id, $data);
        $this->programmes->replaceLeadDepartment($id, $departmentId);
        $this->programmes->recordRevision($id, $previous, [...$data, 'department_id' => $departmentId, 'status' => $previous['status']], $userId, $note !== '' ? $note : 'Programme updated');
        $this->audit->record($userId, $action, 'programme', $id, $ip, $agent, ['status' => $previous['status']]);
    }

    /** @param array<string,mixed> $data */
    private function requireOverview(array $data): void
    {
        if (trim((string) ($data['overview'] ?? '')) === '') {
            throw new HttpException(409, 'Add an overview before publishing this programme.');
        }
    }

    private function requireProgramme(int $userId, string $permission, int $id): void
    {
        if (!$this->authorization->can($userId, $permission) || !$this->programmes->canAccess($userId, $id)) {
            throw new HttpException(403, 'This programme action is outside your access.');
        }
    }

    private function requireDepartment(int $userId, string $permission, int $departmentId): void
    {
        if (!$this->authorization->can($userId, $permission) || !$this->programmes->canUseDepartment($userId, $departmentId)) {
            throw new HttpException(403, 'This department is outside your programme access.');
        }
    }

    private function transaction(callable $operation): mixed
    {
        $connection = $this->programmes->connection();
        $owns = !$connection->inTransaction();
        if ($owns) {
            $connection->beginTransaction();
        }
        try {
            $result = $operation();
            if ($owns) {
                $connection->commit();
            }
            return $result;
        } catch (Throwable $exception) {
            if ($owns && $connection->inTransaction()) {
                $connection->rollBack();
            }
            throw $exception;
        }
    }
}
