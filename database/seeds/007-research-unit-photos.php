<?php

declare(strict_types=1);

/**
 * Wires 2 confidently-matched photos (uploaded earlier this session) to the
 * specific research labs they actually depict, by setting
 * research_units.hero_media_id. Only these 2 matches are made - no generic
 * photo is forced onto any other lab.
 *
 * Match 1: the low-field MRI research apparatus photo -> "Medical Imaging,
 * Virtual Reality and Artificial Intelligence Laboratory" (Department of
 * Biomedical Sciences and Engineering). Chosen over other Biomedical labs
 * because its own name and overview ("advance medical imaging, artificial
 * intelligence, virtual reality... for disease diagnosis") are the closest,
 * most literal match to an MRI apparatus photo - not a generic biomedical
 * engineering lab guess.
 *
 * Match 2: the 3D printers / fabrication lab photo -> "Advanced Manufacturing
 * and Digital Fabrication Laboratory" (Department of Mechanical and
 * Industrial Engineering), whose overview explicitly covers "rapid
 * prototyping" and "modern manufacturing technologies".
 *
 * Idempotent: each update is skipped if the target research unit already
 * has a hero_media_id set, or if the expected media/unit rows cannot be
 * found.
 */

require dirname(__DIR__, 2) . '/bootstrap/autoload.php';

/** @var \FastWebsite\Core\Database $database */
$database = require dirname(__DIR__, 2) . '/bootstrap/database.php';
$connection = $database->connection();

$matches = [
    [
        'media_alt' => 'A low-field MRI research apparatus in a laboratory',
        'unit_name' => 'Medical Imaging, Virtual Reality and Artificial Intelligence Laboratory',
    ],
    [
        'media_alt' => '3D printers in a fabrication laboratory',
        'unit_name' => 'Advanced Manufacturing and Digital Fabrication Laboratory',
    ],
];

$results = [];

foreach ($matches as $match) {
    $mediaStatement = $connection->prepare(
        'SELECT id FROM media WHERE alt_text = :alt AND deleted_at IS NULL ORDER BY id LIMIT 1'
    );
    $mediaStatement->execute(['alt' => $match['media_alt']]);
    $mediaId = (int) $mediaStatement->fetchColumn();

    $unitStatement = $connection->prepare(
        'SELECT id, hero_media_id FROM research_units WHERE name = :name AND deleted_at IS NULL LIMIT 1'
    );
    $unitStatement->execute(['name' => $match['unit_name']]);
    $unit = $unitStatement->fetch();

    if ($mediaId < 1 || !is_array($unit)) {
        $results[] = ['unit' => $match['unit_name'], 'updated' => false, 'reason' => 'media or research unit not found'];
        continue;
    }

    if ($unit['hero_media_id'] !== null) {
        $results[] = ['unit' => $match['unit_name'], 'updated' => false, 'reason' => 'hero_media_id already set'];
        continue;
    }

    $connection->prepare(
        'UPDATE research_units SET hero_media_id = :media_id WHERE id = :id'
    )->execute(['media_id' => $mediaId, 'id' => (int) $unit['id']]);

    $results[] = ['unit' => $match['unit_name'], 'updated' => true, 'media_id' => $mediaId];
}

echo json_encode($results, JSON_PRETTY_PRINT) . PHP_EOL;
