<?php

declare(strict_types=1);

namespace FastWebsite\Core;

use InvalidArgumentException;
use PDO;

final class Database
{
    private ?PDO $connection = null;

    /**
     * @param array<string, mixed> $configuration
     */
    public function __construct(private readonly array $configuration)
    {
    }

    public function connection(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        $driver = (string) ($this->configuration['driver'] ?? '');
        $charset = (string) ($this->configuration['charset'] ?? '');
        $collation = (string) (
            $this->configuration['collation'] ?? 'utf8mb4_unicode_ci'
        );

        if ($driver !== 'mysql') {
            throw new InvalidArgumentException('Only the mysql PDO driver is supported.');
        }

        if (preg_match('/^[a-zA-Z0-9_]+$/', $charset) !== 1) {
            throw new InvalidArgumentException('The configured database charset is invalid.');
        }

        if (preg_match('/^[a-zA-Z0-9_]+$/', $collation) !== 1) {
            throw new InvalidArgumentException('The configured database collation is invalid.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            (string) $this->configuration['host'],
            (int) $this->configuration['port'],
            (string) $this->configuration['database'],
            $charset
        );

        $mysqlInitCommandAttribute = class_exists(\Pdo\Mysql::class)
            ? \Pdo\Mysql::ATTR_INIT_COMMAND
            : constant('PDO::MYSQL_ATTR_INIT_COMMAND');

        $this->connection = new PDO(
            $dsn,
            (string) $this->configuration['username'],
            (string) $this->configuration['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
                $mysqlInitCommandAttribute => sprintf(
                    'SET NAMES %s COLLATE %s',
                    $charset,
                    $collation
                ),
            ]
        );

        return $this->connection;
    }
}
