<?php

declare(strict_types=1);

use FastWebsite\Core\View;

$mediaUrl = static function (mixed $path) use ($baseUrl): ?string {
    if (!is_string($path) || trim($path) === '' || preg_match('#^(?:https?:)?//#i', $path) === 1) {
        return null;
    }

    return $baseUrl . ltrim(
        preg_replace('#^public/#', '', str_replace('\\', '/', $path)) ?? $path,
        '/'
    );
};
?>
<section class="page-heading department-heading">
    <div class="shell">
        <p class="eyebrow"><?= View::escape(View::setting($siteContent ?? [], 'programmes.eyebrow', 'Study at FAST')) ?></p>
        <h1><?= View::escape(View::setting($siteContent ?? [], 'programmes.title', 'Academic programmes')) ?></h1>
        <p class="lead"><?= View::escape(View::setting($siteContent ?? [], 'programmes.introduction', 'Explore undergraduate and postgraduate study, then refine by department or subject.')) ?></p>
    </div>
</section>

<section class="section">
    <div class="shell">
        <form class="public-search programme-search" method="get" action="<?= View::escape($baseUrl) ?>programmes">
            <label for="programme-directory-search">Find a programme</label>
            <div>
                <input id="programme-directory-search" name="q" type="search" maxlength="100" value="<?= View::escape($search) ?>" placeholder="Name, code, or subject">
                <select name="department" aria-label="Department">
                    <option value="">All departments</option>
                    <?php foreach ($departments as $department): ?>
                        <?php $departmentName = preg_replace('/^Department of /', '', (string) $department['name']) ?? (string) $department['name']; ?>
                        <option value="<?= View::escape($department['slug']) ?>" <?= $departmentFilter === $department['slug'] ? 'selected' : '' ?>><?= View::escape($departmentName) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="category" aria-label="Programme category">
                    <option value="">All programmes</option>
                    <option value="undergraduate" <?= $categoryFilter === 'undergraduate' ? 'selected' : '' ?>>Undergraduate</option>
                    <option value="postgraduate" <?= $categoryFilter === 'postgraduate' ? 'selected' : '' ?>>Postgraduate</option>
                </select>
                <button class="button button-primary" type="submit">Search</button>
            </div>
        </form>

        <?php if ($result['items'] === []): ?>
            <div class="public-empty">
                <h2><?= $search === '' && $categoryFilter === '' && $departmentFilter === '' ? View::escape(View::setting($siteContent ?? [], 'programmes.empty', 'Programme pages are being prepared')) : 'No programmes found' ?></h2>
                <p>Try another department, category, or search term.</p>
            </div>
        <?php else: ?>
            <div class="department-card-grid">
                <?php foreach ($result['items'] as $programme): ?>
                    <?php $image = $mediaUrl($programme['hero_path']); ?>
                    <article class="department-card">
                        <?php if ($image): ?><img src="<?= View::escape($image) ?>" alt="<?= View::escape($programme['hero_alt_text'] ?: $programme['name']) ?>"><?php endif; ?>
                        <div>
                            <p class="eyebrow"><?= View::escape($programme['level_name']) ?></p>
                            <h2><a href="<?= View::escape($baseUrl) ?>programmes/<?= View::escape($programme['slug']) ?>"><?= View::escape($programme['name']) ?></a></h2>
                            <p><?= View::escape(mb_strimwidth((string) $programme['overview'], 0, 190, '…')) ?></p>
                            <p class="card-meta"><?= View::escape(implode(' · ', array_filter([$programme['programme_code'], $programme['duration_text'], $programme['department_name']]))) ?></p>
                            <a class="text-link" href="<?= View::escape($baseUrl) ?>programmes/<?= View::escape($programme['slug']) ?>"><?= View::escape(View::setting($siteContent ?? [], 'programmes.card_link', 'View programme →')) ?></a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
