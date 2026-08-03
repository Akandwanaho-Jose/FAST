<?php

declare(strict_types=1);

namespace FastWebsite\Core;

use RuntimeException;

final class Env
{
    private const KEY_PATTERN = '/^[A-Z_][A-Z0-9_]*$/';

    public static function load(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            throw new RuntimeException('The environment file could not be read.');
        }

        foreach ($lines as $lineNumber => $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, 'export ')) {
                $line = trim(substr($line, 7));
            }

            $separator = strpos($line, '=');

            if ($separator === false) {
                throw new RuntimeException(sprintf(
                    'Invalid environment entry on line %d.',
                    $lineNumber + 1
                ));
            }

            $key = trim(substr($line, 0, $separator));
            $value = trim(substr($line, $separator + 1));

            if (preg_match(self::KEY_PATTERN, $key) !== 1) {
                throw new RuntimeException(sprintf(
                    'Invalid environment key on line %d.',
                    $lineNumber + 1
                ));
            }

            if (self::exists($key)) {
                continue;
            }

            $value = self::normalizeValue($value);
            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = $_ENV[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            return $default;
        }

        return (string) $value;
    }

    public static function required(string $key, bool $allowEmpty = false): string
    {
        $value = self::get($key);

        if ($value === null || (!$allowEmpty && $value === '')) {
            throw new RuntimeException(sprintf(
                'Required environment variable %s is not configured.',
                $key
            ));
        }

        return $value;
    }

    public static function integer(string $key, int $default): int
    {
        $value = self::get($key);

        if ($value === null || $value === '') {
            return $default;
        }

        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new RuntimeException(sprintf(
                'Environment variable %s must be an integer.',
                $key
            ));
        }

        return (int) $value;
    }

    public static function boolean(string $key, bool $default): bool
    {
        $value = self::get($key);

        if ($value === null || $value === '') {
            return $default;
        }

        $validated = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        if ($validated === null) {
            throw new RuntimeException(sprintf(
                'Environment variable %s must be a boolean.',
                $key
            ));
        }

        return $validated;
    }

    private static function exists(string $key): bool
    {
        return array_key_exists($key, $_ENV) || getenv($key) !== false;
    }

    private static function normalizeValue(string $value): string
    {
        $length = strlen($value);

        if ($length < 2) {
            return $value;
        }

        $first = $value[0];
        $last = $value[$length - 1];

        if (($first === '"' && $last === '"')
            || ($first === "'" && $last === "'")
        ) {
            $value = substr($value, 1, -1);

            return $first === '"' ? stripcslashes($value) : $value;
        }

        return $value;
    }
}
