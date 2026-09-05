<?php

declare(strict_types=1);

use FastWebsite\Core\View;

$imageUrl = static fn (string $path): string => $baseUrl . ltrim(str_replace('public/', '', $path), '/');
$clockIcon = '<svg class="newsroom-meta-icon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></svg>';

$authorFullName = trim((string) ($item['author_first_name'] ?? '') . ' ' . (string) ($item['author_last_name'] ?? ''));
$authorStaffName = trim((string) ($item['author_honorific_title'] ?? '') . ' ' . $authorFullName);
$authorName = $authorStaffName !== '' ? $authorStaffName : (string) ($item['author_name'] ?? '');
$authorInitials = '';
foreach (preg_split('/\s+/', $authorFullName !== '' ? $authorFullName : (string) ($item['author_name'] ?? '')) as $namePart) {
    if ($namePart !== '') {
        $authorInitials .= mb_strtoupper(mb_substr($namePart, 0, 1));
    }
}
$authorInitials = mb_substr($authorInitials, 0, 2);

// Inline photos are placed by the editor directly in the story body (via
// the rich text editor's Insert photo tool), so the body is rendered as
// authored - no automatic photo placement happens here.
$bodyHtml = View::richText($item['body']);
?>
<article class="editorial-article">
    <header class="editorial-masthead" data-feature-reveal>
        <div class="shell editorial-masthead-grid">
            <div class="editorial-masthead-copy">
                <a class="editorial-back-link" href="<?= View::escape($baseUrl) ?>news"><span aria-hidden="true">&larr;</span> News & announcements</a>
                <p class="newsroom-meta"><?= $clockIcon ?><?= View::escape(date('j F Y', strtotime((string) $item['article_date']))) ?></p>
                <h1><?= View::escape($item['title']) ?></h1>
                <?php if (!empty($item['summary'])): ?><p class="editorial-deck"><?= View::escape($item['summary']) ?></p><?php endif; ?>
            </div>
            <?php if (!empty($item['media_path'])): ?>
                <figure class="editorial-masthead-media">
                    <?php if (!empty($item['category_name'])): ?><span class="newsroom-category-ribbon"><?= View::escape($item['category_name']) ?></span><?php endif; ?>
                    <img src="<?= View::escape($imageUrl($item['media_path'])) ?>" alt="<?= View::escape($item['media_alt_text'] ?? '') ?>">
                </figure>
            <?php endif; ?>
        </div>
    </header>

    <section class="editorial-body-section section" data-feature-reveal>
        <div class="shell">
            <div class="prose editorial-prose"><?= $bodyHtml ?></div>
        </div>
    </section>

    <section class="editorial-author-section section" data-feature-reveal>
        <div class="shell">
            <p class="eyebrow">Author</p>
            <div class="editorial-author-card">
                <?php if ($authorName !== ''): ?>
                    <?php if (!empty($item['author_photo_path'])): ?>
                        <img class="editorial-author-avatar" src="<?= View::escape($imageUrl($item['author_photo_path'])) ?>" alt="">
                    <?php else: ?>
                        <span class="editorial-author-avatar editorial-author-avatar--initials" aria-hidden="true"><?= View::escape($authorInitials) ?></span>
                    <?php endif; ?>
                <?php endif; ?>
                <div class="editorial-author-copy">
                    <?php if ($authorName !== ''): ?>
                        <?php if (!empty($item['author_staff_slug'])): ?>
                            <a class="editorial-author-name" href="<?= View::escape($baseUrl) ?>staff/<?= View::escape($item['author_staff_slug']) ?>"><?= View::escape($authorName) ?></a>
                        <?php else: ?>
                            <span class="editorial-author-name"><?= View::escape($authorName) ?></span>
                        <?php endif; ?>
                    <?php endif; ?>
                    <p class="editorial-author-meta"><?= View::escape(date('j F Y', strtotime((string) $item['article_date']))) ?> <span aria-hidden="true">&middot;</span> <?= View::escape($item['category_name']) ?></p>
                    <?php if (!empty($item['author_staff_slug'])): ?>
                        <a class="editorial-author-link" href="<?= View::escape($baseUrl) ?>staff/<?= View::escape($item['author_staff_slug']) ?>">View staff profile <span aria-hidden="true">&rarr;</span></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <?php if ($related !== []): ?>
        <section class="editorial-related section" data-feature-reveal>
            <div class="shell">
                <div class="editorial-section-heading"><div><p class="eyebrow">Continue reading</p><h2>More from FAST</h2></div><a class="editorial-link" href="<?= View::escape($baseUrl) ?>news">View all news <span aria-hidden="true">&rarr;</span></a></div>
                <div class="newsroom-story-grid newsroom-story-grid--three">
                    <?php foreach ($related as $story): ?>
                        <article class="newsroom-card">
                            <a class="newsroom-card-media" href="<?= View::escape($baseUrl) ?>news/<?= View::escape($story['slug']) ?>">
                                <?php if (!empty($story['category_name'])): ?><span class="newsroom-category-ribbon"><?= View::escape($story['category_name']) ?></span><?php endif; ?>
                                <?php if (!empty($story['media_path'])): ?><img src="<?= View::escape($imageUrl($story['media_path'])) ?>" alt="<?= View::escape($story['media_alt_text'] ?? '') ?>"><?php else: ?><span class="newsroom-media-placeholder" aria-hidden="true">FAST</span><?php endif; ?>
                            </a>
                            <div class="newsroom-card-copy"><p class="newsroom-meta"><?= $clockIcon ?><?= View::escape(date('j M Y', strtotime((string) $story['article_date']))) ?></p><h3><a href="<?= View::escape($baseUrl) ?>news/<?= View::escape($story['slug']) ?>"><?= View::escape($story['title']) ?></a></h3></div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>
</article>
