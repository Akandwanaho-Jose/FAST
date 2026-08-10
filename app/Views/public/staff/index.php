<?php

declare(strict_types=1);

use FastWebsite\Core\View;

$mediaUrl = static function (mixed $path) use ($baseUrl): ?string {
    if (!is_string($path) || trim($path) === '' || preg_match('#^(?:https?:)?//#i', $path) === 1) {
        return null;
    }
    return $baseUrl . ltrim(preg_replace('#^public/#', '', str_replace('\\', '/', $path)) ?? $path, '/');
};
$categoryLabels = ['academic'=>'Academic','administrative'=>'Administrative','technical'=>'Technical','support'=>'Support','research'=>'Research','visiting'=>'Visiting','emeritus'=>'Emeritus','other'=>'Other'];
$departmentNames = array_column($departments, 'name', 'id');
$expertiseNames = array_column($expertiseAreas, 'name', 'id');
$hasFilters = $search !== '' || $departmentFilter !== null || $categoryFilter !== '' || $expertiseFilter !== null;
$queryForPage = static function (int $page) use ($search, $departmentFilter, $categoryFilter, $expertiseFilter): string {
    return http_build_query(array_filter(['q'=>$search,'department'=>$departmentFilter,'category'=>$categoryFilter,'expertise'=>$expertiseFilter,'page'=>$page], static fn (mixed $value): bool => $value !== '' && $value !== null));
};
?>
<section class="staff-directory-section" id="staff-directory"><div class="shell">
    <div class="staff-directory-heading" data-staff-reveal><div><p class="eyebrow">FAST directory</p><h2>Meet our academic community</h2></div><p>Connect with the people advancing education, research, innovation, and faculty operations.</p></div>

    <form class="staff-filter-panel" method="get" action="<?= View::escape($baseUrl) ?>staff" data-staff-reveal>
        <div class="staff-filter-heading"><div><p class="eyebrow">Find a colleague</p><h2>Search and filter</h2></div><?php if ($hasFilters): ?><a class="filter-clear" href="<?= View::escape($baseUrl) ?>staff">Clear all filters</a><?php endif; ?></div>
        <div class="staff-filter-grid">
            <div class="filter-field filter-field-search"><label for="staff-directory-search">Name or keyword</label><input id="staff-directory-search" name="q" type="search" maxlength="100" value="<?= View::escape($search) ?>" placeholder="Name, role, or expertise"></div>
            <div class="filter-field"><label for="staff-department">Department</label><select id="staff-department" name="department"><option value="">All departments</option><?php foreach ($departments as $department): ?><option value="<?= (int) $department['id'] ?>" <?= $departmentFilter === (int) $department['id'] ? 'selected' : '' ?>><?= View::escape(preg_replace('/^Department of /', '', (string) $department['name']) ?? $department['name']) ?> (<?= (int) $department['staff_count'] ?>)</option><?php endforeach; ?></select></div>
            <div class="filter-field"><label for="staff-category">Staff category</label><select id="staff-category" name="category"><option value="">All categories</option><?php foreach ($categories as $category): ?><?php $code = (string) $category['staff_category']; ?><option value="<?= View::escape($code) ?>" <?= $categoryFilter === $code ? 'selected' : '' ?>><?= View::escape($categoryLabels[$code] ?? ucfirst($code)) ?> (<?= (int) $category['staff_count'] ?>)</option><?php endforeach; ?></select></div>
            <?php if ($expertiseAreas !== []): ?><div class="filter-field"><label for="staff-expertise">Expertise</label><select id="staff-expertise" name="expertise"><option value="">All expertise areas</option><?php foreach ($expertiseAreas as $area): ?><option value="<?= (int) $area['id'] ?>" <?= $expertiseFilter === (int) $area['id'] ? 'selected' : '' ?>><?= View::escape($area['name']) ?> (<?= (int) $area['staff_count'] ?>)</option><?php endforeach; ?></select></div><?php endif; ?>
            <button class="button button-primary staff-filter-submit" type="submit">Show staff</button>
        </div>
    </form>

    <?php if ($hasFilters): ?><div class="active-filters" aria-label="Active staff filters"><span>Active filters:</span><?php if ($search !== ''): ?><span class="filter-chip">“<?= View::escape($search) ?>”</span><?php endif; ?><?php if ($departmentFilter !== null): ?><span class="filter-chip"><?= View::escape($departmentNames[$departmentFilter] ?? 'Department') ?></span><?php endif; ?><?php if ($categoryFilter !== ''): ?><span class="filter-chip"><?= View::escape($categoryLabels[$categoryFilter] ?? ucfirst($categoryFilter)) ?></span><?php endif; ?><?php if ($expertiseFilter !== null): ?><span class="filter-chip"><?= View::escape($expertiseNames[$expertiseFilter] ?? 'Expertise') ?></span><?php endif; ?></div><?php endif; ?>

    <div class="staff-directory-layout">
        <div class="staff-profile-list">
            <div class="directory-summary" aria-live="polite"><p><strong><?= (int) $result['total'] ?></strong> <?= (int) $result['total'] === 1 ? 'staff profile' : 'staff profiles' ?></p></div>
            <?php if ($result['items'] === []): ?><div class="public-empty" data-staff-reveal><h2><?= $hasFilters ? 'No staff profiles match these filters' : View::escape(View::setting($siteContent ?? [], 'staff.empty', 'Staff profiles are being prepared')) ?></h2><p><?= $hasFilters ? 'Try removing a filter or searching with a broader term.' : 'Published staff profiles will appear here.' ?></p><?php if ($hasFilters): ?><a class="button button-secondary" href="<?= View::escape($baseUrl) ?>staff">View all staff</a><?php endif; ?></div><?php else: ?>
                <?php foreach ($result['items'] as $index => $profile): ?>
                    <?php $image = $mediaUrl($profile['profile_path']); $name = trim(implode(' ', array_filter([$profile['honorific_title'],$profile['first_name'],$profile['middle_name'],$profile['last_name']]))); $initials = mb_strtoupper(mb_substr((string) $profile['first_name'],0,1).mb_substr((string) $profile['last_name'],0,1)); $profileExpertise = is_string($profile['expertise_names']) ? array_slice(array_filter(explode('||',$profile['expertise_names'])),0,2) : []; ?>
                    <article class="staff-directory-row" data-staff-reveal style="--staff-delay: <?= min(3, $index % 4) * 80 ?>ms">
                        <a class="staff-row-portrait" href="<?= View::escape($baseUrl) ?>staff/<?= View::escape($profile['slug']) ?>" tabindex="-1" aria-hidden="true"><?php if ($image): ?><img src="<?= View::escape($image) ?>" alt="" loading="lazy"><?php else: ?><span><?= View::escape($initials) ?></span><?php endif; ?></a>
                        <div class="staff-row-identity"><p class="eyebrow"><?= View::escape($categoryLabels[$profile['staff_category']] ?? ucfirst((string) $profile['staff_category'])) ?></p><h3><a href="<?= View::escape($baseUrl) ?>staff/<?= View::escape($profile['slug']) ?>"><?= View::escape($name) ?></a></h3><p class="staff-position"><?= View::escape($profile['position_title'] ?? 'FAST staff member') ?></p><?php if ($profile['department_name']): ?><p class="staff-department"><?= View::escape($profile['department_name']) ?></p><?php endif; ?><?php if ((int) $profile['supervision_available'] === 1): ?><p class="supervision-available">Available for supervision</p><?php endif; ?></div>
                        <div class="staff-row-contact"><?php if (!empty($profile['institutional_email'])): ?><a href="mailto:<?= View::escape($profile['institutional_email']) ?>"><span>Email</span><?= View::escape($profile['institutional_email']) ?></a><?php endif; ?><?php if (!empty($profile['public_phone'])): ?><a href="tel:<?= View::escape(preg_replace('/[^+0-9]/','',(string)$profile['public_phone']) ?? '') ?>"><span>Phone</span><?= View::escape($profile['public_phone']) ?></a><?php endif; ?><?php if ($profileExpertise !== []): ?><ul aria-label="Expertise"><?php foreach ($profileExpertise as $area): ?><li><?= View::escape($area) ?></li><?php endforeach; ?></ul><?php endif; ?><a class="staff-row-profile-link" href="<?= View::escape($baseUrl) ?>staff/<?= View::escape($profile['slug']) ?>">View profile →</a></div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if ($result['pages'] > 1): ?><nav class="pagination public-pagination" aria-label="Staff directory pages"><?php for ($page=1;$page<=$result['pages'];$page++): ?><a href="<?= View::escape($baseUrl) ?>staff?<?= View::escape($queryForPage($page)) ?>" <?= $page===$result['page']?'aria-current="page"':'' ?>><?= $page ?></a><?php endfor; ?></nav><?php endif; ?>
        </div>

        <?php if ($departments !== []): ?><aside class="staff-department-directory" data-staff-reveal><p class="eyebrow">Browse by unit</p><h2>Departments</h2><nav aria-label="Staff by department"><?php foreach ($departments as $department): ?><a href="<?= View::escape($baseUrl) ?>staff?department=<?= (int) $department['id'] ?>"><span><?= View::escape(preg_replace('/^Department of /','',(string)$department['name']) ?? $department['name']) ?></span><strong><?= (int) $department['staff_count'] ?></strong></a><?php endforeach; ?></nav></aside><?php endif; ?>
    </div>
</div></section>
