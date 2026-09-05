<?php

declare(strict_types=1);

namespace FastWebsite\Validation;

use FastWebsite\Repositories\UserRepository;

final class UserValidator
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{data: array<string, mixed>, roleIds: list<int>, errors: list<string>}
     */
    public function validate(array $input, ?int $existingId = null): array
    {
        $errors = [];
        $name = trim((string) ($input['name'] ?? ''));
        $email = strtolower(trim((string) ($input['email'] ?? '')));

        if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 200) {
            $errors[] = 'Name must be 2 to 200 characters.';
        }

        if ($email === '' || mb_strlen($email) > 190 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'Enter a valid email address.';
        } elseif ($this->users->emailExistsForAdmin($email, $existingId)) {
            $errors[] = 'That email address is already in use.';
        }

        $availableRoleIds = array_map('intval', array_column($this->users->allRoles(), 'id'));
        $roleIds = [];

        foreach (is_array($input['role_ids'] ?? null) ? $input['role_ids'] : [] as $value) {
            $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false || !in_array((int) $id, $availableRoleIds, true)) {
                $errors[] = 'Select valid roles.';
                continue;
            }
            $roleIds[] = (int) $id;
        }

        $roleIds = array_values(array_unique($roleIds));

        if ($roleIds === []) {
            $errors[] = 'Select at least one role.';
        }

        return [
            'data' => ['name' => $name, 'email' => $email],
            'roleIds' => $roleIds,
            'errors' => $errors,
        ];
    }
}
