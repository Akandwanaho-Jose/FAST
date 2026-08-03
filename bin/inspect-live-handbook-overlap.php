<?php

declare(strict_types=1);

$database = require dirname(__DIR__) . '/bootstrap/database.php';
$statement = $database->connection()->prepare(
    'SELECT s.id, s.slug, s.short_biography, s.institutional_email,
            sd.department_id, sp.position_id, sp.title_override, p.code AS position_code
     FROM staff s
     LEFT JOIN staff_departments sd ON sd.staff_id = s.id AND sd.is_primary = 1
     LEFT JOIN staff_positions sp ON sp.staff_id = s.id AND sp.is_current = 1
     LEFT JOIN positions p ON p.id = sp.position_id
     WHERE s.id = 9'
);
$statement->execute();
echo json_encode($statement->fetch(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL;
