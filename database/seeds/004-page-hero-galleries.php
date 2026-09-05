<?php

declare(strict_types=1);

/**
 * Replaces the synthetic placeholder image (media id 1) on the 9 pages and
 * 18 gallery-type page_sections that were still using it, with real
 * photography already uploaded to the media library this session
 * (database/seeds/003-site-photography.php, media ids 25-63).
 *
 * Matches chosen by content relevance from the photo descriptions already
 * gathered this session. A few pages (professional-bodies, the two
 * mentorship pages, meet-our-mentors) have no photo that specifically
 * depicts their exact topic (no professional-body branding or one-on-one
 * mentoring shots exist in the supplied folder) - those use the closest
 * generic/plausible match available rather than a precise one. This is
 * noted so an admin can swap them for something more specific later.
 *
 * Idempotent: only updates rows still pointing at the placeholder (id 1).
 */

require dirname(__DIR__, 2) . '/bootstrap/autoload.php';

/** @var \FastWebsite\Core\Database $database */
$database = require dirname(__DIR__, 2) . '/bootstrap/database.php';
$connection = $database->connection();

$pageHeroes = [
    'industrial-training' => 38,
    'community-outreach' => 35,
    'partnerships' => 61,
    'engineering-education' => 58,
    'student-life' => 45,
    'professional-bodies' => 25,
    'student-mentorship-programme' => 30,
    'faculty-mentorship-programme' => 32,
    'meet-our-mentors' => 50,
];

$sectionGalleries = [
    'industrial-training' => [26, 29],
    'community-outreach' => [33, 27],
    'partnerships' => [63, 41],
    'engineering-education' => [59, 56],
    'student-life' => [47, 49],
    'professional-bodies' => [46, 60],
    'student-mentorship-programme' => [62, 39],
    'faculty-mentorship-programme' => [31, 40],
    'meet-our-mentors' => [48, 55],
];

$heroUpdated = 0;
$sectionUpdated = 0;

$heroStatement = $connection->prepare(
    'UPDATE pages SET hero_media_id = :media_id WHERE slug = :slug AND hero_media_id = 1'
);
foreach ($pageHeroes as $slug => $mediaId) {
    $heroStatement->execute(['media_id' => $mediaId, 'slug' => $slug]);
    $heroUpdated += $heroStatement->rowCount();
}

$sectionSelect = $connection->prepare(
    'SELECT ps.id
     FROM page_sections ps
     INNER JOIN pages p ON p.id = ps.page_id
     WHERE p.slug = :slug AND ps.media_id = 1
     ORDER BY ps.display_order, ps.id
     LIMIT 2'
);
$sectionUpdate = $connection->prepare(
    'UPDATE page_sections SET media_id = :media_id WHERE id = :id AND media_id = 1'
);
foreach ($sectionGalleries as $slug => $mediaIds) {
    $sectionSelect->execute(['slug' => $slug]);
    $sectionIds = $sectionSelect->fetchAll(PDO::FETCH_COLUMN);
    foreach ($sectionIds as $index => $sectionId) {
        if (!isset($mediaIds[$index])) {
            continue;
        }
        $sectionUpdate->execute(['media_id' => $mediaIds[$index], 'id' => (int) $sectionId]);
        $sectionUpdated += $sectionUpdate->rowCount();
    }
}

echo json_encode([
    'pages_updated' => $heroUpdated,
    'sections_updated' => $sectionUpdated,
], JSON_PRETTY_PRINT) . PHP_EOL;
