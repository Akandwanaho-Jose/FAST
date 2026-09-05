<?php

declare(strict_types=1);

namespace FastWebsite\Services;

use FastWebsite\Core\HttpException;
use FastWebsite\Repositories\StaffRepository;
use Throwable;

final class StaffService
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
        private readonly StaffRepository $staff,
        private readonly AuthorizationService $authorization,
        private readonly DepartmentScopeService $scope,
        private readonly AuditService $audit
    ) {
    }

    /** @param array<string,mixed> $data @param array<string,mixed> $assignment */
    public function create(
        array $data,
        array $assignment,
        int $userId,
        string $ip,
        string $agent,
        array $relations = []
    ): int
    {
        $this->requireGlobal($userId, 'staff.create');
        return $this->transaction(function () use ($data, $assignment, $userId, $ip, $agent, $relations): int {
            $id = $this->staff->create($data);
            $this->staff->replaceAssignment(
                $id,
                $assignment['department_id'],
                $assignment['position_id'],
                $assignment['title_override'],
                (int) $data['faculty_id']
            );
            $this->staff->replaceProfileRelations($id, $relations);
            $this->staff->recordRevision($id, null, [...$data, ...$assignment, 'relations' => $relations], $userId, 'Initial staff profile');
            $this->audit->record($userId, 'staff.created', 'staff', $id, $ip, $agent, ['status' => 'draft']);
            return $id;
        });
    }

    /** @param array<string,mixed> $data @param array<string,mixed> $assignment */
    public function createAndPublish(
        array $data,
        array $assignment,
        int $userId,
        string $ip,
        string $agent,
        array $relations = []
    ): int {
        return $this->transaction(function () use ($data, $assignment, $userId, $ip, $agent, $relations): int {
            if (!$this->canDirectPublish($userId)) {
                throw new HttpException(
                    403,
                    'You do not have all permissions required to publish directly.'
                );
            }
            $id = $this->create($data, $assignment, $userId, $ip, $agent, $relations);
            $this->publishDraft(
                $id,
                $userId,
                'Created and published directly.',
                $ip,
                $agent
            );
            return $id;
        });
    }

    /** @param array<string,mixed> $data @param array<string,mixed> $assignment */
    public function update(
        int $id,
        array $data,
        array $assignment,
        int $userId,
        string $note,
        string $ip,
        string $agent,
        array $relations = []
    ): void {
        $this->requireGlobal($userId, 'staff.edit');
        $this->transaction(function () use ($id, $data, $assignment, $userId, $note, $ip, $agent, $relations): void {
            $previous = $this->staff->findForUpdate($id);
            if ($previous === null) {
                throw new HttpException(404, 'Staff profile not found.');
            }
            if ($previous['status'] !== 'draft') {
                throw new HttpException(409, 'Only draft staff profiles can be edited.');
            }
            $this->staff->update($id, $data);
            $this->staff->replaceAssignment(
                $id,
                $assignment['department_id'],
                $assignment['position_id'],
                $assignment['title_override'],
                (int) $data['faculty_id']
            );
            $this->staff->replaceProfileRelations($id, $relations);
            $this->staff->recordRevision(
                $id,
                $previous,
                [...$data, ...$assignment, 'relations' => $relations],
                $userId,
                $note !== '' ? $note : 'Staff profile updated'
            );
            $this->audit->record($userId, 'staff.updated', 'staff', $id, $ip, $agent, ['status' => 'draft']);
        });
    }

    /** @param array<string,mixed> $data @param array<string,mixed> $assignment */
    public function updatePublished(
        int $id,
        array $data,
        array $assignment,
        int $userId,
        string $note,
        string $ip,
        string $agent,
        array $relations = []
    ): void {
        if (!$this->canDirectPublish($userId)) {
            throw new HttpException(
                403,
                'Editing published profiles requires staff publishing access.'
            );
        }
        $this->transaction(function () use ($id, $data, $assignment, $userId, $note, $ip, $agent, $relations): void {
            $previous = $this->staff->findForUpdate($id);
            if ($previous === null) {
                throw new HttpException(404, 'Staff profile not found.');
            }
            if ($previous['status'] !== 'published') {
                throw new HttpException(
                    409,
                    'The profile is no longer published. Reload before editing.'
                );
            }
            if (trim((string) ($data['short_biography'] ?? '')) === '') {
                throw new HttpException(
                    409,
                    'Published staff profiles require a short biography.'
                );
            }
            $this->staff->update($id, $data);
            $this->staff->replaceAssignment(
                $id,
                $assignment['department_id'],
                $assignment['position_id'],
                $assignment['title_override'],
                (int) $data['faculty_id']
            );
            $this->staff->replaceProfileRelations($id, $relations);
            $this->staff->recordRevision(
                $id,
                $previous,
                [...$data, ...$assignment, 'relations' => $relations, 'status' => 'published'],
                $userId,
                $note !== '' ? $note : 'Published staff profile updated'
            );
            $this->audit->record(
                $userId,
                'staff.published_updated',
                'staff',
                $id,
                $ip,
                $agent,
                ['status' => 'published']
            );
        });
    }

    /**
     * Staff self-service: lets a staff member update their own profile
     * regardless of its current status (draft or published), without the
     * elevated staff.edit/publish permissions updatePublished() requires.
     * Ownership (does this staff row belong to the calling user) must be
     * verified by the caller before this is invoked - this method trusts
     * $id once called. Only touches the fields present in $data (a partial
     * update, per StaffRepository::update()) and never department/position
     * assignment, so self-service can never grant itself those.
     *
     * @param array<string,mixed> $data
     * @param array<string,mixed> $relations
     */
    public function updateOwnProfile(
        int $id,
        array $data,
        int $userId,
        string $note,
        string $ip,
        string $agent,
        array $relations = []
    ): void {
        $this->transaction(function () use ($id, $data, $userId, $note, $ip, $agent, $relations): void {
            $previous = $this->staff->findForUpdate($id);
            if ($previous === null) {
                throw new HttpException(404, 'Staff profile not found.');
            }
            $this->staff->update($id, $data);
            $this->staff->replaceProfileRelations($id, $relations);
            $this->staff->recordRevision(
                $id,
                $previous,
                [...$data, 'relations' => $relations],
                $userId,
                $note !== '' ? $note : 'Profile updated by staff member'
            );
            $this->audit->record(
                $userId,
                'staff.self_updated',
                'staff',
                $id,
                $ip,
                $agent,
                ['status' => $previous['status']]
            );
        });
    }

    /** @param array<string,mixed> $data @param array<string,mixed> $assignment */
    public function updateAndPublish(
        int $id,
        array $data,
        array $assignment,
        int $userId,
        string $note,
        string $ip,
        string $agent,
        array $relations = []
    ): void {
        $this->transaction(function () use ($id, $data, $assignment, $userId, $note, $ip, $agent, $relations): void {
            $this->update($id, $data, $assignment, $userId, $note, $ip, $agent, $relations);
            $this->publishDraft($id, $userId, 'Direct publication.', $ip, $agent);
        });
    }

    public function publishDraft(
        int $id,
        int $userId,
        string $comment,
        string $ip,
        string $agent
    ): void {
        $this->transaction(function () use ($id, $userId, $comment, $ip, $agent): void {
            if (!$this->canDirectPublish($userId)) {
                throw new HttpException(
                    403,
                    'You do not have all permissions required to publish directly.'
                );
            }
            foreach (['submitted', 'approved', 'published'] as $action) {
                $this->transition($id, $action, $userId, $comment, $ip, $agent);
            }
        });
    }

    public function transition(
        int $id,
        string $action,
        int $userId,
        string $comment,
        string $ip,
        string $agent
    ): string {
        return $this->transaction(function () use ($id, $action, $userId, $comment, $ip, $agent): string {
            $profile = $this->staff->findForUpdate($id);
            if ($profile === null) {
                throw new HttpException(404, 'Staff profile not found.');
            }
            if (!isset(self::TRANSITIONS[$action])) {
                throw new HttpException(403, 'That staff workflow action is unavailable.');
            }
            if ($action === 'changes_requested' && trim($comment) === '') {
                throw new HttpException(409, 'A comment is required when requesting changes.');
            }
            [$from, $target] = self::TRANSITIONS[$action];
            if ($profile['status'] !== $from) {
                throw new HttpException(409, 'The staff profile status has changed.');
            }
            $permission = match ($action) {
                'submitted' => 'staff.edit',
                'approved', 'changes_requested' => 'content.approve',
                default => 'staff.publish',
            };
            $this->requireGlobal($userId, $permission);
            if (in_array($target, ['under_review', 'published'], true)
                && trim((string) ($profile['short_biography'] ?? '')) === ''
            ) {
                throw new HttpException(409, 'Add a short biography before publishing.');
            }
            $this->staff->changeStatus($id, $target);
            $this->staff->recordApproval($id, $action, $userId, $comment);
            $this->audit->record(
                $userId,
                'staff.' . $action,
                'staff',
                $id,
                $ip,
                $agent,
                ['from_status' => $from, 'to_status' => $target]
            );
            return $target;
        });
    }

    /** @return list<array{action:string,label:string}> */
    public function actions(int $userId, string $status): array
    {
        if (!$this->scope->hasGlobalScope($userId)) {
            return [];
        }
        $definitions = [
            'submitted' => ['draft', 'staff.edit', 'Submit for review'],
            'changes_requested' => ['under_review', 'content.approve', 'Request changes'],
            'approved' => ['under_review', 'content.approve', 'Approve'],
            'published' => ['approved', 'staff.publish', 'Publish'],
            'archived' => ['published', 'staff.publish', 'Archive'],
            'restored' => ['archived', 'staff.publish', 'Restore as draft'],
        ];
        $actions = [];
        foreach ($definitions as $action => [$from, $permission, $label]) {
            if ($status === $from && $this->authorization->can($userId, $permission)) {
                $actions[] = ['action' => $action, 'label' => $label];
            }
        }
        return $actions;
    }

    public function canDirectPublish(int $userId): bool
    {
        return $this->scope->hasGlobalScope($userId)
            && $this->authorization->can($userId, 'staff.edit')
            && $this->authorization->can($userId, 'content.approve')
            && $this->authorization->can($userId, 'staff.publish');
    }

    private function requireGlobal(int $userId, string $permission): void
    {
        if (!$this->authorization->can($userId, $permission)
            || !$this->scope->hasGlobalScope($userId)
        ) {
            throw new HttpException(403, 'This staff action requires global staff access.');
        }
    }

    private function transaction(callable $operation): mixed
    {
        $connection = $this->staff->connection();
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
