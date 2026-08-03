<?php

declare(strict_types=1);

use FastWebsite\Core\Database;

require dirname(__DIR__, 2) . '/bootstrap/autoload.php';

/** @var Database $database */
$database = require dirname(__DIR__, 2) . '/bootstrap/database.php';
$connection = $database->connection();
$imagePath = dirname(__DIR__, 2) . '/public/assets/images/fast-building.png';

if (!is_file($imagePath)) {
    throw new RuntimeException('The FAST building image is missing.');
}

$connection->beginTransaction();
try {
    $media = $connection->prepare(
        'SELECT id FROM media WHERE file_path = :path AND deleted_at IS NULL LIMIT 1'
    );
    $media->execute(['path' => 'public/assets/images/fast-building.png']);
    $mediaId = (int) $media->fetchColumn();

    if ($mediaId < 1) {
        $insertMedia = $connection->prepare(
            'INSERT INTO media
                (media_type, original_name, stored_name, file_path, mime_type,
                 file_extension, file_size, width, height, alt_text, uploaded_by, status)
             VALUES
                ("image", "FAST building at Kihumuro Campus", "fast-building.png",
                 "public/assets/images/fast-building.png", "image/png", "png",
                 :file_size, 1600, 900, "FAST building at the MUST Kihumuro Campus", NULL, "active")'
        );
        $insertMedia->execute(['file_size' => filesize($imagePath)]);
        $mediaId = (int) $connection->lastInsertId();
    }

    $slideCount = (int) $connection->query('SELECT COUNT(*) FROM hero_slides')->fetchColumn();
    if ($slideCount === 0) {
        $insertSlide = $connection->prepare(
            'INSERT INTO hero_slides
                (title, caption, media_id, button_label, button_url,
                 text_alignment, overlay_strength, display_order, is_active)
             VALUES
                (:title, :caption, :media_id, "Explore programmes", "/programmes",
                 "left", 60, 0, 1)'
        );
        $insertSlide->execute([
            'title' => 'Faculty of Applied Sciences and Technology',
            'caption' => 'Discover teaching, research, innovation, and partnerships at FAST.',
            'media_id' => $mediaId,
        ]);
    }

    $connection->commit();
    fwrite(STDOUT, "Homepage hero seed is ready.\n");
} catch (Throwable $exception) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
    throw $exception;
}
