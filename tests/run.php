<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/autoload.php';

$testFiles = glob(__DIR__ . '/*Test.php') ?: [];
$failures = [];

foreach ($testFiles as $testFile) {
    $name = basename($testFile);

    try {
        $test = require $testFile;
        $test();
        fwrite(STDOUT, sprintf("PASS %s\n", $name));
    } catch (Throwable $exception) {
        $failures[] = $name;
        fwrite(STDERR, sprintf("FAIL %s: %s\n", $name, $exception->getMessage()));
    }
}

fwrite(
    STDOUT,
    sprintf(
        "\n%d test(s), %d failure(s).\n",
        count($testFiles),
        count($failures)
    )
);

exit($failures === [] ? 0 : 1);

