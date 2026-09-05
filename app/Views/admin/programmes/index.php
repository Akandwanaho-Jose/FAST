<?php
declare(strict_types=1);
use FastWebsite\Core\View;
$queryForPage = static function (int $page) use ($search, $statusFilter): string {
    return http_build_query(array_filter(
        ['q' => $search, 'status' => $statusFilter, 'page' => $page],
        static fn (mixed $value): bool => $value !== ''
    ));
};
?>
<section class="admin-page-heading">
    <div><p class="eyebrow">Phase 6</p><h1>Academic programmes</h1><p>Manage programme information, ownership, images, and publication.</p></div>
    <?php if ($canCreate): ?><a class="button button-primary" href="<?= View::escape($baseUrl) ?>admin/programmes/create">Create programme</a><?php endif; ?>
</section>
<?php if (is_string($success) && $success !== ''): ?><div class="success-alert" role="status"><?= View::escape($success) ?></div><?php endif; ?>
<form class="filter-bar" method="get" action="<?= View::escape($baseUrl) ?>admin/programmes">
    <div><label for="programme-search">Search programmes</label><input id="programme-search" name="q" type="search" value="<?= View::escape($search) ?>" placeholder="Name, code, or award"></div>
    <div><label for="programme-status">Status</label><select id="programme-status" name="status"><option value="">All statuses</option><?php foreach ($statuses as $status): ?><option value="<?= View::escape($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= View::escape(ucfirst(str_replace('_', ' ', $status))) ?></option><?php endforeach; ?></select></div>
    <button class="button button-primary" type="submit">Apply filters</button>
</form>
<section class="admin-panel">
    <div class="panel-heading"><h2><?= number_format($result['total']) ?> programme<?= $result['total'] === 1 ? '' : 's' ?></h2></div>
    <?php if ($result['items'] === []): ?><div class="admin-empty">No programmes match the current filters.</div><?php else: ?>
    <div class="table-scroll"><table class="admin-table"><thead><tr><th>Programme</th><th>Level</th><th>Lead department</th><th>Status</th><th><span class="visually-hidden">Action</span></th></tr></thead><tbody>
    <?php foreach ($result['items'] as $programme): ?><tr>
        <th><?= View::escape($programme['name']) ?><small><?= View::escape($programme['programme_code'] ?? 'No code') ?></small></th>
        <td><?= View::escape($programme['level_name']) ?></td><td><?= View::escape($programme['department_name'] ?? 'Not assigned') ?></td>
        <td><span class="status-badge status-<?= View::escape($programme['status']) ?>"><?= View::escape(ucfirst(str_replace('_', ' ', $programme['status']))) ?></span></td>
        <td><a class="button button-secondary button-compact" href="<?= View::escape($baseUrl) ?>admin/programmes/<?= (int) $programme['id'] ?>">Open</a></td>
    </tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
<?php if ($result['pages'] > 1): ?>
    <nav class="pagination" aria-label="Programme pages">
        <?php for ($page = 1; $page <= $result['pages']; $page++): ?>
            <a href="<?= View::escape($baseUrl . 'admin/programmes?' . $queryForPage($page)) ?>" <?= $page === $result['page'] ? 'aria-current="page"' : '' ?>><?= $page ?></a>
        <?php endfor; ?>
    </nav>
<?php endif; ?>
