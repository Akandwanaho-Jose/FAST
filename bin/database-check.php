<?php

declare(strict_types=1);

use FastWebsite\Core\Database;
use FastWebsite\Core\DatabaseVerifier;

require dirname(__DIR__) . '/bootstrap/autoload.php';

try {
    /** @var Database $database */
    $database = require dirname(__DIR__) . '/bootstrap/database.php';
    $verifier = new DatabaseVerifier($database->connection());
    $result = $verifier->verify();

    fwrite(
        STDOUT,
        json_encode(
            $result,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ) . PHP_EOL
    );

    $healthy = $result['tables']['matches']
        && $result['foreign_keys']['matches']
        && $result['checks']['matches']
        && $result['required_tables_missing'] === []
        && $result['privileges']['least_privilege'];

    exit($healthy ? 0 : 2);
} catch (Throwable $exception) {
    fwrite(
        STDERR,
        "Database verification could not complete. "
        . "Check the local DB_* environment settings and database access."
        . PHP_EOL
    );

    exit(1);
}

