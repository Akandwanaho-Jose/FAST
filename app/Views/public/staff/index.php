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

$categoryLabels = [
    'academic' => 'Academic',
    'administrative' => 'Administrative',
    'technical' => 'Technical',
    'support' => 'Support',
    'research' => 'Research',
    'visiting' => 'Visiting',
    'emeritus' => 'Emeritus',
    'other' => 'Other',
];
$departmentNames = array_column($departments, 'name', 'id');
$expertiseNames = array_column($expertiseAreas, 'name', 'id');
$hasFilters = $search !== '' || $departmentFilter !== null
    || $categoryFilter !== '' || $expertiseFilter !== null;
$queryForPage = static function (int $page) use (
    $search,
    $departmentFilter,
    $categoryFilter,
    $expertiseFilter
): string {
    return http_build_query(array_filter([
        'q' => $search,
        'department' => $departmentFilter,
        'category' => $categoryFilter,
        'expertise' => $expertiseFilter,
        'page' => $page,
    ], static fn (mixed $value): bool => $value !== '' && $value !== null));
};
?>

<section class="page-heading people-heading">
    <div class="shell">
        <p class="eyebrow"><?= View::escape(View::setting($siteContent ?? [], 'staff.eyebrow', 'Our people')) ?></p>
        <h1><?= View::escape(View::setting($siteContent ?? [], 'staff.title', 'Staff directory')) ?></h1>
        <p class="lead"><?= View::escape(View::setting($siteContent ?? [], 'staff.introduction', 'Find FAST academic, administrative, technical, and research staff by name, department, category, or expertise.')) ?></p>
    </div>
</section>

<section class="section staff-directory-section">
    <div class="shell">
        <form class="staff-filter-panel" method="get" action="<?= View::escape($baseUrl) ?>staff">
            <div class="staff-filter-heading">
                <div>
                    <p class="eyebrow">Find a colleague</p>
                    <h2>Search and filter</h2>
                </div>
                <?php if ($hasFilters): ?>
                    <a class="filter-clear" href="<?= View::escape($baseUrl) ?>staff">Clear all filters</a>
                <?php endif; ?>
            </div>
            <div class="staff-filter-grid">
                <div class="filter-field filter-field-search">
                    <label for="staff-directory-search">Name or keyword</label>
                    <input id="staff-directory-search" name="q" type="search" maxlength="100" value="<?= View::escape($search) ?>" placeholder="e.g. robotics or Kalyankolo">
                </div>
                <div class="filter-field">
                    <label for="staff-department">Department</label>
                    <select id="staff-department" name="department">
                        <option value="">All departments</option>
                        <?php foreach ($departments as $department): ?>
                            <option value="<?= (int) $department['id'] ?>" <?= $departmentFilter === (int) $department['id'] ? 'selected' : '' ?>><?= View::escape($department['name']) ?> (<?= (int) $department['staff_count'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-field">
                    <label for="staff-category">Staff category</label>
                    <select id="staff-category" name="category">
                        <option value="">All categories</option>
                        <?php foreach ($categories as $category): ?>
                            <?php $categoryCode = (string) $category['staff_category']; ?>
                            <option value="<?= View::escape($categoryCode) ?>" <?= $categoryFilter === $categoryCode ? 'selected' : '' ?>><?= View::escape($categoryLabels[$categoryCode] ?? ucfirst($categoryCode)) ?> (<?= (int) $category['staff_count'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($expertiseAreas !== []): ?>
                    <div class="filter-field">
                        <label for="staff-expertise">Expertise</label>
                        <select id="staff-expertise" name="expertise">
                            <option value="">All expertise areas</option>
                            <?php foreach ($expertiseAreas as $area): ?>
                                <option value="<?= (int) $area['id'] ?>" <?= $expertiseFilter === (int) $area['id'] ? 'selected' : '' ?>><?= View::escape($area['name']) ?> (<?= (int) $area['staff_count'] ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <button class="button button-primary staff-filter-submit" type="submit">Show staff</button>
            </div>
        </form>

        <?php if ($hasFilters): ?>
            <div class="active-filters" aria-label="Active staff filters">
                <span>Active filters:</span>
                <?php if ($search !== ''): ?><span class="filter-chip">“<?= View::escape($search) ?>”</span><?php endif; ?>
                <?php if ($departmentFilter !== null): ?><span class="filter-chip"><?= View::escape($departmentNames[$departmentFilter] ?? 'Department') ?></span><?php endif; ?>
                <?php if ($categoryFilter !== ''): ?><span class="filter-chip"><?= View::escape($categoryLabels[$categoryFilter] ?? ucfirst($categoryFilter)) ?></span><?php endif; ?>
                <?php if ($expertiseFilter !== null): ?><span class="filter-chip"><?= View::escape($expertiseNames[$expertiseFilter] ?? 'Expertise') ?></span><?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="directory-summary" aria-live="polite">
            <p><strong><?= (int) $result['total'] ?></strong> <?= (int) $result['total'] === 1 ? 'staff profile' : 'staff profiles' ?></p>
        </div>

        <?php if ($result['items'] === []): ?>
            <div class="public-empty">
                <h2><?= $hasFilters ? 'No staff profiles match these filters' : View::escape(View::setting($siteContent ?? [], 'staff.empty', 'Staff profiles are being prepared')) ?></h2>
                <p><?= $hasFilters ? 'Try removing a filter or searching with a broader term.' : 'Published staff profiles will appear here.' ?></p>
                <?php if ($hasFilters): ?><a class="button button-secondary" href="<?= View::escape($baseUrl) ?>staff">View all staff</a><?php endif; ?>
            </div>
        <?php else: ?>
            <div class="staff-card-grid">
                <?php foreach ($result['items'] as $profile): ?>
                    <?php
                    $image = $mediaUrl($profile['profile_path']);
                    $name = trim(implode(' ', array_filter([
                        $profile['honorific_title'],
                        $profile['first_name'],
                        $profile['middle_name'],
                        $profile['last_name'],
                    ])));
                    $initials = mb_strtoupper(
                        mb_substr((string) $profile['first_name'], 0, 1)
                        . mb_substr((string) $profile['last_name'], 0, 1)
                    );
                    $expertise = is_string($profile['expertise_names'])
                        ? array_slice(array_filter(explode('||', $profile['expertise_names'])), 0, 3)
                        : [];
                    ?>
                    <article class="staff-card">
                        <?php if ($image !== null): ?>
                            <img src="<?= View::escape($image) ?>" alt="<?= View::escape($profile['profile_alt_text'] ?: 'Portrait of ' . $name) ?>" loading="lazy">
                        <?php else: ?>
                            <div class="staff-card-placeholder" aria-hidden="true"><span><?= View::escape($initials) ?></span></div>
                        <?php endif; ?>
                        <div class="staff-card-body">
                            <p class="staff-category"><?= View::escape($categoryLabels[$profile['staff_category']] ?? ucfirst((string) $profile['staff_category'])) ?></p>
                            <h2><a class="staff-card-link" href="<?= View::escape($baseUrl) ?>staff/<?= View::escape($profile['slug']) ?>"><?= View::escape($name) ?></a></h2>
                            <p class="staff-position"><?= View::escape($profile['position_title'] ?? 'FAST staff member') ?></p>
                            <?php if ($profile['department_name']): ?><p class="staff-department"><?= View::escape($profile['department_name']) ?></p><?php endif; ?>
                            <?php if ($expertise !== []): ?>
                                <ul class="staff-expertise-tags" aria-label="Expertise">
                                    <?php foreach ($expertise as $area): ?><li><?= View::escape($area) ?></li><?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <?php if ((int) $profile['supervision_available'] === 1): ?><p class="supervision-available">Available for supervision</p><?php endif; ?>
                            <span class="staff-card-action" aria-hidden="true">View profile →</span>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if ($result['pages'] > 1): ?>
                <nav class="pagination public-pagination" aria-label="Staff directory pages">
                    <?php for ($page = 1; $page <= $result['pages']; $page++): ?>
                        <a href="<?= View::escape($baseUrl) ?>staff?<?= View::escape($queryForPage($page)) ?>" <?= $page === $result['page'] ? 'aria-current="page"' : '' ?>><?= $page ?></a>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
