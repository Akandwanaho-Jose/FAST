<?php

declare(strict_types=1);

namespace FastWebsite\Services;

use RuntimeException;

final class LoginThrottle
{
    public function __construct(
        private readonly string $storagePath,
        private readonly int $maxAttempts,
        private readonly int $windowSeconds,
        private readonly int $lockSeconds
    ) {
    }

    public function isBlocked(string $email, string $ipAddress): bool
    {
        $state = $this->read($this->key($email, $ipAddress));
        $now = time();

        if (($state['locked_until'] ?? 0) > $now) {
            return true;
        }

        if (($state['window_started_at'] ?? 0) + $this->windowSeconds < $now) {
            $this->clear($email, $ipAddress);
        }

        return false;
    }

    public function recordFailure(string $email, string $ipAddress): void
    {
        $key = $this->key($email, $ipAddress);
        $state = $this->read($key);
        $now = time();

        if (($state['window_started_at'] ?? 0) + $this->windowSeconds < $now) {
            $state = ['attempts' => 0, 'window_started_at' => $now, 'locked_until' => 0];
        }

        $state['attempts'] = (int) ($state['attempts'] ?? 0) + 1;

        if ($state['attempts'] >= $this->maxAttempts) {
            $state['locked_until'] = $now + $this->lockSeconds;
        }

        $this->write($key, $state);
    }

    public function clear(string $email, string $ipAddress): void
    {
        $path = $this->filePath($this->key($email, $ipAddress));

        if (is_file($path)) {
            unlink($path);
        }
    }

    private function key(string $email, string $ipAddress): string
    {
        return hash('sha256', strtolower(trim($email)) . '|' . $ipAddress);
    }

    private function filePath(string $key): string
    {
        return rtrim($this->storagePath, '/\\') . DIRECTORY_SEPARATOR . $key . '.json';
    }

    /**
     * @return array{attempts?: int, window_started_at?: int, locked_until?: int}
     */
    private function read(string $key): array
    {
        $path = $this->filePath($key);

        if (!is_file($path)) {
            return [];
        }

        $contents = file_get_contents($path);
        $state = is_string($contents) ? json_decode($contents, true) : null;

        return is_array($state) ? $state : [];
    }

    /**
     * @param array{attempts: int, window_started_at: int, locked_until: int} $state
     */
    private function write(string $key, array $state): void
    {
        if (!is_dir($this->storagePath)
            && !mkdir($this->storagePath, 0775, true)
            && !is_dir($this->storagePath)
        ) {
            throw new RuntimeException('Login throttle storage could not be created.');
        }

        $encoded = json_encode($state, JSON_THROW_ON_ERROR);

        if (file_put_contents($this->filePath($key), $encoded, LOCK_EX) === false) {
            throw new RuntimeException('Login throttle state could not be written.');
        }
    }
}

