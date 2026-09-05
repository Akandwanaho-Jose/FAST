<?php

declare(strict_types=1);

namespace FastWebsite\Services;

use FastWebsite\Core\HttpException;
use FastWebsite\Repositories\UserRepository;
use Throwable;

/**
 * User accounts get no admin-set password: create() issues one only the
 * hash of which is ever stored, then reuses PasswordResetService to email
 * the new user a set-your-password link - the same flow "forgot password"
 * already relies on, so there is only one place that ever mails a
 * credential-setup link.
 */
final class UserService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly AuthorizationService $authorization,
        private readonly DepartmentScopeService $scope,
        private readonly AuditService $audit,
        private readonly PasswordResetService $passwordReset
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @param list<int> $roleIds
     */
    public function create(array $data, array $roleIds, int $actorId, string $ip, string $agent): int
    {
        $this->requireGlobal($actorId);
        $userId = $this->transaction(function () use ($data, $roleIds, $actorId, $ip, $agent): int {
            $id = $this->users->insertUser(
                (string) $data['name'],
                (string) $data['email'],
                password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT)
            );
            $this->users->syncRoles($id, $roleIds, $actorId);
            $this->audit->record($actorId, 'users.created', 'user', $id, $ip, $agent, ['roles' => $roleIds]);

            return $id;
        });
        $this->passwordReset->requestReset((string) $data['email'], $ip, $agent);

        return $userId;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<int> $roleIds
     */
    public function update(int $id, array $data, array $roleIds, int $actorId, string $ip, string $agent): void
    {
        $this->requireGlobal($actorId);
        $this->transaction(function () use ($id, $data, $roleIds, $actorId, $ip, $agent): void {
            if ($this->users->findAdminById($id) === null) {
                throw new HttpException(404, 'User not found.');
            }

            $superAdminRoleId = $this->users->roleIdByCode('super_admin');
            $currentRoleIds = $this->users->roleIdsForUser($id);

            if ($superAdminRoleId !== null
                && in_array($superAdminRoleId, $currentRoleIds, true)
                && !in_array($superAdminRoleId, $roleIds, true)
                && $this->users->countUsersWithRole('super_admin', $id) === 0
            ) {
                throw new HttpException(409, 'At least one Super Administrator must remain.');
            }

            $this->users->updateUser($id, (string) $data['name'], (string) $data['email']);
            $this->users->syncRoles($id, $roleIds, $actorId);
            $this->audit->record($actorId, 'users.updated', 'user', $id, $ip, $agent, ['roles' => $roleIds]);
        });
    }

    private function requireGlobal(int $userId): void
    {
        if (!$this->authorization->can($userId, 'users.manage') || !$this->scope->hasGlobalScope($userId)) {
            throw new HttpException(403, 'This user management action requires global access.');
        }
    }

    private function transaction(callable $operation): mixed
    {
        $connection = $this->users->connection();
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
