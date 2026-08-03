<?php

declare(strict_types=1);

use FastWebsite\Core\Env;

return [
    'name' => Env::get('APP_NAME', 'FAST Website'),
    'environment' => Env::get('APP_ENV', 'production'),
    'debug' => Env::boolean('APP_DEBUG', false),
    'timezone' => 'Africa/Kampala',
    'log_path' => dirname(__DIR__) . '/storage/logs/application.log',
    'php_error_log_path' => dirname(__DIR__) . '/storage/logs/php-error.log',
    'views_path' => dirname(__DIR__) . '/app/Views',
    'session' => [
        'name' => Env::get('SESSION_NAME', 'fast_session'),
        'lifetime_minutes' => Env::integer('SESSION_LIFETIME_MINUTES', 120),
    ],
    'authentication' => [
        'max_attempts' => Env::integer('LOGIN_MAX_ATTEMPTS', 5),
        'window_minutes' => Env::integer('LOGIN_WINDOW_MINUTES', 15),
        'lock_minutes' => Env::integer('LOGIN_LOCK_MINUTES', 15),
        'throttle_path' => dirname(__DIR__) . '/storage/cache/login',
    ],
    'uploads' => [
        'public_path' => dirname(__DIR__) . '/public',
        'maximum_image_bytes' => Env::integer('MAX_IMAGE_UPLOAD_MB', 20) * 1024 * 1024,
        'maximum_document_bytes' => 20 * 1024 * 1024,
    ],
];
