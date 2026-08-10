<?php

declare(strict_types=1);

use FastWebsite\Core\View;

$mediaUrl = static function (mixed $path) use ($baseUrl): ?string {
    if (!is_string($path) || trim($path) === '' || preg_match('#^(?:https?:)?//#i', $path) === 1) {
        return null;
    }
    return $baseUrl . ltrim(preg_replace('#^public/#', '', str_replace('\\', '/', $path)) ?? $path, '/');
};
$queryForPage = static function (int $page) use ($search, $categoryFilter, $departmentFilter): string {
    return http_build_query(array_filter(
        ['q' => $search, 'category' => $categoryFilter, 'department' => $departmentFilter, 'page' => $page],
        static fn (mixed $value): bool => $value !== ''
    ));
};
$pathwayUrl = static function (string $category) use ($baseUrl, $search, $departmentFilter): string {
    $query = http_build_query(array_filter(
        ['q' => $search, 'department' => $departmentFilter, 'category' => $category],
        static fn (mixed $value): bool => $value !== ''
    ));
    return $baseUrl . 'programmes' . ($query !== '' ? '?' . $query : '');
};
$featuredImage = null;
$featuredAlt = '';
foreach ($result['items'] as $item) {
    $candidate = $mediaUrl($item['hero_path'] ?? null);
    if ($candidate !== null) {
        $featuredImage = $candidate;
        $featuredAlt = (string) ($item['hero_alt_text'] ?: $item['name']);
        break;
    }
}
$featuredImage ??= $baseUrl . 'assets/images/fast-building.png';
$categoryCounts = is_array($categoryCounts ?? null) ? $categoryCounts : ['all' => $result['total'], 'undergraduate' => 0, 'postgraduate' => 0];
?>
<header class="programme-directory-hero">
    <div class="shell programme-directory-hero-grid">
        <div class="programme-directory-intro" data-programme-reveal>
            <p class="eyebrow"><?= View::escape(View::setting($siteContent ?? [], 'programmes.eyebrow', 'Study at FAST')) ?></p>
            <h1><?= View::escape(View::setting($siteContent ?? [], 'programmes.title', 'Academic programmes')) ?></h1>
            <p class="lead"><?= View::escape(View::setting($siteContent ?? [], 'programmes.introduction', 'Explore undergraduate and postgraduate study, then refine by department or subject.')) ?></p>
            <a class="programme-hero-link" href="#programme-directory">Find your programme <span aria-hidden="true">↓</span></a>
        </div>
        <figure class="programme-directory-visual" data-programme-reveal>
            <img src="<?= View::escape($featuredImage) ?>" alt="<?= View::escape($featuredAlt) ?>">
            <figcaption><strong><?= (int) $categoryCounts['all'] ?></strong><span>published programme<?= (int) $categoryCounts['all'] === 1 ? '' : 's' ?></span></figcaption>
        </figure>
    </div>
</header>

<nav class="programme-pathways" aria-label="Programme categories">
    <div class="shell">
        <?php foreach (['' => 'All programmes', 'undergraduate' => 'Undergraduate', 'postgraduate' => 'Postgraduate'] as $value => $label): ?>
            <a href="<?= View::escape($pathwayUrl($value)) ?>" <?= $categoryFilter === $value ? 'aria-current="page"' : '' ?>>
                <span><?= View::escape($label) ?></span><strong><?= (int) $categoryCounts[$value === '' ? 'all' : $value] ?></strong>
            </a>
        <?php endforeach; ?>
    </div>
</nav>

<section class="programme-directory-section" id="programme-directory">
    <div class="shell">
        <div class="programme-directory-heading" data-programme-reveal>
            <div><p class="eyebrow">Explore your options</p><h2><?= $categoryFilter === '' ? 'Choose your path' : View::escape(ucfirst($categoryFilter) . ' programmes') ?></h2></div>
            <p><?= (int) $result['total'] ?> result<?= (int) $result['total'] === 1 ? '' : 's' ?><?= $departmentFilter !== '' || $search !== '' ? ' matching your filters' : '' ?></p>
        </div>

        <form class="programme-filter-bar" method="get" action="<?= View::escape($baseUrl) ?>programmes" data-programme-reveal>
            <label class="programme-filter-search" for="programme-directory-search"><span>Search programmes</span><input id="programme-directory-search" name="q" type="search" maxlength="100" value="<?= View::escape($search) ?>" placeholder="Name, code, or subject"></label>
            <label><span>Department</span><select name="department"><option value="">All departments</option><?php foreach ($departments as $department): ?><?php $departmentName = preg_replace('/^Department of /', '', (string) $department['name']) ?? (string) $department['name']; ?><option value="<?= View::escape($department['slug']) ?>" <?= $departmentFilter === $department['slug'] ? 'selected' : '' ?>><?= View::escape($departmentName) ?></option><?php endforeach; ?></select></label>
            <label><span>Study level</span><select name="category"><option value="">All levels</option><option value="undergraduate" <?= $categoryFilter === 'undergraduate' ? 'selected' : '' ?>>Undergraduate</option><option value="postgraduate" <?= $categoryFilter === 'postgraduate' ? 'selected' : '' ?>>Postgraduate</option></select></label>
            <button class="button button-primary" type="submit">Show programmes</button>
            <?php if ($search !== '' || $categoryFilter !== '' || $departmentFilter !== ''): ?><a class="programme-clear-filter" href="<?= View::escape($baseUrl) ?>programmes">Clear</a><?php endif; ?>
        </form>

        <?php if ($result['items'] === []): ?>
            <div class="public-empty programme-empty" data-programme-reveal><p class="eyebrow">No matches</p><h2><?= $search === '' && $categoryFilter === '' && $departmentFilter === '' ? View::escape(View::setting($siteContent ?? [], 'programmes.empty', 'Programme pages are being prepared')) : 'No programmes found' ?></h2><p>Try another department, study level, or search term. Published programmes will appear here automatically.</p><a class="button button-secondary" href="<?= View::escape($baseUrl) ?>programmes">View all programmes</a></div>
        <?php else: ?>
            <div class="programme-directory-grid">
                <?php foreach ($result['items'] as $index => $programme): ?>
                    <?php $image = $mediaUrl($programme['hero_path']); $duration = $programme['duration_text'] ?: ($programme['duration_years'] ? $programme['duration_years'] . ' years' : null); ?>
                    <article class="programme-directory-card<?= $image === null ? ' has-placeholder' : '' ?>" data-programme-reveal style="--programme-delay: <?= min(3, $index % 4) * 90 ?>ms">
                        <a class="programme-card-media" href="<?= View::escape($baseUrl) ?>programmes/<?= View::escape($programme['slug']) ?>" tabindex="-1" aria-hidden="true">
                            <?php if ($image !== null): ?><img src="<?= View::escape($image) ?>" alt="" loading="lazy"><?php else: ?><span><?= View::escape($programme['programme_code'] ?: 'FAST') ?></span><?php endif; ?>
                        </a>
                        <div class="programme-card-copy">
                            <p class="eyebrow"><?= View::escape($programme['level_name']) ?></p>
                            <h3><a href="<?= View::escape($baseUrl) ?>programmes/<?= View::escape($programme['slug']) ?>"><?= View::escape($programme['name']) ?></a></h3>
                            <?php if (trim((string) $programme['overview']) !== ''): ?><p><?= View::escape(mb_strimwidth(strip_tags((string) $programme['overview']), 0, 175, '…')) ?></p><?php endif; ?>
                            <ul class="programme-card-meta" aria-label="Programme facts"><?php foreach (array_filter([$programme['programme_code'], $duration, $programme['department_name']]) as $fact): ?><li><?= View::escape($fact) ?></li><?php endforeach; ?></ul>
                            <a class="programme-card-link" href="<?= View::escape($baseUrl) ?>programmes/<?= View::escape($programme['slug']) ?>"><?= View::escape(View::setting($siteContent ?? [], 'programmes.card_link', 'View programme')) ?> <span aria-hidden="true">→</span></a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($result['pages'] > 1): ?><nav class="pagination public-pagination" aria-label="Programme pages"><?php for ($page = 1; $page <= $result['pages']; $page++): ?><a href="<?= View::escape($baseUrl . 'programmes?' . $queryForPage($page)) ?>" <?= $page === $result['page'] ? 'aria-current="page"' : '' ?>><?= $page ?></a><?php endfor; ?></nav><?php endif; ?>
    </div>
</section>
