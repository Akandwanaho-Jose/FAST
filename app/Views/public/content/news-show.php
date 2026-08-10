<?php

declare(strict_types=1);

use FastWebsite\Core\View;

$imageUrl = static fn (string $path): string => $baseUrl . ltrim(str_replace('public/', '', $path), '/');
?>
<article class="editorial-article">
    <header class="editorial-masthead" data-feature-reveal>
        <div class="shell editorial-masthead-grid">
            <div class="editorial-masthead-copy">
                <a class="editorial-back-link" href="<?= View::escape($baseUrl) ?>news"><span aria-hidden="true">&larr;</span> News & announcements</a>
                <p class="newsroom-meta"><?= View::escape($item['category_name']) ?> <span aria-hidden="true">/</span> <?= View::escape(date('j F Y', strtotime((string) $item['article_date']))) ?></p>
                <h1><?= View::escape($item['title']) ?></h1>
                <?php if (!empty($item['summary'])): ?><p class="editorial-deck"><?= View::escape($item['summary']) ?></p><?php endif; ?>
            </div>
            <?php if (!empty($item['media_path'])): ?>
                <figure class="editorial-masthead-media"><img src="<?= View::escape($imageUrl($item['media_path'])) ?>" alt="<?= View::escape($item['media_alt_text'] ?? '') ?>"></figure>
            <?php endif; ?>
        </div>
    </header>

    <section class="editorial-body-section section" data-feature-reveal>
        <div class="shell editorial-body-grid">
            <aside class="editorial-byline">
                <p class="eyebrow">Story details</p>
                <dl>
                    <div><dt>Published</dt><dd><?= View::escape(date('j F Y', strtotime((string) $item['article_date']))) ?></dd></div>
                    <div><dt>Category</dt><dd><?= View::escape($item['category_name']) ?></dd></div>
                    <?php if (!empty($item['author_name'])): ?><div><dt>By</dt><dd><?= View::escape($item['author_name']) ?></dd></div><?php endif; ?>
                </dl>
            </aside>
            <div class="prose editorial-prose"><?= View::richText($item['body']) ?></div>
        </div>
    </section>

    <?php if ($related !== []): ?>
        <section class="editorial-related section" data-feature-reveal>
            <div class="shell">
                <div class="editorial-section-heading"><div><p class="eyebrow">Continue reading</p><h2>More from FAST</h2></div><a class="editorial-link" href="<?= View::escape($baseUrl) ?>news">View all news <span aria-hidden="true">&rarr;</span></a></div>
                <div class="newsroom-story-grid newsroom-story-grid--three">
                    <?php foreach ($related as $story): ?>
                        <article class="newsroom-card">
                            <a class="newsroom-card-media" href="<?= View::escape($baseUrl) ?>news/<?= View::escape($story['slug']) ?>"><?php if (!empty($story['media_path'])): ?><img src="<?= View::escape($imageUrl($story['media_path'])) ?>" alt="<?= View::escape($story['media_alt_text'] ?? '') ?>"><?php else: ?><span class="newsroom-media-placeholder" aria-hidden="true">FAST</span><?php endif; ?></a>
                            <div class="newsroom-card-copy"><p class="newsroom-meta"><?= View::escape($story['category_name']) ?> <span aria-hidden="true">/</span> <?= View::escape(date('j M Y', strtotime((string) $story['article_date']))) ?></p><h3><a href="<?= View::escape($baseUrl) ?>news/<?= View::escape($story['slug']) ?>"><?= View::escape($story['title']) ?></a></h3></div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>
</article>
