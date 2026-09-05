<?php

declare(strict_types=1);

use FastWebsite\Core\Database;
use FastWebsite\Repositories\MediaRepository;
use FastWebsite\Services\MediaUploadService;

require dirname(__DIR__, 2) . '/bootstrap/autoload.php';

/**
 * Site photography seed -- uploads the 48-file general site/event photo set
 * recovered at D:\FAST-CONTENT\Fast Website\Pictures\ into the media table
 * and wires the confirmed homepage-hero and department-hero placements.
 *
 * Idempotent: every source file is looked up in `media` by its sanitized
 * original filename (as `original_name`) before conversion/upload, so a
 * rerun reuses the existing media row and simply re-applies the hero_slides
 * / departments.hero_media_id wiring instead of creating duplicates.
 *
 * Excluded entirely: fast23.jpg (confirmed byte-identical duplicate of
 * fast22.jpg -- same size, same visual content).
 *
 * Every source photo is converted to image/webp (resized so the longest
 * edge is at most 1600px) before being handed to MediaUploadService::store(),
 * matching the conversion pattern already used for staff portraits this
 * session (public/uploads/staff/handbook-2025-2026/*.webp).
 */

/** @var Database $database */
$database = require dirname(__DIR__, 2) . '/bootstrap/database.php';
$pdo = $database->connection();

$appConfiguration = require dirname(__DIR__, 2) . '/config/app.php';
$uploadConfiguration = $appConfiguration['uploads'];

$mediaRepository = new MediaRepository($database);
$mediaUploads = new MediaUploadService(
    $mediaRepository,
    (string) $uploadConfiguration['public_path'],
    (int) $uploadConfiguration['maximum_image_bytes']
);

$sourceDir = 'D:/FAST-CONTENT/Fast Website/Pictures';
if (!is_dir($sourceDir)) {
    throw new RuntimeException("Source photography directory not found: {$sourceDir}");
}

// Attribute uploads to the seeded administrator (Joseph) if present.
$uploadedBy = (int) $pdo->query(
    "SELECT id FROM users WHERE email = 'jakandwanaho@must.ac.ug' LIMIT 1"
)->fetchColumn();
if ($uploadedBy < 1) {
    $uploadedBy = (int) $pdo->query('SELECT id FROM users ORDER BY id LIMIT 1')->fetchColumn();
}
if ($uploadedBy < 1) {
    throw new RuntimeException('No user row found to attribute uploads to (uploaded_by is a required FK here).');
}

$tempDir = sys_get_temp_dir() . '/fast-site-photography';
if (!is_dir($tempDir) && !mkdir($tempDir, 0755, true) && !is_dir($tempDir)) {
    throw new RuntimeException("Could not create temp conversion directory: {$tempDir}");
}

const MAX_EDGE = 1600;
const WEBP_QUALITY = 85;

/**
 * Convert a source image (jpeg/png/webp/gif) to a resized webp file in $tempDir.
 *
 * @return array{tmp_path: string, width: int, height: int, size: int}
 */
function convertToWebp(string $sourcePath, string $tempDir): array
{
    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($sourcePath);

    $image = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($sourcePath),
        'image/png' => @imagecreatefrompng($sourcePath),
        'image/webp' => @imagecreatefromwebp($sourcePath),
        'image/gif' => @imagecreatefromgif($sourcePath),
        default => null,
    };

    if (!$image instanceof GdImage) {
        throw new RuntimeException("Unsupported or unreadable image ({$mime}): {$sourcePath}");
    }

    $originalWidth = imagesx($image);
    $originalHeight = imagesy($image);
    $longestEdge = max($originalWidth, $originalHeight);

    $targetWidth = $originalWidth;
    $targetHeight = $originalHeight;

    if ($longestEdge > MAX_EDGE) {
        $scale = MAX_EDGE / $longestEdge;
        $targetWidth = max(1, (int) round($originalWidth * $scale));
        $targetHeight = max(1, (int) round($originalHeight * $scale));

        $resized = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
        imagefilledrectangle($resized, 0, 0, $targetWidth, $targetHeight, $transparent);

        imagecopyresampled(
            $resized,
            $image,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $originalWidth,
            $originalHeight
        );
        imagedestroy($image);
        $image = $resized;
    } else {
        imagealphablending($image, true);
        imagesavealpha($image, true);
    }

    $tmpPath = $tempDir . '/' . bin2hex(random_bytes(16)) . '.webp';
    if (!imagewebp($image, $tmpPath, WEBP_QUALITY)) {
        imagedestroy($image);
        throw new RuntimeException("Could not encode webp for: {$sourcePath}");
    }
    imagedestroy($image);

    return [
        'tmp_path' => $tmpPath,
        'width' => $targetWidth,
        'height' => $targetHeight,
        'size' => (int) filesize($tmpPath),
    ];
}

/**
 * Upload $sourceFile (basename inside $sourceDir) into the media table,
 * reusing an existing row (matched by sanitized original filename) if one
 * already exists.
 *
 * @return array{id: int, public_path: string, created: bool}
 */
function upsertMedia(
    PDO $pdo,
    MediaUploadService $mediaUploads,
    string $sourceDir,
    string $tempDir,
    string $sourceFile,
    string $altText,
    string $collection,
    int $uploadedBy
): array {
    $sourcePath = $sourceDir . '/' . $sourceFile;
    if (!is_file($sourcePath)) {
        throw new RuntimeException("Source photo not found: {$sourcePath}");
    }

    $originalName = trim(preg_replace('/[^\pL\pN._ -]+/u', '-', $sourceFile) ?? $sourceFile);

    $lookup = $pdo->prepare(
        'SELECT id, file_path FROM media
         WHERE original_name = :original_name AND deleted_at IS NULL
         ORDER BY id LIMIT 1'
    );
    $lookup->execute(['original_name' => $originalName]);
    $existing = $lookup->fetch();

    if ($existing !== false) {
        return [
            'id' => (int) $existing['id'],
            'public_path' => (string) $existing['file_path'],
            'created' => false,
        ];
    }

    $converted = convertToWebp($sourcePath, $tempDir);

    $upload = [
        'tmp_name' => $converted['tmp_path'],
        'original_name' => $originalName,
        'mime_type' => 'image/webp',
        'extension' => 'webp',
        'size' => $converted['size'],
        'width' => $converted['width'],
        'height' => $converted['height'],
        'alt_text' => $altText,
    ];

    $result = $mediaUploads->store($upload, $uploadedBy, $collection);

    return [
        'id' => $result['id'],
        'public_path' => $result['public_path'],
        'created' => true,
    ];
}

$report = ['hero_slides' => [], 'department_heroes' => [], 'general_library' => [], 'skipped' => []];

// ---------------------------------------------------------------------------
// 1. Homepage hero slides (collection 'pages') -- ordered to match
//    hero_slides.display_order 0, 1, 2.
// ---------------------------------------------------------------------------
$heroFiles = [
    [
        'file' => 'IMGL0218.jpg',
        'alt' => 'FAST building signage with a large group of staff and students gathered outside',
    ],
    [
        'file' => 'quiz_azm8fs.jpg',
        'alt' => 'MUES Annual Innovation Week banner with quiz competition champions',
    ],
    [
        'file' => 'IMGL0146.jpg',
        'alt' => 'Students working in a busy, modern teaching laboratory',
    ],
];

$slides = $pdo->query(
    'SELECT id, title, display_order FROM hero_slides ORDER BY display_order, id'
)->fetchAll();

if (count($slides) !== count($heroFiles)) {
    fwrite(STDERR, sprintf(
        "WARNING: expected %d hero_slides rows, found %d. Wiring the first %d by display_order.\n",
        count($heroFiles),
        count($slides),
        min(count($slides), count($heroFiles))
    ));
}

$updateSlide = $pdo->prepare('UPDATE hero_slides SET media_id = :media_id WHERE id = :id');
foreach ($heroFiles as $index => $heroFile) {
    if (!isset($slides[$index])) {
        continue;
    }
    $media = upsertMedia(
        $pdo,
        $mediaUploads,
        $sourceDir,
        $tempDir,
        $heroFile['file'],
        $heroFile['alt'],
        'pages',
        $uploadedBy
    );
    $updateSlide->execute(['media_id' => $media['id'], 'id' => $slides[$index]['id']]);
    $report['hero_slides'][] = [
        'slide_id' => (int) $slides[$index]['id'],
        'title' => $slides[$index]['title'],
        'file' => $heroFile['file'],
        'media_id' => $media['id'],
        'public_path' => $media['public_path'],
        'created' => $media['created'],
    ];
}

// ---------------------------------------------------------------------------
// 2. Department heroes (collection 'departments')
// ---------------------------------------------------------------------------
$departmentHeroes = [
    'biomedical-engineering' => [
        'file' => 'fast6.jpg',
        'alt' => 'MRI UGANDA launch banner -- the first MRI machine built in Sub-Saharan Africa',
    ],
    'mechanical-engineering' => [
        'file' => 'fast28.jpg',
        'alt' => 'A row of milling machines in a workshop, with a worker inspecting one wearing a Uganda flag patch',
    ],
    'electrical-and-electronics-engineering' => [
        'file' => 'IMGL1377.jpg',
        'alt' => 'Students measuring and wiring a circuit board in a workshop',
    ],
    'civil-engineering' => [
        'file' => 'track_vn2oi2.jpg',
        'alt' => 'A road grader and a hard-hat crowd at a construction site visit',
    ],
    'petroleum-engineering-and-environmental-management' => [
        'file' => 'fast17.webp',
        'alt' => 'Group photo at the Petroleum Authority of Uganda gate',
    ],
];

$updateDepartment = $pdo->prepare('UPDATE departments SET hero_media_id = :media_id WHERE slug = :slug');
foreach ($departmentHeroes as $slug => $entry) {
    $media = upsertMedia(
        $pdo,
        $mediaUploads,
        $sourceDir,
        $tempDir,
        $entry['file'],
        $entry['alt'],
        'departments',
        $uploadedBy
    );
    $affected = $updateDepartment->execute(['media_id' => $media['id'], 'slug' => $slug]);
    $report['department_heroes'][] = [
        'slug' => $slug,
        'file' => $entry['file'],
        'media_id' => $media['id'],
        'public_path' => $media['public_path'],
        'created' => $media['created'],
    ];
}

// ---------------------------------------------------------------------------
// 3. General media library (collection 'media') -- everything else in the
//    48-file set except fast23.jpg (duplicate, skipped) and the 8 files
//    already placed above (3 hero slides + 5 department heroes).
// ---------------------------------------------------------------------------
$genericStaffAlt = 'Staff and students at a campus event';

$generalLibrary = [
    'IMG_5604.JPG' => 'An award presentation at the MUES Innovation Week',
    'IMGL0124.jpg' => 'Students presenting a magnetic separator engineering design project',
    'IMGL0089.jpg' => 'Students demonstrating a small wind-turbine engineering model',
    'IMGL0220.jpg' => 'FAST building entrance with staff and students',
    'IMGL1390.jpg' => 'Students in welding safety equipment in a workshop',
    'wasswa.png' => 'Dr. Elias Kumbakumba, Prof. Joseph Ngonzi and Dr. Eng. William Wasswa at a panel discussion',
    'wasswa2.jpg' => $genericStaffAlt,
    'wasswa4.jpg' => $genericStaffAlt,
    'fast1.jpg' => 'Students presenting the TotoWarm newborn-hypothermia wearable device at an innovation showcase',
    'fast2.jpg' => 'Student orientation activities',
    'fast3.jpg' => 'A plastic waste recycling project event',
    'fast5.jpg' => 'Reviewing architectural scale models',
    'fast7.webp' => 'A group photo at an outdoor site',
    'fast9.webp' => 'A site visit to an energy/petroleum sector facility',
    'fast10.webp' => 'A site visit portrait, energy sector',
    'fast11.webp' => 'A site visit portrait, energy sector',
    'fasr12.webp' => 'A site visit to an energy/petroleum sector facility',
    'fasr13.webp' => 'A site visit to an energy/petroleum sector facility',
    'fast15.webp' => 'A site visit to an energy/petroleum sector facility',
    'fast16.webp' => 'A site visit to an energy/petroleum sector facility',
    'fast14.jpg' => 'Students and staff celebrating with a trophy',
    'fast16.jpg' => 'Students and staff celebrating an achievement',
    'fast17.jpg' => 'Students and staff celebrating with a flag and trophy',
    'fast 18.jpg' => 'A graduation ceremony',
    'fast19.jpg' => 'A student group outside the FAST building',
    'fast20.jpg' => 'A student group at an outdoor site visit',
    'fast21.jpg' => 'A low-field MRI research apparatus in a laboratory',
    'fast22.jpg' => 'A low-field MRI research apparatus in a laboratory',
    'fast24.jpg' => 'Visitors touring a biomedical engineering laboratory',
    'fast25.jpg' => 'A speaker presenting at an assistive technology event',
    'fast26.jpg' => 'A construction site tour',
    'fast30.jpg' => '3D printers in a fabrication laboratory',
    'fast11.jpg' => "Illustrative stock image of a quadruped robot (not FAST's own equipment)",
    'IMG-20250807-WA0019.jpg' => 'Students in a lecture hall',
    'IMG-20250807-WA0020(1).jpg' => 'Students in a lecture hall',
    'lukayaa_xfu5bq.jpg' => $genericStaffAlt,
    'international_tflxzw.jpg' => 'A delegation visit with cultural representatives',
    'peem_xwiwdk.jpg' => $genericStaffAlt,
    '1Z7A1702.jpg' => 'Staff and faculty at a meeting',
];

foreach ($generalLibrary as $file => $alt) {
    $media = upsertMedia($pdo, $mediaUploads, $sourceDir, $tempDir, $file, $alt, 'media', $uploadedBy);
    $report['general_library'][] = [
        'file' => $file,
        'media_id' => $media['id'],
        'public_path' => $media['public_path'],
        'created' => $media['created'],
    ];
}

$report['skipped'][] = [
    'file' => 'fast23.jpg',
    'reason' => 'Byte-identical duplicate of fast22.jpg (confirmed by user)',
];

// ---------------------------------------------------------------------------
// 4. Housekeeping: if the old synthetic placeholder media row is no longer
//    referenced by ANY *_media_id column anywhere, delete the orphaned file
//    on disk (the media row itself is left alone, per instructions).
// ---------------------------------------------------------------------------
$placeholder = $pdo->query(
    "SELECT id, file_path FROM media WHERE file_path = 'public/uploads/site/placeholder-hero.png' LIMIT 1"
)->fetch();

if ($placeholder !== false) {
    $placeholderId = (int) $placeholder['id'];
    $referenceColumns = $pdo->query(
        "SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME LIKE '%media_id'"
    )->fetchAll();

    $stillReferenced = false;
    foreach ($referenceColumns as $column) {
        $table = $column['TABLE_NAME'];
        $col = $column['COLUMN_NAME'];
        $count = (int) $pdo->query(
            "SELECT COUNT(*) FROM `{$table}` WHERE `{$col}` = {$placeholderId}"
        )->fetchColumn();
        if ($count > 0) {
            $stillReferenced = true;
            break;
        }
    }

    if (!$stillReferenced) {
        $absolutePlaceholderPath = dirname(__DIR__, 2) . '/' . $placeholder['file_path'];
        if (is_file($absolutePlaceholderPath)) {
            @unlink($absolutePlaceholderPath);
            $report['placeholder_file_deleted'] = true;
        } else {
            $report['placeholder_file_deleted'] = 'already absent';
        }
    } else {
        $report['placeholder_file_deleted'] = false;
    }
}

fwrite(STDOUT, "Site photography seeded.\n");
fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
