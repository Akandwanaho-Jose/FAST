<?php

declare(strict_types=1);

namespace FastWebsite\Validation;

final class PasswordPolicy
{
    /**
     * @return list<string>
     */
    public function validate(string $password): array
    {
        $errors = [];

        if (strlen($password) < 12) {
            $errors[] = 'Use at least 12 characters.';
        }

        if (strlen($password) > 255) {
            $errors[] = 'Use no more than 255 characters.';
        }

        if (preg_match('/[a-z]/', $password) !== 1) {
            $errors[] = 'Include a lowercase letter.';
        }

        if (preg_match('/[A-Z]/', $password) !== 1) {
            $errors[] = 'Include an uppercase letter.';
        }

        if (preg_match('/[0-9]/', $password) !== 1) {
            $errors[] = 'Include a number.';
        }

        if (preg_match('/[^a-zA-Z0-9]/', $password) !== 1) {
            $errors[] = 'Include a symbol.';
        }

        return $errors;
    }
}

