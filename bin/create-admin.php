<?php

declare(strict_types=1);

use FastWebsite\Core\ConsolePasswordReader;
use FastWebsite\Core\Database;
use FastWebsite\Services\AdminSetupService;
use FastWebsite\Services\AuditService;
use FastWebsite\Validation\PasswordPolicy;

require dirname(__DIR__) . '/bootstrap/autoload.php';

try {
    $options = getopt('', ['name::', 'email::', 'status']);
    /** @var Database $database */
    $database = require dirname(__DIR__) . '/bootstrap/database.php';
    $connection = $database->connection();
    $service = new AdminSetupService(
        $connection,
        new PasswordPolicy(),
        new AuditService($database)
    );

    if (array_key_exists('status', $options)) {
        $available = $service->isAvailable();
        fwrite(
            STDOUT,
            $available
                ? "Administrator setup is available.\n"
                : "Administrator setup is disabled.\n"
        );
        exit($available ? 0 : 2);
    }

    if (!$service->isAvailable()) {
        throw new RuntimeException(
            'Administrator setup is disabled because a user already exists '
            . 'or the required role is unavailable.'
        );
    }

    $name = trim((string) ($options['name'] ?? ''));
    $email = trim((string) ($options['email'] ?? ''));

    if ($name === '') {
        $name = trim((string) readline('Administrator name: '));
    }

    if ($email === '') {
        $email = trim((string) readline('Administrator email: '));
    }

    $passwordReader = new ConsolePasswordReader();
    $password = $passwordReader->read('Temporary password');
    $confirmation = $passwordReader->read('Confirm temporary password');

    if (!hash_equals($password, $confirmation)) {
        throw new RuntimeException('The temporary passwords do not match.');
    }

    $userId = $service->create($name, $email, $password);

    unset($password, $confirmation);
    fwrite(
        STDOUT,
        sprintf(
            "Administrator created with user ID %d. A password change is required at first login.\n",
            $userId
        )
    );
    exit(0);
} catch (RuntimeException $exception) {
    fwrite(STDERR, 'Administrator setup failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
} catch (Throwable) {
    fwrite(STDERR, "Administrator setup failed safely. Check the database connection and logs.\n");
    exit(1);
}
