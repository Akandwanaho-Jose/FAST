<?php

declare(strict_types=1);

/**
 * Seeds a single News draft sourced from the FAST LinkedIn page's post on
 * First Year students using the recess period for hands-on training,
 * innovation, and industrial exposure.
 *
 * The source post carried no photo (a text-only post, confirmed by
 * inspecting its media before writing this seed), so this article has no
 * featured image or gallery - text only, same as the source.
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

require dirname(__DIR__, 2) . '/bootstrap/autoload.php';

/** @var \FastWebsite\Core\Database $database */
$database = require dirname(__DIR__, 2) . '/bootstrap/database.php';
$pdo = $database->connection();

const SLUG = 'fast-students-bridge-theory-with-practice-during-recess-2026';

$existing = $pdo->prepare('SELECT id FROM news WHERE slug = :slug LIMIT 1');
$existing->execute(['slug' => SLUG]);
if ($existing->fetchColumn() !== false) {
    fwrite(STDOUT, "News item already seeded (slug: " . SLUG . "). Nothing to do.\n");
    exit(0);
}

$uploadedBy = (int) $pdo->query(
    "SELECT id FROM users WHERE email = 'jakandwanaho@must.ac.ug' LIMIT 1"
)->fetchColumn();
if ($uploadedBy < 1) {
    throw new RuntimeException('Seed admin user not found (jakandwanaho@must.ac.ug).');
}

$categoryId = (int) $pdo->query(
    "SELECT id FROM news_categories WHERE slug = 'research-innovation' LIMIT 1"
)->fetchColumn();
if ($categoryId < 1) {
    throw new RuntimeException('Expected "Research & Innovation" news category to already exist.');
}

$title = 'FAST Students Bridge Theory with Practice During Recess';
$summary = 'First Year students at FAST are using the recess period to strengthen practical skills through hands-on training, engineering workshops, laboratory practice, software development, research projects and industrial exposure.';
$body = <<<'HTML'
<p>Learning doesn't stop when the semester ends. At the Faculty of Applied Sciences and Technology (FAST), Mbarara University of Science and Technology (MUST), the recess period gives First Year students an opportunity to strengthen their practical skills through hands-on training, innovation, field activities, industrial exposure, and collaborative projects.</p>
<p>These activities are designed to complement classroom learning by equipping students with the technical competencies, problem-solving abilities, and professional experience needed to excel in today's rapidly evolving world.</p>
<p>From engineering workshops and laboratory practice to software development, research projects, and community engagement, students continue to apply knowledge to real-world challenges while building the confidence and skills demanded by industry.</p>
<p>At FAST, the Faculty remains committed to producing graduates who are not only academically competent but also innovative, industry-ready, and equipped to create solutions that positively impact society.</p>
HTML;

$pdo->prepare(
    'INSERT INTO news (category_id, title, slug, summary, body, featured_media_id, author_user_id, article_date, is_featured, allow_sharing, status)
     VALUES (:category_id, :title, :slug, :summary, :body, NULL, :author_user_id, :article_date, 0, 1, "draft")'
)->execute([
    'category_id' => $categoryId,
    'title' => $title,
    'slug' => SLUG,
    'summary' => $summary,
    'body' => $body,
    'author_user_id' => $uploadedBy,
    'article_date' => date('Y-m-d', strtotime('-1 month')),
]);
$newsId = (int) $pdo->lastInsertId();

fwrite(STDOUT, "Seeded news item id={$newsId} (draft), no photo.\n");
