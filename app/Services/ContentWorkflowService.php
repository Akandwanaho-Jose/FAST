<?php

declare(strict_types=1);

namespace FastWebsite\Services;

use FastWebsite\Core\HttpException;

final class ContentWorkflowService
{
    private const TRANSITIONS = [
        'submitted' => ['draft' => 'under_review'],
        'changes_requested' => [
            'under_review' => 'draft',
            'approved' => 'draft',
        ],
        'approved' => ['under_review' => 'approved'],
        'published' => ['approved' => 'published'],
        'archived' => ['published' => 'archived'],
        'restored' => ['archived' => 'draft'],
    ];

    public function __construct(
        private readonly AuthorizationService $authorization,
        private readonly DepartmentScopeService $scope
    ) {
    }

    /**
     * @return list<array{action: string, label: string}>
     */
    public function availableDepartmentActions(
        int $userId,
        int $departmentId,
        string $status
    ): array {
        $definitions = [
            'submitted' => ['Submit for review', 'departments.edit', 'edit'],
            'changes_requested' => ['Request changes', 'content.review', 'review'],
            'approved' => ['Approve', 'content.approve', 'review'],
            'published' => ['Publish', 'departments.publish', 'publish'],
            'archived' => ['Archive', 'departments.publish', 'publish'],
            'restored' => ['Restore as draft', 'departments.publish', 'publish'],
        ];
        $actions = [];

        foreach ($definitions as $action => [$label, $permission, $level]) {
            if (!isset(self::TRANSITIONS[$action][$status])) {
                continue;
            }

            if ($this->authorization->can($userId, $permission)
                && $this->scope->canAccess($userId, $departmentId, $level)
            ) {
                $actions[] = ['action' => $action, 'label' => $label];
            }
        }

        return $actions;
    }

    public function departmentTargetStatus(
        int $userId,
        int $departmentId,
        string $currentStatus,
        string $action
    ): string {
        $allowed = array_column(
            $this->availableDepartmentActions(
                $userId,
                $departmentId,
                $currentStatus
            ),
            'action'
        );

        if (!in_array($action, $allowed, true)) {
            throw new HttpException(
                403,
                'This workflow action is not allowed for this account.'
            );
        }

        return self::TRANSITIONS[$action][$currentStatus];
    }

    public function canPublishDraftDirectly(
        int $userId,
        int $departmentId
    ): bool {
        foreach ([
            ['departments.edit', 'edit'],
            ['content.review', 'review'],
            ['content.approve', 'review'],
            ['departments.publish', 'publish'],
        ] as [$permission, $level]) {
            if (!$this->authorization->can($userId, $permission)
                || !$this->scope->canAccess($userId, $departmentId, $level)
            ) {
                return false;
            }
        }

        return true;
    }
}
