<?php

declare(strict_types=1);

$composerAutoloader = dirname(__DIR__) . '/vendor/autoload.php';

if (is_file($composerAutoloader)) {
    require $composerAutoloader;

    return;
}

spl_autoload_register(
    static function (string $class): void {
        $prefix = 'FastWebsite\\';

        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relativeClass = substr($class, strlen($prefix));
        $file = dirname(__DIR__) . '/app/'
            . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass)
            . '.php';

        if (is_file($file)) {
            require $file;
        }
    }
);

