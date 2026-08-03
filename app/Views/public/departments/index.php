<?php

declare(strict_types=1);

use FastWebsite\Core\View;

$queryForPage = static function (int $page) use ($search): string {
    return http_build_query(array_filter(
        ['q' => $search, 'page' => $page],
        static fn (mixed $value): bool => $value !== ''
    ));
};
$imageUrl = static function (mixed $path) use ($baseUrl): ?string {
    if (!is_string($path) || trim($path) === '') {
        return null;
    }

    $path = str_replace('\\', '/', $path);

    if (preg_match('#^(?:https?:)?//#i', $path) === 1) {
        return null;
    }

    return $baseUrl . ltrim(
        preg_replace('#^public/#', '', $path) ?? $path,
        '/'
    );
};
?>
<section class="page-heading department-heading">
    <div class="shell">
        <p class="eyebrow"><?= View::escape(View::setting($siteContent ?? [], 'departments.eyebrow', 'Faculty structure')) ?></p>
        <h1><?= View::escape(View::setting($siteContent ?? [], 'departments.title', 'Departments')) ?></h1>
        <p class="lead"><?= View::escape(View::setting($siteContent ?? [], 'departments.introduction', 'Explore published academic departments in the Faculty of Applied Sciences and Technology.')) ?></p>
    </div>
</section>

<section class="section">
    <div class="shell">
        <form class="public-search" method="get" action="<?= View::escape($baseUrl) ?>departments">
            <label for="public-department-search">Search published departments</label>
            <div>
                <input
                    id="public-department-search"
                    name="q"
                    type="search"
                    maxlength="100"
                    value="<?= View::escape($search) ?>"
                    placeholder="Search by name or area"
                >
                <button class="button button-primary" type="submit">Search</button>
            </div>
        </form>

        <?php if ($result['items'] === []): ?>
            <div class="public-empty">
                <h2><?= $search === '' ? View::escape(View::setting($siteContent ?? [], 'departments.empty', 'Department profiles are being prepared')) : 'No published departments found' ?></h2>
                <p>
                    <?= $search === ''
                        ? 'Department starter records are currently in the editorial workflow and will appear here only after publication.'
                        : 'Try a different search term or clear the search.' ?>
                </p>
                <?php if ($search !== ''): ?>
                    <a class="button button-secondary" href="<?= View::escape($baseUrl) ?>departments">Clear search</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="department-card-grid">
                <?php foreach ($result['items'] as $department): ?>
                    <article class="department-card">
                        <?php $cardImageUrl = $imageUrl($department['hero_path']); ?>
                        <?php if ($cardImageUrl !== null): ?>
                            <img
                                class="department-card-image"
                                src="<?= View::escape($cardImageUrl) ?>"
                                alt="<?= View::escape($department['hero_alt_text'] ?? '') ?>"
                            >
                        <?php endif; ?>
                        <p class="department-code"><?= View::escape($department['short_name'] ?? 'FAST') ?></p>
                        <h2>
                            <a href="<?= View::escape($baseUrl) ?>departments/<?= View::escape($department['slug']) ?>">
                                <?= View::escape($department['name']) ?>
                            </a>
                        </h2>
                        <?php if (is_string($department['overview']) && trim($department['overview']) !== ''): ?>
                            <p><?= View::escape(mb_strimwidth($department['overview'], 0, 220, '…')) ?></p>
                        <?php else: ?>
                            <p>View the department profile, contact details, and academic direction.</p>
                        <?php endif; ?>
                        <a class="text-link" href="<?= View::escape($baseUrl) ?>departments/<?= View::escape($department['slug']) ?>">
                            <?= View::escape(View::setting($siteContent ?? [], 'departments.card_link', 'Explore department →')) ?>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($result['pages'] > 1): ?>
            <nav class="pagination public-pagination" aria-label="Department pages">
                <?php for ($page = 1; $page <= $result['pages']; $page++): ?>
                    <a
                        href="<?= View::escape($baseUrl . 'departments?' . $queryForPage($page)) ?>"
                        <?= $page === $result['page'] ? 'aria-current="page"' : '' ?>
                    ><?= $page ?></a>
                <?php endfor; ?>
            </nav>
        <?php endif; ?>
    </div>
</section>
