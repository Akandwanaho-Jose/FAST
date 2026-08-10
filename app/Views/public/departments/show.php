<?php

declare(strict_types=1);

use FastWebsite\Core\View;

$programmes = $programmes ?? [];
$location = array_filter([$department['campus'], $department['building'], $department['floor'], $department['room']], static fn (mixed $value): bool => is_string($value) && trim($value) !== '');
$mediaUrl = static function (mixed $path) use ($baseUrl): ?string {
    if (!is_string($path) || trim($path) === '') {
        return null;
    }
    $path = str_replace('\\', '/', $path);
    if (preg_match('#^(?:https?:)?//#i', $path) === 1) {
        return null;
    }
    return $baseUrl . ltrim(preg_replace('#^public/#', '', $path) ?? $path, '/');
};
$heroUrl = $mediaUrl($department['hero_path']) ?? $baseUrl . 'assets/images/fast-building.png';
$heroAlt = trim((string) ($department['hero_alt_text'] ?? '')) ?: (string) $department['name'];
$hasDirection = array_filter([$department['vision'], $department['mission'], $department['strategic_direction']], static fn (mixed $value): bool => is_string($value) && trim($value) !== '') !== [];
$departmentName = preg_replace('/^Department of /', '', (string) $department['name']) ?? $department['name'];
?>
<header class="department-profile-hero">
    <div class="shell department-profile-hero-grid">
        <div class="department-profile-copy" data-department-reveal>
            <a class="department-back-link" href="<?= View::escape($baseUrl) ?>departments">← All departments</a>
            <p class="eyebrow"><?= View::escape($department['faculty_name']) ?></p>
            <h1<?= mb_strlen((string) $departmentName) > 42 ? ' class="is-long-title"' : '' ?>><?= View::escape($departmentName) ?></h1>
            <?php if (is_string($department['overview']) && trim($department['overview']) !== ''): ?><p class="lead"><?= View::escape(mb_strimwidth(strip_tags($department['overview']), 0, 245, '…')) ?></p><?php endif; ?>
            <div class="department-hero-actions"><?php if ($programmes !== []): ?><a class="button button-primary" href="#programmes">Explore programmes</a><?php endif; ?><a class="department-people-link" href="<?= View::escape($baseUrl) ?>staff?department=<?= (int) $department['id'] ?>">Meet our people →</a></div>
        </div>
        <figure class="department-profile-image" data-department-reveal><img src="<?= View::escape($heroUrl) ?>" alt="<?= View::escape($heroAlt) ?>"></figure>
    </div>
</header>

<nav class="department-section-nav" aria-label="On this department page"><div class="shell"><span>On this page</span><a href="#overview">Overview</a><?php if ($programmes !== []): ?><a href="#programmes">Programmes</a><?php endif; ?><?php if ($hasDirection): ?><a href="#direction">Vision &amp; direction</a><?php endif; ?><a href="#contact">Contact</a></div></nav>

<section class="department-fact-strip"><div class="shell">
    <div data-department-reveal><span>Programmes</span><strong><?= count($programmes) ?> published</strong></div>
    <div data-department-reveal><span>Academic community</span><strong><a href="<?= View::escape($baseUrl) ?>staff?department=<?= (int) $department['id'] ?>">View department staff</a></strong></div>
    <div data-department-reveal><span>Location</span><strong><?= View::escape($location !== [] ? implode(', ', array_slice($location, 0, 2)) : 'FAST, MUST') ?></strong></div>
    <div data-department-reveal><span>Contact</span><strong><?= View::escape($department['email'] ?: ($department['phone'] ?: 'Contact FAST')) ?></strong></div>
</div></section>

<section class="department-profile-body" id="overview">
    <div class="shell department-profile-layout">
        <div class="department-profile-content">
            <article class="department-editorial-section" data-department-reveal><p class="eyebrow">About the department</p><h2>Engineering knowledge for practical impact</h2><?php if (is_string($department['overview']) && trim($department['overview']) !== ''): ?><div class="prose department-prose"><?= View::richText($department['overview']) ?></div><?php else: ?><p class="lead">The department overview is being prepared.</p><?php endif; ?></article>
            <?php if (is_string($department['history']) && trim($department['history']) !== ''): ?><article class="department-editorial-section department-history-panel" data-department-reveal><p class="eyebrow">Our story</p><h2>Department history</h2><div class="prose department-prose"><?= View::richText($department['history']) ?></div></article><?php endif; ?>
        </div>

        <aside class="department-contact-panel" id="contact" data-department-reveal>
            <p class="eyebrow">Get in touch</p><h2><?= View::escape($department['short_name'] ?? $departmentName) ?></h2>
            <dl><?php if (is_string($department['email']) && trim($department['email']) !== ''): ?><div><dt>Email</dt><dd><a href="mailto:<?= View::escape($department['email']) ?>"><?= View::escape($department['email']) ?></a></dd></div><?php endif; ?><?php if (is_string($department['phone']) && trim($department['phone']) !== ''): ?><div><dt>Phone</dt><dd><?= View::escape($department['phone']) ?></dd></div><?php endif; ?><?php if ($location !== []): ?><div><dt>Location</dt><dd><?= View::escape(implode(', ', $location)) ?></dd></div><?php endif; ?></dl>
            <?php if ((!is_string($department['email']) || trim($department['email']) === '') && (!is_string($department['phone']) || trim($department['phone']) === '') && $location === []): ?><p>Verified contact information has not yet been published.</p><?php endif; ?>
            <a class="department-panel-link" href="<?= View::escape($baseUrl) ?>staff?department=<?= (int) $department['id'] ?>">View department staff →</a>
        </aside>
    </div>
</section>

<?php if ($programmes !== []): ?>
<section class="department-programmes-showcase" id="programmes"><div class="shell">
    <div class="department-section-heading-row" data-department-reveal><div><p class="eyebrow">Study with us</p><h2>Programmes shaped for the real world</h2></div><a href="<?= View::escape($baseUrl) ?>programmes?department=<?= View::escape($department['slug']) ?>">View all department programmes →</a></div>
    <div class="department-programme-grid"><?php foreach ($programmes as $index => $programme): ?><article data-department-reveal style="--department-delay: <?= min(3, $index) * 100 ?>ms"><p class="eyebrow"><?= View::escape($programme['level_name']) ?></p><h3><a href="<?= View::escape($baseUrl) ?>programmes/<?= View::escape($programme['slug']) ?>"><?= View::escape($programme['name']) ?></a></h3><ul><?php foreach (array_filter([$programme['programme_code'], $programme['duration_text']]) as $fact): ?><li><?= View::escape($fact) ?></li><?php endforeach; ?></ul><?php if (trim((string) ($programme['overview'] ?? '')) !== ''): ?><p><?= View::escape(mb_strimwidth(strip_tags((string) $programme['overview']), 0, 150, '…')) ?></p><?php endif; ?><a class="department-programme-link" href="<?= View::escape($baseUrl) ?>programmes/<?= View::escape($programme['slug']) ?>">Programme details <span aria-hidden="true">→</span></a></article><?php endforeach; ?></div>
</div></section>
<?php endif; ?>

<?php if ($hasDirection): ?>
<section class="department-direction-showcase" id="direction"><div class="shell">
    <div class="department-section-heading-row" data-department-reveal><div><p class="eyebrow">Where we are going</p><h2>Vision and direction</h2></div><p>Our shared direction for education, research, and impact.</p></div>
    <div class="department-direction-grid"><?php foreach (['Vision' => $department['vision'], 'Mission' => $department['mission'], 'Strategic direction' => $department['strategic_direction']] as $heading => $value): ?><?php if (is_string($value) && trim($value) !== ''): ?><article data-department-reveal><p class="eyebrow">Our <?= View::escape(mb_strtolower($heading)) ?></p><h3><?= View::escape($heading) ?></h3><div class="prose"><?= View::richText($value) ?></div></article><?php endif; ?><?php endforeach; ?></div>
</div></section>
<?php endif; ?>
