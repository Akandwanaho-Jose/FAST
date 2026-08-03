<?php

declare(strict_types=1);

namespace FastWebsite\Core;

use PDO;
use RuntimeException;

final class DatabaseVerifier
{
    /**
     * @var array<string, int>
     */
    private const EXPECTED_SEED_COUNTS = [
        'faculties' => 1,
        'departments' => 5,
        'roles' => 9,
        'permissions' => 31,
        'sdgs' => 17,
    ];

    /**
     * @var list<string>
     */
    private const REQUIRED_TABLES = [
        'users',
        'roles',
        'permissions',
        'user_roles',
        'role_permissions',
        'user_departments',
        'faculties',
        'departments',
        'content_revisions',
        'content_approvals',
        'audit_logs',
    ];

    public function __construct(
        private readonly PDO $connection,
        private readonly string $expectedDatabase = 'fast_website_db'
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function verify(): array
    {
        $this->assertConnection();
        $server = $this->serverDetails();
        $database = (string) $server['database_name'];

        if ($database !== $this->expectedDatabase) {
            throw new RuntimeException('The connection selected an unexpected database.');
        }

        $tableCount = $this->countSchemaObjects('TABLES', "TABLE_TYPE = 'BASE TABLE'");
        $foreignKeyCount = $this->countSchemaObjects('REFERENTIAL_CONSTRAINTS');
        $checkCount = $this->countChecks();
        $expectedCheckCount = str_contains(
            strtolower((string) $server['server_version']),
            'mariadb'
        ) ? 20 : 16;
        $missingTables = $this->missingRequiredTables();
        $seedCounts = $this->seedCounts();

        return [
            'connected' => true,
            'database' => $database,
            'server_version' => $server['server_version'],
            'connection_charset' => $server['connection_charset'],
            'connection_collation' => $server['connection_collation'],
            'session_timezone' => $server['session_timezone'],
            'tables' => [
                'actual' => $tableCount,
                'expected' => 82,
                'matches' => $tableCount === 82,
            ],
            'foreign_keys' => [
                'actual' => $foreignKeyCount,
                'expected' => 128,
                'matches' => $foreignKeyCount === 128,
            ],
            'checks' => [
                'actual' => $checkCount,
                'expected' => $expectedCheckCount,
                'matches' => $checkCount === $expectedCheckCount,
                'note' => $expectedCheckCount === 20
                    ? 'Includes four MariaDB JSON_VALID constraints.'
                    : 'Counts the 16 explicit schema checks.',
            ],
            'required_tables_missing' => $missingTables,
            'seed_counts' => $seedCounts,
            'privileges' => $this->privilegeAssessment(),
        ];
    }

    private function assertConnection(): void
    {
        $result = $this->connection->query('SELECT 1')->fetchColumn();

        if ((int) $result !== 1) {
            throw new RuntimeException('The database connection health query failed.');
        }
    }

    /**
     * @return array<string, string>
     */
    private function serverDetails(): array
    {
        $statement = $this->connection->query(
            'SELECT DATABASE() AS database_name,
                    VERSION() AS server_version,
                    @@character_set_connection AS connection_charset,
                    @@collation_connection AS connection_collation,
                    @@session.time_zone AS session_timezone'
        );
        $row = $statement->fetch();

        if (!is_array($row)) {
            throw new RuntimeException('Database server details could not be read.');
        }

        return array_map(static fn (mixed $value): string => (string) $value, $row);
    }

    private function countSchemaObjects(string $table, string $extraWhere = '1 = 1'): int
    {
        $allowedTables = ['TABLES', 'REFERENTIAL_CONSTRAINTS'];

        if (!in_array($table, $allowedTables, true)) {
            throw new RuntimeException('Unsupported information schema table.');
        }

        $sql = sprintf(
            'SELECT COUNT(*)
             FROM information_schema.%s
             WHERE %s_SCHEMA = :schema AND %s',
            $table,
            $table === 'TABLES' ? 'TABLE' : 'CONSTRAINT',
            $extraWhere
        );
        $statement = $this->connection->prepare($sql);
        $statement->execute(['schema' => $this->expectedDatabase]);

        return (int) $statement->fetchColumn();
    }

    private function countChecks(): int
    {
        $statement = $this->connection->prepare(
            "SELECT COUNT(*)
             FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = :schema
               AND CONSTRAINT_TYPE = 'CHECK'"
        );
        $statement->execute(['schema' => $this->expectedDatabase]);

        return (int) $statement->fetchColumn();
    }

    /**
     * @return list<string>
     */
    private function missingRequiredTables(): array
    {
        $placeholders = implode(
            ', ',
            array_fill(0, count(self::REQUIRED_TABLES), '?')
        );
        $statement = $this->connection->prepare(
            "SELECT TABLE_NAME
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ?
               AND TABLE_TYPE = 'BASE TABLE'
               AND TABLE_NAME IN ($placeholders)"
        );
        $statement->execute([$this->expectedDatabase, ...self::REQUIRED_TABLES]);
        $present = $statement->fetchAll(PDO::FETCH_COLUMN);

        return array_values(array_diff(self::REQUIRED_TABLES, $present));
    }

    /**
     * @return array<string, array{actual: int, expected_minimum: int, meets_minimum: bool}>
     */
    private function seedCounts(): array
    {
        $results = [];

        foreach (self::EXPECTED_SEED_COUNTS as $table => $expectedMinimum) {
            // Table names come only from the fixed allowlist above.
            $actual = (int) $this->connection
                ->query(sprintf('SELECT COUNT(*) FROM `%s`', $table))
                ->fetchColumn();
            $results[$table] = [
                'actual' => $actual,
                'expected_minimum' => $expectedMinimum,
                'meets_minimum' => $actual >= $expectedMinimum,
            ];
        }

        return $results;
    }

    /**
     * @return array{least_privilege: bool, warnings: list<string>}
     */
    private function privilegeAssessment(): array
    {
        $grants = $this->connection->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN);
        $warnings = [];
        $dangerousPrivileges = [
            'ALL PRIVILEGES',
            'CREATE',
            'ALTER',
            'DROP',
            'FILE',
            'GRANT OPTION',
            'SUPER',
        ];

        foreach ($grants as $grant) {
            $upperGrant = strtoupper((string) $grant);

            foreach ($dangerousPrivileges as $privilege) {
                if (preg_match(
                    '/(?:GRANT |, )' . preg_quote($privilege, '/') . '(?:,| ON| TO)/',
                    $upperGrant
                ) === 1) {
                    $warnings[$privilege] = sprintf(
                        'Application account has elevated %s privilege.',
                        $privilege
                    );
                }
            }
        }

        return [
            'least_privilege' => $warnings === [],
            'warnings' => array_values($warnings),
        ];
    }
}
