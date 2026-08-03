<?php

declare(strict_types=1);

namespace FastWebsite\Services;

use FastWebsite\Repositories\UserRepository;

final class AuthorizationService
{
    /**
     * @var array<int, list<string>>
     */
    private array $permissionCache = [];

    /**
     * @var array<int, list<array{code: string, name: string}>>
     */
    private array $roleCache = [];

    public function __construct(private readonly UserRepository $users)
    {
    }

    public function can(int $userId, string $permission): bool
    {
        return in_array($permission, $this->permissions($userId), true);
    }

    /**
     * @return list<string>
     */
    public function permissions(int $userId): array
    {
        return $this->permissionCache[$userId]
            ??= $this->users->permissionCodes($userId);
    }

    /**
     * @return list<array{code: string, name: string}>
     */
    public function roles(int $userId): array
    {
        return $this->roleCache[$userId] ??= $this->users->roles($userId);
    }
}

