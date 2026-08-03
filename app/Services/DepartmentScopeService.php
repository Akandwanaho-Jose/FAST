<?php

declare(strict_types=1);

namespace FastWebsite\Services;

use FastWebsite\Repositories\UserRepository;

final class DepartmentScopeService
{
    private const ACCESS_RANK = [
        'view' => 10,
        'create' => 20,
        'edit' => 30,
        'review' => 40,
        'publish' => 50,
        'manage' => 60,
    ];

    /**
     * @var array<int, array<int, string>>
     */
    private array $accessCache = [];

    public function __construct(
        private readonly UserRepository $users,
        private readonly AuthorizationService $authorization
    ) {
    }

    public function hasGlobalScope(int $userId): bool
    {
        return $this->authorization->can($userId, 'settings.manage');
    }

    /**
     * @return list<int>
     */
    public function departmentIds(int $userId): array
    {
        return array_keys($this->access($userId));
    }

    public function canAccess(
        int $userId,
        int $departmentId,
        string $minimumLevel = 'view'
    ): bool {
        if ($this->hasGlobalScope($userId)) {
            return true;
        }

        $actual = $this->access($userId)[$departmentId] ?? null;

        return $actual !== null
            && (self::ACCESS_RANK[$actual] ?? 0)
                >= (self::ACCESS_RANK[$minimumLevel] ?? PHP_INT_MAX);
    }

    /**
     * @return array<int, string>
     */
    private function access(int $userId): array
    {
        return $this->accessCache[$userId]
            ??= $this->users->departmentAccess($userId);
    }
}

