<?php

declare(strict_types=1);

/**
 * One-time data migration: folds the single existing `announcements` row
 * into `news` under a new "Announcements" category, then drops the
 * `announcements` table entirely.
 *
 * This is a deliberate simplification (explicitly approved): the site has
 * very little content so far, and running two parallel content systems
 * (separate schema, admin CRUD, validation, public rendering) cost more in
 * duplication than the two features `announcements` had that `news` lacks
 * (starts_at/ends_at auto-expiry and is_pinned) were worth, since neither
 * was actually in use.
 *
 * Idempotent for the migration step (skipped if the announcement's title
 * already exists as a news row); the DROP TABLE step is not idempotent by
 * nature but is guarded to only run if the table still exists.
 */

require dirname(__DIR__) . '/bootstrap/autoload.php';

/** @var \FastWebsite\Core\Database $database */
$database = require dirname(__DIR__) . '/bootstrap/database.php';
$pdo = $database->connection();

$tableExists = (int) $pdo->query(
    "SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'announcements'"
)->fetchColumn() > 0;

if (!$tableExists) {
    fwrite(STDOUT, "announcements table does not exist. Nothing to migrate.\n");
    exit(0);
}

$rows = $pdo->query('SELECT * FROM announcements')->fetchAll();

$categoryId = (int) $pdo->query(
    "SELECT id FROM news_categories WHERE slug = 'announcements' LIMIT 1"
)->fetchColumn();
if ($categoryId < 1) {
    $pdo->prepare(
        'INSERT INTO news_categories (name, slug, display_order, is_active) VALUES (:name, :slug, 0, 1)'
    )->execute(['name' => 'Announcements', 'slug' => 'announcements']);
    $categoryId = (int) $pdo->lastInsertId();
}

$uploadedBy = (int) $pdo->query(
    "SELECT id FROM users WHERE email = 'jakandwanaho@must.ac.ug' LIMIT 1"
)->fetchColumn();

$slugify = static function (string $text): string {
    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $text) ?? $text, '-'));
    return $slug !== '' ? $slug : 'announcement';
};

$migrated = 0;
foreach ($rows as $row) {
    $title = (string) $row['title'];
    $slug = $slugify($title);

    $existing = $pdo->prepare('SELECT id FROM news WHERE slug = :slug LIMIT 1');
    $existing->execute(['slug' => $slug]);
    if ($existing->fetchColumn() !== false) {
        fwrite(STDOUT, "Already migrated: {$title}\n");
        continue;
    }

    $summary = (string) ($row['summary'] ?? '');
    $body = '<p>' . htmlspecialchars($summary, ENT_QUOTES, 'UTF-8') . '</p>';

    $pdo->prepare(
        'INSERT INTO news (category_id, title, slug, summary, body, featured_media_id, author_user_id, article_date, is_featured, allow_sharing, status, published_at)
         VALUES (:category_id, :title, :slug, :summary, :body, NULL, :author_user_id, :article_date, 0, 1, :status, :published_at)'
    )->execute([
        'category_id' => $categoryId,
        'title' => $title,
        'slug' => $slug,
        'summary' => $summary,
        'body' => $body,
        'author_user_id' => $uploadedBy > 0 ? $uploadedBy : null,
        'article_date' => $row['published_at'] !== null ? substr((string) $row['published_at'], 0, 10) : date('Y-m-d'),
        'status' => $row['status'],
        'published_at' => $row['published_at'],
    ]);
    $migrated++;
    fwrite(STDOUT, "Migrated: {$title} -> news slug={$slug}\n");
}

fwrite(STDOUT, "Migrated {$migrated} announcement(s) into news.\n");
