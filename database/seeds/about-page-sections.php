<?php

declare(strict_types=1);

use FastWebsite\Core\Database;

require dirname(__DIR__, 2) . '/bootstrap/autoload.php';

/** @var Database $database */
$database = require dirname(__DIR__, 2) . '/bootstrap/database.php';
$connection = $database->connection();

$pageStatement = $connection->prepare(
    'SELECT id FROM pages WHERE slug = :slug AND deleted_at IS NULL LIMIT 1'
);

$pageId = static function (string $slug) use ($pageStatement): ?int {
    $pageStatement->execute(['slug' => $slug]);
    $id = $pageStatement->fetchColumn();
    return $id === false ? null : (int) $id;
};

$historyPageId = $pageId('history-of-the-faculty');
$directionPageId = $pageId('vision-and-mission');

$connection->beginTransaction();

try {
    if ($historyPageId !== null) {
        $galleryCount = $connection->prepare(
            'SELECT COUNT(*) FROM page_sections WHERE page_id = :page AND section_type = "gallery"'
        );
        $galleryCount->execute(['page' => $historyPageId]);

        if ((int) $galleryCount->fetchColumn() === 0) {
            $mediaIds = $connection->query(
                'SELECT DISTINCT h.media_id
                 FROM hero_slides h
                 INNER JOIN media m ON m.id = h.media_id
                    AND m.status = "active" AND m.deleted_at IS NULL
                 WHERE h.is_active = 1
                 ORDER BY h.display_order, h.id LIMIT 4'
            )->fetchAll(PDO::FETCH_COLUMN);

            $insertGallery = $connection->prepare(
                'INSERT INTO page_sections
                    (page_id, section_type, media_id, display_order, is_visible)
                 VALUES (:page, "gallery", :media, :display_order, 1)'
            );
            foreach ($mediaIds as $index => $mediaId) {
                $insertGallery->execute([
                    'page' => $historyPageId,
                    'media' => (int) $mediaId,
                    'display_order' => ($index + 1) * 10,
                ]);
            }
        }
    }

    if ($directionPageId !== null) {
        $existingHeadings = $connection->prepare(
            'SELECT LOWER(COALESCE(heading, "")) FROM page_sections WHERE page_id = :page'
        );
        $existingHeadings->execute(['page' => $directionPageId]);
        $headings = $existingHeadings->fetchAll(PDO::FETCH_COLUMN);

        $insertDirection = $connection->prepare(
            'INSERT INTO page_sections
                (page_id, section_type, heading, subheading, body,
                 display_order, is_visible)
             VALUES
                (:page, "cards", :heading, :subheading, NULL,
                 :display_order, 1)'
        );

        foreach ([
            ['vision', 'Vision', 'Where we are going', 10],
            ['mission', 'Mission', 'What we are here to do', 20],
        ] as [$needle, $heading, $subheading, $displayOrder]) {
            $exists = array_filter(
                $headings,
                static fn (string $value): bool => str_contains($value, $needle)
            ) !== [];
            if (!$exists) {
                $insertDirection->execute([
                    'page' => $directionPageId,
                    'heading' => $heading,
                    'subheading' => $subheading,
                    'display_order' => $displayOrder,
                ]);
            }
        }
    }

    $connection->commit();
    fwrite(STDOUT, "About FAST page sections are ready.\n");
} catch (Throwable $exception) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }
    throw $exception;
}
