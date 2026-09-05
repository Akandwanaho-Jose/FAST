<?php

declare(strict_types=1);

/**
 * TESTING ONLY - fills every remaining photo placeholder across the site
 * (research labs, projects, programmes, and the About-section pages that
 * never had a hero at all) with a photo cycled from the general media
 * library uploaded earlier this session (ids 25-63, the unplaced "site
 * photography" batch). None of these are real matches for their content -
 * this exists purely so every page renders with *something* during layout
 * testing, per explicit user instruction, with the stated intent that real,
 * accurate photos will be swapped in later.
 *
 * Staff profile photos are deliberately excluded - assigning a stranger's
 * photo to a specific named person is a different, worse kind of wrong than
 * a lab or project showing a generic-but-real FAST photo, and the user
 * confirmed staff should stay name-only for now.
 *
 * Idempotent: only fills rows that are currently NULL, never overwrites an
 * already-set hero_media_id (including the 2 real matches wired earlier:
 * the MRI lab and the Advanced Manufacturing lab).
 */

require dirname(__DIR__, 2) . '/bootstrap/autoload.php';

/** @var \FastWebsite\Core\Database $database */
$database = require dirname(__DIR__, 2) . '/bootstrap/database.php';
$connection = $database->connection();

$mediaIds = $connection->query(
    'SELECT id FROM media WHERE id BETWEEN 25 AND 63 ORDER BY id'
)->fetchAll(PDO::FETCH_COLUMN);

if ($mediaIds === []) {
    throw new RuntimeException('No placeholder media rows found (expected ids 25-63).');
}

$cursor = 0;
$next = static function () use ($mediaIds, &$cursor): int {
    $id = $mediaIds[$cursor % count($mediaIds)];
    $cursor++;
    return (int) $id;
};

$result = ['research_units' => 0, 'projects' => 0, 'programmes' => 0, 'pages' => 0, 'page_sections' => 0];

foreach (['research_units', 'projects', 'programmes'] as $table) {
    $ids = $connection->query(
        "SELECT id FROM $table WHERE hero_media_id IS NULL AND deleted_at IS NULL ORDER BY id"
    )->fetchAll(PDO::FETCH_COLUMN);
    $update = $connection->prepare("UPDATE $table SET hero_media_id = :media_id WHERE id = :id AND hero_media_id IS NULL");
    foreach ($ids as $id) {
        $update->execute(['media_id' => $next(), 'id' => (int) $id]);
        $result[$table]++;
    }
}

$pageIds = $connection->query(
    'SELECT id FROM pages WHERE hero_media_id IS NULL AND deleted_at IS NULL ORDER BY id'
)->fetchAll(PDO::FETCH_COLUMN);
$updatePage = $connection->prepare('UPDATE pages SET hero_media_id = :media_id WHERE id = :id AND hero_media_id IS NULL');
foreach ($pageIds as $id) {
    $updatePage->execute(['media_id' => $next(), 'id' => (int) $id]);
    $result['pages']++;
}

$sectionIds = $connection->query(
    "SELECT id FROM page_sections WHERE media_id IS NULL AND section_type IN ('gallery','image_text') ORDER BY id"
)->fetchAll(PDO::FETCH_COLUMN);
$updateSection = $connection->prepare('UPDATE page_sections SET media_id = :media_id WHERE id = :id AND media_id IS NULL');
foreach ($sectionIds as $id) {
    $updateSection->execute(['media_id' => $next(), 'id' => (int) $id]);
    $result['page_sections']++;
}

echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
