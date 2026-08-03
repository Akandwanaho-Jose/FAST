<?php

declare(strict_types=1);

use FastWebsite\Core\Database;

/** @var Database $database */
$database = require dirname(__DIR__) . '/bootstrap/database.php';
$connection = $database->connection();
$tables = ['research_themes', 'research_theme_departments', 'partners', 'project_themes', 'project_sdgs', 'project_partners', 'publication_themes'];

foreach ($tables as $table) {
    $count = (int) $connection->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    fwrite(STDOUT, sprintf("%-30s %d\n", $table, $count));
}

$sdgCount = (int) $connection->query('SELECT COUNT(*) FROM sdgs')->fetchColumn();
fwrite(STDOUT, sprintf("%-30s %d\n", 'sdgs', $sdgCount));
if ($sdgCount !== 17) {
    fwrite(STDERR, "Expected exactly 17 seeded SDGs.\n");
    exit(1);
}
