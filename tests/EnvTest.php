<?php

declare(strict_types=1);

use FastWebsite\Core\Env;

return static function (): void {
    $key = 'FAST_TEST_ENV_' . strtoupper(bin2hex(random_bytes(4)));
    $integerKey = $key . '_PORT';
    $path = tempnam(sys_get_temp_dir(), 'fast-env-');

    if ($path === false) {
        throw new RuntimeException('Could not create an environment test file.');
    }

    try {
        file_put_contents(
            $path,
            sprintf(
                "%s=\"value with spaces\"\n%s=3306\n",
                $key,
                $integerKey
            )
        );
        Env::load($path);

        if (Env::required($key) !== 'value with spaces') {
            throw new RuntimeException('Quoted environment value was not loaded.');
        }

        if (Env::integer($integerKey, 0) !== 3306) {
            throw new RuntimeException('Integer environment value was not loaded.');
        }
    } finally {
        @unlink($path);
        putenv($key);
        putenv($integerKey);
        unset($_ENV[$key], $_ENV[$integerKey]);
    }
};
