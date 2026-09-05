<?php

declare(strict_types=1);

namespace FastWebsite\Repositories;

use FastWebsite\Core\Database;
use PDO;

final class PasswordResetRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    /**
     * The expiry is computed by MySQL itself (NOW() + INTERVAL), not passed
     * in as a PHP-formatted datetime - PHP's and the database server's
     * clocks are not guaranteed to agree on timezone, and findValid()'s
     * "expires_at > NOW()" check must be measured against the same clock
     * that set expires_at in the first place, or a skew in either direction
     * silently expires tokens immediately or makes them last too long.
     */
    public function create(int $userId, string $tokenHash, int $ttlMinutes): void
    {
        $statement = $this->connection()->prepare(
            'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at)
             VALUES (:user_id, :token_hash, DATE_ADD(NOW(), INTERVAL :ttl_minutes MINUTE))'
        );
        $statement->execute([
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'ttl_minutes' => $ttlMinutes,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findValid(string $tokenHash): ?array
    {
        $statement = $this->connection()->prepare(
            'SELECT id, user_id, expires_at
             FROM password_reset_tokens
             WHERE token_hash = :token_hash
               AND used_at IS NULL
               AND expires_at > NOW()
             LIMIT 1'
        );
        $statement->execute(['token_hash' => $tokenHash]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    public function markUsed(int $id): void
    {
        $statement = $this->connection()->prepare(
            'UPDATE password_reset_tokens SET used_at = NOW() WHERE id = :id'
        );
        $statement->execute(['id' => $id]);
    }

    public function deleteForUser(int $userId): void
    {
        $statement = $this->connection()->prepare(
            'DELETE FROM password_reset_tokens WHERE user_id = :user_id'
        );
        $statement->execute(['user_id' => $userId]);
    }

    private function connection(): PDO
    {
        return $this->database->connection();
    }
}
