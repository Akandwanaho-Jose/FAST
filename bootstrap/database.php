<?php

declare(strict_types=1);

use FastWebsite\Core\Database;
use FastWebsite\Core\Env;

require __DIR__ . '/autoload.php';

Env::load(dirname(__DIR__) . '/.env');

/** @var array<string, mixed> $configuration */
$configuration = require dirname(__DIR__) . '/config/database.php';

return new Database($configuration);

