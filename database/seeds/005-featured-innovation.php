<?php

declare(strict_types=1);

/**
 * Seeds one real Innovation record: TotoWarm, a newborn-hypothermia
 * wearable device shown in fast1.jpg (media id 33) from this session's
 * site photography. Name and description are taken directly from the
 * product banner legible in the photo itself - not invented. The lead
 * department (Biomedical Sciences and Engineering) and development stage
 * (prototype, based on the bracelet + business-canvas presentation shown)
 * are reasonable inferences from the photo's healthcare-device context,
 * not sourced from written documentation - flagged here for a human to
 * correct or expand if more detail becomes available.
 *
 * Without at least one published innovation, the homepage "Research and
 * Innovation" section always falls back to a plain text placeholder
 * (app/Views/public/home.php line ~238) regardless of what photos exist.
 *
 * Idempotent: skipped if a record with this slug already exists.
 */

require dirname(__DIR__, 2) . '/bootstrap/autoload.php';

/** @var \FastWebsite\Core\Database $database */
$database = require dirname(__DIR__, 2) . '/bootstrap/database.php';
$connection = $database->connection();

$existing = $connection->prepare('SELECT id FROM innovations WHERE slug = :slug LIMIT 1');
$existing->execute(['slug' => 'totowarm']);

if ($existing->fetchColumn() !== false) {
    echo json_encode(['created' => false, 'reason' => 'already exists']) . PHP_EOL;
    exit;
}

$departmentId = (int) $connection
    ->query("SELECT id FROM departments WHERE slug = 'biomedical-engineering' LIMIT 1")
    ->fetchColumn();
$heroMediaId = (int) $connection
    ->query("SELECT id FROM media WHERE file_path LIKE '%85f76ff6b42a3b53c82639c41860915e05756c1c%' LIMIT 1")
    ->fetchColumn();

if ($departmentId < 1 || $heroMediaId < 1) {
    throw new RuntimeException('Required department or media row is missing.');
}

$connection->prepare(
    'INSERT INTO innovations
        (lead_department_id, name, slug, innovation_type, description,
         development_stage, hero_media_id, status, published_at)
     VALUES
        (:department_id, :name, :slug, :type, :description,
         :stage, :hero_media_id, :status, :published_at)'
)->execute([
    'department_id' => $departmentId,
    'name' => 'TotoWarm',
    'slug' => 'totowarm',
    'type' => 'device',
    'description' => 'TotoWarm is a smart wearable bracelet system designed to protect '
        . 'newborns from hypothermia, developed and presented by FAST students at an '
        . 'innovation showcase.',
    'stage' => 'prototype',
    'hero_media_id' => $heroMediaId,
    'status' => 'published',
    'published_at' => date('Y-m-d H:i:s'),
]);

echo json_encode(['created' => true]) . PHP_EOL;
