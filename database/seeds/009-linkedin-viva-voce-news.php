<?php

declare(strict_types=1);

use FastWebsite\Core\Database;
use FastWebsite\Repositories\MediaRepository;
use FastWebsite\Services\MediaUploadService;

require dirname(__DIR__, 2) . '/bootstrap/autoload.php';

/**
 * Seeds a single News draft sourced from the FAST LinkedIn page's post
 * announcing the MSc. Biomedical Sciences & Engineering Viva Voce Defences
 * (2025/2026 academic year), including its 5 event photos as a gallery.
 *
 * The article body is written as an original news item based on the same
 * underlying facts as the LinkedIn post (not a verbatim copy of its caption
 * text/formatting), since this is FAST's own institutional content being
 * republished on FAST's own site.
 *
 * Created as status=draft, per explicit instruction: this must go through
 * the normal /admin/news review-and-publish workflow, not go live directly.
 *
 * Idempotent: skipped entirely if a news row with this slug already exists.
 */

/** @var Database $database */
$database = require dirname(__DIR__, 2) . '/bootstrap/database.php';
$pdo = $database->connection();

const SLUG = 'fast-msc-biomedical-sciences-engineering-viva-voce-defences-2026';

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

$tempDir = sys_get_temp_dir() . '/fast-linkedin-viva-voce';
if (!is_dir($tempDir) && !mkdir($tempDir, 0755, true) && !is_dir($tempDir)) {
    throw new RuntimeException("Could not create temp conversion directory: {$tempDir}");
}

$sourceDir = 'C:/Users/joetech/AppData/Local/Temp/claude/C--xampp-htdocs-FAST/c460ef50-ff13-4e05-b8ba-4b7879e2ef0a/scratchpad/linkedin-import';

$photos = [
    [
        'file' => 'photo-1.jpg',
        'alt' => 'A postgraduate student gestures while presenting research findings to an examiner during a viva voce defence',
    ],
    [
        'file' => 'photo-2.jpg',
        'alt' => 'A postgraduate student shows her laptop presentation to two examiners during a viva voce defence',
    ],
    [
        'file' => 'photo-3.jpg',
        'alt' => 'A panel of examiners and staff with laptops listens during a biomedical sciences and engineering viva voce defence',
    ],
    [
        'file' => 'photo-4.jpg',
        'alt' => 'A postgraduate student presents to a seated panel of examiners in a lecture room',
    ],
    [
        'file' => 'photo-5.jpg',
        'alt' => 'Examiners and panel members in discussion in front of a whiteboard with digital twin research notes',
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
        'original_name' => 'linkedin-viva-voce-' . $photo['file'],
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

$departmentId = (int) $pdo->query(
    "SELECT id FROM departments WHERE slug = 'biomedical-engineering' LIMIT 1"
)->fetchColumn();

$title = 'FAST Holds MSc. Biomedical Sciences & Engineering Viva Voce Defences';
$summary = 'Eight postgraduate students defended research spanning AI-driven disease detection, digital health systems, medical diagnostics, biomedical equipment management, clinical NLP, and biomedical sensing and digital twin technologies.';
$body = <<<'HTML'
<p>The Faculty of Applied Sciences and Technology (FAST) at Mbarara University of Science and Technology (MUST) has successfully held the MSc. Biomedical Sciences &amp; Engineering Viva Voce Defences for the 2025/2026 academic year.</p>
<p>The defence sessions gave eight postgraduate students a platform to present and defend their research, showcasing the depth of innovation, interdisciplinary collaboration, and problem-solving at the heart of the Department of Biomedical Sciences and Engineering.</p>
<h2>From research to real-world impact</h2>
<p>The projects defended addressed a diverse range of challenges in healthcare and technology, including:</p>
<ul>
<li>Artificial intelligence and machine learning for healthcare and disease detection</li>
<li>Digital health and intelligent healthcare systems</li>
<li>Medical diagnostics and mobile microscopy</li>
<li>Biomedical equipment management and sustainability</li>
<li>Clinical data and natural language processing (NLP)</li>
<li>Biomedical sensing and digital twin technologies</li>
</ul>
<p>These research projects reflect FAST's commitment to developing technology-driven solutions to real-world challenges, while equipping postgraduate researchers with the skills to contribute meaningfully to Uganda's healthcare and technological transformation.</p>
<p>FAST congratulates all eight students on reaching this significant academic milestone, and thanks the examiners, supervisors, panel members, and staff whose guidance and expertise made the defence sessions a success.</p>
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
    'featured_media_id' => $mediaIds[3],
    'author_user_id' => $uploadedBy,
    'article_date' => date('Y-m-d', strtotime('-2 weeks')),
]);
$newsId = (int) $pdo->lastInsertId();

if ($departmentId > 0) {
    $pdo->prepare(
        'INSERT INTO news_departments (news_id, department_id) VALUES (:news_id, :department_id)'
    )->execute(['news_id' => $newsId, 'department_id' => $departmentId]);
}

$insertGallery = $pdo->prepare(
    'INSERT INTO news_media (news_id, media_id, display_order) VALUES (:news_id, :media_id, :display_order)'
);
foreach ($mediaIds as $order => $mediaId) {
    $insertGallery->execute(['news_id' => $newsId, 'media_id' => $mediaId, 'display_order' => $order]);
}

fwrite(STDOUT, "Seeded news item id={$newsId} (draft) with " . count($mediaIds) . " gallery photos.\n");
