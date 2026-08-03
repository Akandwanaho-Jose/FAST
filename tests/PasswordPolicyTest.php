<?php

declare(strict_types=1);

use FastWebsite\Validation\PasswordPolicy;

return static function (): void {
    $policy = new PasswordPolicy();

    if ($policy->validate('StrongPassword#123') !== []) {
        throw new RuntimeException('A strong password was rejected.');
    }

    if (count($policy->validate('weak')) < 3) {
        throw new RuntimeException('A weak password was not rejected thoroughly.');
    }
};

