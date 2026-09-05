<?php

declare(strict_types=1);

/**
 * One-off: attaches the one recovered photo from the recess hands-on-learning
 * LinkedIn post to that news article (seed 012) as its featured image, then
 * publishes the article.
 */

require dirname(__DIR__, 2) . '/bootstrap/autoload.php';

use FastWebsite\Repositories\MediaRepository;
use FastWebsite\Services\MediaUploadService;

/** @var \FastWebsite\Core\Database $database */
$database = require dirname(__DIR__, 2) . '/bootstrap/database.php';
$pdo = $database->connection();

const SLUG = 'fast-students-bridge-theory-with-practice-during-recess-2026';

$newsRow = $pdo->prepare('SELECT id, featured_media_id FROM news WHERE slug = :slug LIMIT 1');
$newsRow->execute(['slug' => SLUG]);
$news = $newsRow->fetch();
if (!is_array($news)) {
    throw new RuntimeException('News row not found for slug: ' . SLUG);
}
$newsId = (int) $news['id'];

if ($news['featured_media_id'] === null) {
    $appConfiguration = require dirname(__DIR__, 2) . '/config/app.php';
    $uploadConfiguration = $appConfiguration['uploads'];

    $mediaRepository = new MediaRepository($database);
    $mediaUploads = new MediaUploadService(
        $mediaRepository,
        (string) $uploadConfiguration['public_path'],
        (int) $uploadConfiguration['maximum_image_bytes']
    );

    $uploadedBy = (int) $pdo->query(
        "SELECT id FROM users WHERE email = 'jakandwanaho@must.ac.ug' LIMIT 1"
    )->fetchColumn();

    $sourcePath = 'C:/Users/joetech/AppData/Local/Temp/claude/C--xampp-htdocs-FAST/c460ef50-ff13-4e05-b8ba-4b7879e2ef0a/scratchpad/user-photos/pasted-1.jpg';
    if (!is_file($sourcePath)) {
        throw new RuntimeException("Photo not found: {$sourcePath}");
    }

    $tempDir = sys_get_temp_dir() . '/fast-recess-photo';
    if (!is_dir($tempDir) && !mkdir($tempDir, 0755, true) && !is_dir($tempDir)) {
        throw new RuntimeException("Could not create temp conversion directory: {$tempDir}");
    }

    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($sourcePath);
    $image = $mime === 'image/jpeg' ? @imagecreatefromjpeg($sourcePath) : null;
    if (!$image instanceof GdImage) {
        throw new RuntimeException("Unreadable image ({$mime}): {$sourcePath}");
    }
    $originalWidth = imagesx($image);
    $originalHeight = imagesy($image);
    $longestEdge = max($originalWidth, $originalHeight);
    $targetWidth = $originalWidth;
    $targetHeight = $originalHeight;
    if ($longestEdge > 1600) {
        $scale = 1600 / $longestEdge;
        $targetWidth = max(1, (int) round($originalWidth * $scale));
        $targetHeight = max(1, (int) round($originalHeight * $scale));
        $resized = imagecreatetruecolor($targetWidth, $targetHeight);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $originalWidth, $originalHeight);
        imagedestroy($image);
        $image = $resized;
    }
    $tmpPath = $tempDir . '/' . bin2hex(random_bytes(16)) . '.webp';
    if (!imagewebp($image, $tmpPath, 85)) {
        imagedestroy($image);
        throw new RuntimeException('Could not encode webp.');
    }
    imagedestroy($image);

    $upload = [
        'tmp_name' => $tmpPath,
        'original_name' => 'linkedin-recess-hands-on-learning.jpg',
        'mime_type' => 'image/webp',
        'extension' => 'webp',
        'size' => (int) filesize($tmpPath),
        'width' => $targetWidth,
        'height' => $targetHeight,
        'alt_text' => 'Two students assemble an electronic device at a workbench while a third student looks on during recess practical training',
    ];
    $result = $mediaUploads->store($upload, $uploadedBy, 'news');

    $pdo->prepare('UPDATE news SET featured_media_id = :media_id WHERE id = :id')
        ->execute(['media_id' => $result['id'], 'id' => $newsId]);

    fwrite(STDOUT, "Attached featured image (media id {$result['id']}) to news id={$newsId}.\n");
} else {
    fwrite(STDOUT, "News id={$newsId} already has a featured image.\n");
}

$pdo->prepare("UPDATE news SET status='published', published_at=NOW() WHERE id = :id")
    ->execute(['id' => $newsId]);

fwrite(STDOUT, "Published news id={$newsId}.\n");
