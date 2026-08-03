<?php

declare(strict_types=1);

use FastWebsite\Core\Env;

return [
    'driver' => Env::get('DB_CONNECTION', 'mysql'),
    'host' => Env::get('DB_HOST', '127.0.0.1'),
    'port' => Env::integer('DB_PORT', 3306),
    'database' => Env::get('DB_DATABASE', 'fast_website_db'),
    'username' => Env::required('DB_USERNAME'),
    'password' => Env::required('DB_PASSWORD', allowEmpty: true),
    'charset' => Env::get('DB_CHARSET', 'utf8mb4'),
    'collation' => Env::get('DB_COLLATION', 'utf8mb4_unicode_ci'),
];
