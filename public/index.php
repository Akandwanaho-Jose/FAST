<?php

declare(strict_types=1);

use FastWebsite\Core\Application;
use FastWebsite\Core\Request;

/** @var Application $application */
$application = require dirname(__DIR__) . '/bootstrap/app.php';
$application->handle(Request::capture())->send();

