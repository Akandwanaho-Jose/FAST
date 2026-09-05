<?php

declare(strict_types=1);

/**
 * One-time data migration: for the handful of existing news articles that
 * had extra photos in `news_media`, bakes those photos directly into the
 * stored `news.body` HTML as inline <figure> elements (the same way the
 * page used to auto-interleave them at render time), then leaves the
 * `news_media` rows in place as the admin's photo library for that article.
 *
 * This is required because the site is moving from "photos automatically
 * distributed between paragraphs at render time" to "editor places photos
 * exactly where they want them, using the rich text editor's Insert photo
 * tool" - so from now on, placement lives in the stored body content
 * itself, not in a repeated per-request layout calculation. Without this
 * migration, the already-published articles would lose their extra photos
 * the moment the render-time interleaving code is removed.
 *
 * Idempotent: skipped per-article if that article's body already contains
 * an <figure class="editorial-inline-figure"> block.
 */

require dirname(__DIR__) . '/bootstrap/autoload.php';

use FastWebsite\Core\View;

/** @var \FastWebsite\Core\Database $database */
$database = require dirname(__DIR__) . '/bootstrap/database.php';
$pdo = $database->connection();

$imageUrl = static function (string $path): string {
    return '/FAST/public/' . ltrim(str_replace('public/', '', $path), '/');
};

$inlineFigure = static function (array $photo) use ($imageUrl): string {
    $caption = trim((string) ($photo['alt_text'] ?? ''));
    $figure = '<figure class="editorial-inline-figure"><img src="' . View::escape($imageUrl((string) $photo['file_path'])) . '" alt="' . View::escape($caption) . '" loading="lazy">';
    if ($caption !== '') {
        $figure .= '<figcaption>' . View::escape($caption) . '</figcaption>';
    }
    return $figure . '</figure>';
};

$articles = $pdo->query('SELECT id, body, featured_media_id FROM news')->fetchAll();

$migrated = 0;
foreach ($articles as $article) {
    $newsId = (int) $article['id'];
    $body = (string) $article['body'];

    if (str_contains($body, 'editorial-inline-figure')) {
        fwrite(STDOUT, "Skipping news id={$newsId}: body already has inline photos.\n");
        continue;
    }

    $galleryStatement = $pdo->prepare(
        'SELECT m.id, m.file_path, m.alt_text
         FROM news_media nm INNER JOIN media m ON m.id = nm.media_id AND m.status = "active" AND m.deleted_at IS NULL
         WHERE nm.news_id = :news_id AND m.id != :featured_id
         ORDER BY nm.display_order, nm.id'
    );
    $galleryStatement->execute([
        'news_id' => $newsId,
        'featured_id' => $article['featured_media_id'] ?? 0,
    ]);
    $gallery = $galleryStatement->fetchAll();

    if ($gallery === []) {
        continue;
    }

    $paragraphBlocks = preg_split('/(?<=<\/p>)/', $body, -1, PREG_SPLIT_NO_EMPTY) ?: [$body];
    $blockCount = count($paragraphBlocks);
    $photoCount = count($gallery);
    $gap = max(1, (int) floor($blockCount / ($photoCount + 1)));

    $newBody = '';
    $photoIndex = 0;
    foreach ($paragraphBlocks as $index => $block) {
        $newBody .= $block;
        $isLastBlock = $index === $blockCount - 1;
        if (!$isLastBlock && $photoIndex < $photoCount && ($index + 1) % $gap === 0) {
            $newBody .= $inlineFigure($gallery[$photoIndex]);
            $photoIndex++;
        }
    }
    while ($photoIndex < $photoCount) {
        $newBody .= $inlineFigure($gallery[$photoIndex]);
        $photoIndex++;
    }

    $pdo->prepare('UPDATE news SET body = :body WHERE id = :id')
        ->execute(['body' => $newBody, 'id' => $newsId]);
    $migrated++;
    fwrite(STDOUT, "Baked {$photoCount} photo(s) into news id={$newsId} body.\n");
}

fwrite(STDOUT, "Migrated {$migrated} article(s).\n");
