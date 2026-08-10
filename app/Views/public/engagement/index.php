<?php

declare(strict_types=1);

use FastWebsite\Core\View;

$mediaUrl = static function (mixed $path) use ($baseUrl): ?string {
    if (!is_string($path) || trim($path) === '' || preg_match('#^(?:https?:)?//#i', $path) === 1) return null;
    return $baseUrl . ltrim(preg_replace('#^public/#', '', str_replace('\\', '/', $path)) ?? $path, '/');
};
$engagementPages = is_array($engagementPages ?? null) ? $engagementPages : [];
$heroPage = null;
foreach ($engagementPages as $candidate) {
    if ($mediaUrl($candidate['hero_path'] ?? null) !== null) { $heroPage = $candidate; break; }
}
$heroImage = $mediaUrl($heroPage['hero_path'] ?? null) ?? $baseUrl . 'assets/images/fast-building.png';
$heroAlt = trim((string) ($heroPage['hero_alt_text'] ?? '')) ?: 'FAST engagement in action';
?>

<header class="engagement-landing-hero">
    <img src="<?= View::escape($heroImage) ?>" alt="<?= View::escape($heroAlt) ?>">
    <div class="engagement-landing-overlay"></div>
    <div class="shell engagement-landing-hero-content" data-feature-reveal>
        <p class="eyebrow"><?= View::escape(View::setting($siteContent ?? [], 'engagement.eyebrow', 'Working together')) ?></p>
        <h1><?= View::escape(View::setting($siteContent ?? [], 'engagement.title', 'Partnerships and impact')) ?></h1>
        <p class="lead"><?= View::escape(View::setting($siteContent ?? [], 'engagement.introduction', 'See how FAST collaborates and turns knowledge into public value.')) ?></p>
        <a href="#engagement-pathways">Explore engagement <span aria-hidden="true">↓</span></a>
    </div>
    <div class="engagement-hero-marker"><strong><?= count($engagementPages) ?></strong><span>ways to engage</span></div>
</header>

<nav class="feature-page-nav" aria-label="Engagement page sections"><div class="shell">
    <span>On this page</span><a href="#engagement-pathways">Engagement pathways</a><a href="#formal-partnerships">Formal partnerships</a><a href="#impact-stories">Impact stories</a>
</div></nav>

<?php if ($engagementPages !== []): ?>
<section class="engagement-pathways" id="engagement-pathways"><div class="shell">
    <div class="engagement-section-heading" data-feature-reveal><div><p class="eyebrow">Connect with FAST</p><h2>From learning to lasting impact</h2></div><p>Explore the programmes and relationships that connect our engineering knowledge with professional practice and community priorities.</p></div>
    <div class="engagement-pathway-grid">
        <?php foreach ($engagementPages as $index => $page): ?>
            <?php $cardImage = $mediaUrl($page['hero_path'] ?? null); ?>
            <article class="engagement-pathway-card" data-feature-reveal style="--feature-delay: <?= min(3, $index) * 100 ?>ms">
                <a class="engagement-pathway-image" href="<?= View::escape($baseUrl . $page['slug']) ?>">
                    <?php if ($cardImage !== null): ?><img src="<?= View::escape($cardImage) ?>" alt="<?= View::escape($page['hero_alt_text'] ?? '') ?>" loading="lazy"><?php else: ?><span aria-hidden="true">FAST</span><?php endif; ?>
                </a>
                <div><p class="eyebrow">FAST engagement</p><h3><a href="<?= View::escape($baseUrl . $page['slug']) ?>"><?= View::escape($page['title']) ?></a></h3><p><?= View::escape($page['meta_description'] ?? '') ?></p><a class="engagement-pathway-link" href="<?= View::escape($baseUrl . $page['slug']) ?>">Explore <?= View::escape(mb_strtolower($page['title'])) ?> <span>→</span></a></div>
            </article>
        <?php endforeach; ?>
    </div>
</div></section>
<?php endif; ?>

<section class="engagement-records" id="formal-partnerships"><div class="shell">
    <div class="engagement-section-heading" data-feature-reveal><div><p class="eyebrow">Strategic relationships</p><h2>Formal partnerships</h2></div><p>Verified institutional collaborations and agreements published by FAST.</p></div>
    <?php if ($partnerships === []): ?><div class="engagement-empty" data-feature-reveal><span aria-hidden="true">P</span><div><h3>Partnership records are being prepared</h3><p>Confirmed organisations and agreements will appear here after publication through the Engagement administration area.</p></div></div>
    <?php else: ?><div class="engagement-record-grid"><?php foreach ($partnerships as $index => $partnership): ?><article data-feature-reveal style="--feature-delay: <?= min(3, $index) * 90 ?>ms"><p class="eyebrow"><?= View::escape(ucwords(str_replace('_', ' ', $partnership['partnership_type']))) ?></p><h3><?= View::escape($partnership['title']) ?></h3><strong><?= View::escape($partnership['partner_name']) ?></strong><p><?= View::escape($partnership['description'] ?? '') ?></p></article><?php endforeach; ?></div><?php endif; ?>
</div></section>

<section class="engagement-impact" id="impact-stories"><div class="shell">
    <div class="engagement-section-heading" data-feature-reveal><div><p class="eyebrow">Evidence of change</p><h2>Impact stories</h2></div><p>See how FAST research, learning and collaboration translate into public value.</p></div>
    <?php if ($impacts === []): ?><div class="engagement-empty is-green" data-feature-reveal><span aria-hidden="true">I</span><div><h3>Impact stories are coming</h3><p>Published stories from departments, projects and partnerships will appear here.</p></div></div>
    <?php else: ?><div class="engagement-impact-grid"><?php foreach ($impacts as $index => $impact): ?><article data-feature-reveal style="--feature-delay: <?= min(3, $index) * 90 ?>ms"><p class="eyebrow"><?= View::escape($impact['impact_date'] ?? 'Impact') ?></p><h3><a href="<?= View::escape($baseUrl) ?>impact/<?= View::escape($impact['slug']) ?>"><?= View::escape($impact['title']) ?></a></h3><p><?= View::escape($impact['summary'] ?? '') ?></p><a href="<?= View::escape($baseUrl) ?>impact/<?= View::escape($impact['slug']) ?>">Read the story →</a></article><?php endforeach; ?></div><?php endif; ?>
</div></section>
