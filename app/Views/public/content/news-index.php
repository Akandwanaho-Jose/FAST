<?php

declare(strict_types=1);

use FastWebsite\Core\View;

$featured = $items[0] ?? null;
$latest = array_slice($items, 1);
$imageUrl = static fn (string $path): string => $baseUrl . ltrim(str_replace('public/', '', $path), '/');
?>
<div class="newsroom-page">
    <header class="newsroom-intro" data-feature-reveal>
        <div class="shell newsroom-intro-grid">
            <div>
                <p class="eyebrow"><?= View::escape(View::setting($siteContent ?? [], 'news.eyebrow', 'The FAST newsroom')) ?></p>
                <h1><?= View::escape(View::setting($siteContent ?? [], 'news.title', 'News & Announcements')) ?></h1>
            </div>
            <p class="newsroom-intro-copy"><?= View::escape(View::setting($siteContent ?? [], 'news.introduction', 'Research, teaching, student achievement and partnerships from across the Faculty.')) ?></p>
        </div>
    </header>

    <?php if ($featured !== null): ?>
        <section class="newsroom-feature shell" aria-labelledby="featured-story-title" data-feature-reveal>
            <a class="newsroom-feature-media" href="<?= View::escape($baseUrl) ?>news/<?= View::escape($featured['slug']) ?>" aria-label="Read <?= View::escape($featured['title']) ?>">
                <?php if (!empty($featured['media_path'])): ?>
                    <img src="<?= View::escape($imageUrl($featured['media_path'])) ?>" alt="<?= View::escape($featured['media_alt_text'] ?? '') ?>">
                <?php else: ?>
                    <span class="newsroom-media-placeholder" aria-hidden="true">FAST</span>
                <?php endif; ?>
            </a>
            <div class="newsroom-feature-copy">
                <p class="newsroom-kicker">Featured story</p>
                <p class="newsroom-meta"><?= View::escape($featured['category_name']) ?> <span aria-hidden="true">/</span> <?= View::escape(date('j F Y', strtotime((string) $featured['article_date']))) ?></p>
                <h2 id="featured-story-title"><a href="<?= View::escape($baseUrl) ?>news/<?= View::escape($featured['slug']) ?>"><?= View::escape($featured['title']) ?></a></h2>
                <?php if (!empty($featured['summary'])): ?><p><?= View::escape($featured['summary']) ?></p><?php endif; ?>
                <a class="editorial-link" href="<?= View::escape($baseUrl) ?>news/<?= View::escape($featured['slug']) ?>">Read the full story <span aria-hidden="true">&rarr;</span></a>
            </div>
        </section>
    <?php endif; ?>

    <section class="newsroom-content section">
        <div class="shell newsroom-content-grid">
            <div class="newsroom-latest" data-feature-reveal>
                <div class="editorial-section-heading">
                    <div><p class="eyebrow">From across the faculty</p><h2>Latest news</h2></div>
                    <span><?= count($items) ?> published <?= count($items) === 1 ? 'story' : 'stories' ?></span>
                </div>
                <?php if ($items === []): ?>
                    <div class="public-empty newsroom-empty"><h2>Our next story is being prepared.</h2><p><?= View::escape(View::setting($siteContent ?? [], 'news.empty', 'Published news will appear here.')) ?></p></div>
                <?php elseif ($latest === []): ?>
                    <div class="newsroom-single-note"><p>More faculty stories will appear here as they are published.</p></div>
                <?php else: ?>
                    <div class="newsroom-story-grid">
                        <?php foreach ($latest as $story): ?>
                            <article class="newsroom-card">
                                <a class="newsroom-card-media" href="<?= View::escape($baseUrl) ?>news/<?= View::escape($story['slug']) ?>">
                                    <?php if (!empty($story['media_path'])): ?><img src="<?= View::escape($imageUrl($story['media_path'])) ?>" alt="<?= View::escape($story['media_alt_text'] ?? '') ?>"><?php else: ?><span class="newsroom-media-placeholder" aria-hidden="true">FAST</span><?php endif; ?>
                                </a>
                                <div class="newsroom-card-copy">
                                    <p class="newsroom-meta"><?= View::escape($story['category_name']) ?> <span aria-hidden="true">/</span> <?= View::escape(date('j M Y', strtotime((string) $story['article_date']))) ?></p>
                                    <h3><a href="<?= View::escape($baseUrl) ?>news/<?= View::escape($story['slug']) ?>"><?= View::escape($story['title']) ?></a></h3>
                                    <?php if (!empty($story['summary'])): ?><p><?= View::escape($story['summary']) ?></p><?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <aside class="announcement-rail" aria-labelledby="announcements-title" data-feature-reveal>
                <div class="announcement-rail-heading"><p class="eyebrow">Official notices</p><h2 id="announcements-title">Announcements</h2></div>
                <?php if ($announcements === []): ?>
                    <div class="announcement-empty"><p>No current announcements.</p><span>New notices will appear here when published.</span></div>
                <?php else: ?>
                    <div class="announcement-list">
                        <?php foreach ($announcements as $notice): ?>
                            <article class="announcement-item">
                                <p class="announcement-type"><?= View::escape(str_replace('_', ' ', (string) $notice['announcement_type'])) ?></p>
                                <h3><?= View::escape($notice['title']) ?></h3>
                                <?php if (!empty($notice['summary'])): ?><p><?= View::escape($notice['summary']) ?></p><?php endif; ?>
                                <?php if (!empty($notice['link_url'])): ?><a href="<?= View::escape($notice['link_url']) ?>">View notice <span aria-hidden="true">&rarr;</span></a><?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <a class="announcement-events-link" href="<?= View::escape($baseUrl) ?>events">View the faculty events calendar <span aria-hidden="true">&rarr;</span></a>
            </aside>
        </div>
    </section>
</div>
