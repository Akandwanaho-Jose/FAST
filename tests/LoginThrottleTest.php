<?php

declare(strict_types=1);

use FastWebsite\Services\LoginThrottle;

return static function (): void {
    $storage = sys_get_temp_dir()
        . DIRECTORY_SEPARATOR
        . 'fast-throttle-'
        . bin2hex(random_bytes(5));
    $throttle = new LoginThrottle($storage, 2, 60, 60);
    $email = 'throttle-test@example.invalid';
    $ipAddress = '127.0.0.9';

    try {
        if ($throttle->isBlocked($email, $ipAddress)) {
            throw new RuntimeException('A new throttle key started blocked.');
        }

        $throttle->recordFailure($email, $ipAddress);

        if ($throttle->isBlocked($email, $ipAddress)) {
            throw new RuntimeException('Throttle blocked too early.');
        }

        $throttle->recordFailure($email, $ipAddress);

        if (!$throttle->isBlocked($email, $ipAddress)) {
            throw new RuntimeException('Throttle did not block at its limit.');
        }

        $throttle->clear($email, $ipAddress);

        if ($throttle->isBlocked($email, $ipAddress)) {
            throw new RuntimeException('Throttle did not clear after success.');
        }
    } finally {
        if (is_dir($storage)) {
            foreach (glob($storage . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }

            rmdir($storage);
        }
    }
};

