<?php

declare(strict_types=1);

namespace FastWebsite\Services;

final class AuthResult
{
    /**
     * @param array<string, mixed>|null $user
     */
    private function __construct(
        public readonly bool $successful,
        public readonly ?array $user = null
    ) {
    }

    /**
     * @param array<string, mixed> $user
     */
    public static function success(array $user): self
    {
        return new self(true, $user);
    }

    public static function failure(): self
    {
        return new self(false);
    }
}

