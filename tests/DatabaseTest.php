<?php

declare(strict_types=1);

use FastWebsite\Core\Database;

return static function (): void {
    $database = new Database([
        'driver' => 'unsupported',
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'fast_website_db',
        'username' => 'unused',
        'password' => 'unused',
        'charset' => 'utf8mb4',
    ]);

    try {
        $database->connection();
    } catch (InvalidArgumentException) {
        return;
    }

    throw new RuntimeException('Unsupported PDO driver was not rejected.');
};

