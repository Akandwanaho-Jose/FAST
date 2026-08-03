<?php

declare(strict_types=1);

use FastWebsite\Core\Csrf;
use FastWebsite\Core\Session;

return static function (): void {
    $_SESSION = [];
    $csrf = new Csrf(new Session('fast_test_session', 5));
    $token = $csrf->token();

    if (!$csrf->validate($token)) {
        throw new RuntimeException('A valid CSRF token was rejected.');
    }

    if ($csrf->validate('invalid-token')) {
        throw new RuntimeException('An invalid CSRF token was accepted.');
    }

    $csrf->rotate();

    if ($csrf->validate($token)) {
        throw new RuntimeException('A rotated CSRF token remained valid.');
    }

    $_SESSION = [];
};

