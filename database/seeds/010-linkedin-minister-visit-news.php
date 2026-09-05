<?php

declare(strict_types=1);

use FastWebsite\Core\Database;
use FastWebsite\Repositories\MediaRepository;
use FastWebsite\Services\MediaUploadService;

require dirname(__DIR__, 2) . '/bootstrap/autoload.php';

/**
 * Seeds a single News draft sourced from the FAST LinkedIn page's post
 * announcing the visit of the Minister of Science, Technology and
 * Innovation and the STI Secretariat, including its 4 event photos as a
 * gallery.
 *
 * The article body is written as an original news item based on the same
 * underlying facts as the LinkedIn post (not a verbatim copy of its caption
 * text/formatting), since this is FAST's own institutional content being
 * republished on FAST's own site.
 *
 * Created as status=draft, per the established review-before-publish
 * process: this must go through the normal /admin/news workflow.
 *
 * Idempotent: skipped entirely if a news row with this slug already exists.
 */

/** @var Database $database */
$database = require dirname(__DIR__, 2) . '/bootstrap/database.php';
$pdo = $database->connection();

const SLUG = 'fast-hosts-minister-of-science-technology-and-innovation-2026';

$existing = $pdo->prepare('SELECT id FROM news WHERE slug = :slug LIMIT 1');
$existing->execute(['slug' => SLUG]);
if ($existing->fetchColumn() !== false) {
    fwrite(STDOUT, "News item already seeded (slug: " . SLUG . "). Nothing to do.\n");
    exit(0);
}

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
if ($uploadedBy < 1) {
    throw new RuntimeException('Seed admin user not found (jakandwanaho@must.ac.ug).');
}

const MAX_EDGE = 1600;
const WEBP_QUALITY = 85;

/** @return array{tmp_path: string, width: int, height: int, size: int} */
function convertToWebp(string $sourcePath, string $tempDir): array
{
    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($sourcePath);
    $image = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($sourcePath),
        'image/png' => @imagecreatefrompng($sourcePath),
        'image/webp' => @imagecreatefromwebp($sourcePath),
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
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $originalWidth, $originalHeight);
        imagedestroy($image);
        $image = $resized;
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

$tempDir = sys_get_temp_dir() . '/fast-linkedin-minister-visit';
if (!is_dir($tempDir) && !mkdir($tempDir, 0755, true) && !is_dir($tempDir)) {
    throw new RuntimeException("Could not create temp conversion directory: {$tempDir}");
}

$sourceDir = 'C:/Users/joetech/AppData/Local/Temp/claude/C--xampp-htdocs-FAST/c460ef50-ff13-4e05-b8ba-4b7879e2ef0a/scratchpad/linkedin-import-2';

$photos = [
    [
        'file' => 'photo-1.jpg',
        'alt' => 'Students in safety helmets and coveralls applaud as the Minister and delegation arrive at FAST',
    ],
    [
        'file' => 'photo-2.jpg',
        'alt' => 'The Minister and Vice Chancellor try virtual reality headsets during a demonstration at FAST',
    ],
    [
        'file' => 'photo-3.jpg',
        'alt' => 'A student demonstrates engineering laboratory equipment to the Minister and delegation',
    ],
    [
        'file' => 'photo-4.jpg',
        'alt' => 'A student presents an architectural model to the Minister and delegation',
    ],
];

$mediaIds = [];
foreach ($photos as $photo) {
    $sourcePath = $sourceDir . '/' . $photo['file'];
    if (!is_file($sourcePath)) {
        throw new RuntimeException("Source photo not found: {$sourcePath}");
    }

    $converted = convertToWebp($sourcePath, $tempDir);
    $upload = [
        'tmp_name' => $converted['tmp_path'],
        'original_name' => 'linkedin-minister-visit-' . $photo['file'],
        'mime_type' => 'image/webp',
        'extension' => 'webp',
        'size' => $converted['size'],
        'width' => $converted['width'],
        'height' => $converted['height'],
        'alt_text' => $photo['alt'],
    ];
    $result = $mediaUploads->store($upload, $uploadedBy, 'news');
    $mediaIds[] = $result['id'];
}

$categoryId = (int) $pdo->query(
    "SELECT id FROM news_categories WHERE slug = 'research-innovation' LIMIT 1"
)->fetchColumn();
if ($categoryId < 1) {
    $pdo->prepare(
        'INSERT INTO news_categories (name, slug, display_order, is_active) VALUES (:name, :slug, 0, 1)'
    )->execute(['name' => 'Research & Innovation', 'slug' => 'research-innovation']);
    $categoryId = (int) $pdo->lastInsertId();
}

$title = 'FAST Hosts Minister of Science, Technology and Innovation';
$summary = 'Rt. Hon. Eng. Asiimwe Jonard and the STI Secretariat toured FAST\'s research and innovation ecosystem, viewing student and staff projects spanning healthcare, engineering, digital technologies, agriculture and sustainable development.';
$body = <<<'HTML'
<p>The Faculty of Applied Sciences and Technology (FAST) at Mbarara University of Science and Technology (MUST) was honoured to host Rt. Hon. Eng. Asiimwe Jonard, Minister of Science, Technology and Innovation, together with the Science, Technology and Innovation Secretariat of Uganda, during their visit to the University.</p>
<p>As part of the Minister's wider engagement across MUST, the delegation visited FAST under the leadership of Vice Chancellor Prof. Pauline Byakika-Kibwika, guided by Dean Dr. William Wasswa, through a showcase of the Faculty's research and innovation ecosystem.</p>
<p>The visit featured a range of ongoing research and technology-driven projects, with researchers and student innovators demonstrating practical solutions in healthcare, engineering, digital technologies, agriculture, and sustainable development. These engagements highlighted the role of university research in generating innovative solutions, supporting entrepreneurship, and developing technologies that contribute to Uganda's socio-economic transformation.</p>
<p>The Minister commended the creativity, dedication, and innovation demonstrated by FAST's researchers and students, and called for stronger links between research, innovation, commercialization, and national development.</p>
<p>FAST thanks University Management, led by Prof. Pauline Byakika-Kibwika, the Rt. Hon. Eng. Jonard Asiimwe, the STI Secretariat, Dean Dr. William Wasswa, and all researchers, staff, and students whose efforts made the engagement a success.</p>
HTML;

$pdo->prepare(
    'INSERT INTO news (category_id, title, slug, summary, body, featured_media_id, author_user_id, article_date, is_featured, allow_sharing, status)
     VALUES (:category_id, :title, :slug, :summary, :body, :featured_media_id, :author_user_id, :article_date, 0, 1, "draft")'
)->execute([
    'category_id' => $categoryId,
    'title' => $title,
    'slug' => SLUG,
    'summary' => $summary,
    'body' => $body,
    'featured_media_id' => $mediaIds[1],
    'author_user_id' => $uploadedBy,
    'article_date' => date('Y-m-d', strtotime('-1 month')),
]);
$newsId = (int) $pdo->lastInsertId();

$insertGallery = $pdo->prepare(
    'INSERT INTO news_media (news_id, media_id, display_order) VALUES (:news_id, :media_id, :display_order)'
);
foreach ($mediaIds as $order => $mediaId) {
    $insertGallery->execute(['news_id' => $newsId, 'media_id' => $mediaId, 'display_order' => $order]);
}

fwrite(STDOUT, "Seeded news item id={$newsId} (draft) with " . count($mediaIds) . " gallery photos.\n");
