<?php

declare(strict_types=1);

namespace FastWebsite\Core;

use RuntimeException;

final class Logger
{
    public function __construct(private readonly string $path)
    {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void
    {
        $directory = dirname($this->path);

        if (!is_dir($directory)
            && !mkdir($directory, 0775, true)
            && !is_dir($directory)
        ) {
            throw new RuntimeException('The log directory could not be created.');
        }

        $entry = json_encode(
            [
                'timestamp' => date(DATE_ATOM),
                'level' => 'error',
                'message' => $message,
                'context' => $context,
            ],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if ($entry === false
            || file_put_contents($this->path, $entry . PHP_EOL, FILE_APPEND | LOCK_EX) === false
        ) {
            throw new RuntimeException('The application log could not be written.');
        }
    }
}

