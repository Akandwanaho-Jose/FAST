<?php

declare(strict_types=1);

use FastWebsite\Core\View;

$queryForPage = static function (int $page) use ($search): string {
    return http_build_query(array_filter(['q' => $search, 'page' => $page], static fn (mixed $value): bool => $value !== ''));
};
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
$featuredImage = null;
$featuredAlt = '';
foreach ($result['items'] as $item) {
    $candidate = $mediaUrl($item['hero_path'] ?? null);
    if ($candidate !== null) {
        $featuredImage = $candidate;
        $featuredAlt = trim((string) ($item['hero_alt_text'] ?? ''));
        break;
    }
}
$featuredImage ??= $baseUrl . 'assets/images/fast-building.png';
?>
<header class="department-directory-hero">
    <div class="shell department-directory-hero-grid">
        <div class="department-directory-intro" data-department-reveal>
            <p class="eyebrow"><?= View::escape(View::setting($siteContent ?? [], 'departments.eyebrow', 'Faculty structure')) ?></p>
            <h1><?= View::escape(View::setting($siteContent ?? [], 'departments.title', 'Departments')) ?></h1>
            <p class="lead"><?= View::escape(View::setting($siteContent ?? [], 'departments.introduction', 'Explore published academic departments in the Faculty of Applied Sciences and Technology.')) ?></p>
            <a class="department-hero-link" href="#department-directory">Explore our disciplines <span aria-hidden="true">↓</span></a>
        </div>
        <figure class="department-directory-visual" data-department-reveal>
            <img src="<?= View::escape($featuredImage) ?>" alt="<?= View::escape($featuredAlt) ?>">
            <figcaption><strong><?= (int) $result['total'] ?></strong><span>academic department<?= (int) $result['total'] === 1 ? '' : 's' ?></span></figcaption>
        </figure>
    </div>
</header>

<section class="department-directory-section" id="department-directory">
    <div class="shell">
        <div class="department-directory-heading" data-department-reveal>
            <div><p class="eyebrow">Find your discipline</p><h2>Where ideas become practice</h2></div>
            <p>Explore teaching, research, facilities, and people across FAST.</p>
        </div>

        <form class="department-filter-bar" method="get" action="<?= View::escape($baseUrl) ?>departments" data-department-reveal>
            <label for="public-department-search"><span>Search departments</span><input id="public-department-search" name="q" type="search" maxlength="100" value="<?= View::escape($search) ?>" placeholder="Search by name or area"></label>
            <button class="button button-primary" type="submit">Find department</button>
            <?php if ($search !== ''): ?><a href="<?= View::escape($baseUrl) ?>departments">Clear search</a><?php endif; ?>
        </form>

        <?php if ($result['items'] === []): ?>
            <div class="public-empty" data-department-reveal><p class="eyebrow">No matches</p><h2><?= $search === '' ? View::escape(View::setting($siteContent ?? [], 'departments.empty', 'Department profiles are being prepared')) : 'No published departments found' ?></h2><p><?= $search === '' ? 'Published department profiles will appear here automatically.' : 'Try a different search term or clear the search.' ?></p><?php if ($search !== ''): ?><a class="button button-secondary" href="<?= View::escape($baseUrl) ?>departments">View all departments</a><?php endif; ?></div>
        <?php else: ?>
            <div class="department-directory-grid">
                <?php foreach ($result['items'] as $index => $department): ?>
                    <?php $image = $mediaUrl($department['hero_path']); $departmentName = preg_replace('/^Department of /', '', (string) $department['name']) ?? $department['name']; ?>
                    <article class="department-directory-card<?= $image === null ? ' has-placeholder' : '' ?>" data-department-reveal style="--department-delay: <?= min(3, $index % 4) * 100 ?>ms">
                        <a class="department-card-visual" href="<?= View::escape($baseUrl) ?>departments/<?= View::escape($department['slug']) ?>" tabindex="-1" aria-hidden="true">
                            <?php if ($image !== null): ?><img src="<?= View::escape($image) ?>" alt="" loading="lazy"><?php else: ?><span><?= View::escape($department['short_name'] ?? 'FAST') ?></span><?php endif; ?>
                        </a>
                        <div class="department-directory-copy">
                            <p class="eyebrow"><?= View::escape($department['short_name'] ?? 'FAST') ?></p>
                            <h3><a href="<?= View::escape($baseUrl) ?>departments/<?= View::escape($department['slug']) ?>"><?= View::escape($departmentName) ?></a></h3>
                            <?php if (is_string($department['overview']) && trim($department['overview']) !== ''): ?><p><?= View::escape(mb_strimwidth(strip_tags($department['overview']), 0, 180, '…')) ?></p><?php else: ?><p>Explore the department’s teaching, research, facilities, and academic community.</p><?php endif; ?>
                            <a class="department-directory-link" href="<?= View::escape($baseUrl) ?>departments/<?= View::escape($department['slug']) ?>"><?= View::escape(View::setting($siteContent ?? [], 'departments.card_link', 'Explore department')) ?> <span aria-hidden="true">→</span></a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($result['pages'] > 1): ?><nav class="pagination public-pagination" aria-label="Department pages"><?php for ($page = 1; $page <= $result['pages']; $page++): ?><a href="<?= View::escape($baseUrl . 'departments?' . $queryForPage($page)) ?>" <?= $page === $result['page'] ? 'aria-current="page"' : '' ?>><?= $page ?></a><?php endfor; ?></nav><?php endif; ?>
    </div>
</section>
