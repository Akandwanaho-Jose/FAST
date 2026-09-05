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
    <div>
        <p class="eyebrow">Phase 5</p>
        <h1>Staff directory</h1>
        <p>Manage staff profiles, appointments, images, and publication.</p>
    </div>
    <?php if ($canCreate): ?>
        <a class="button button-primary" href="<?= View::escape($baseUrl) ?>admin/staff/create">Create staff profile</a>
    <?php endif; ?>
</section>
<?php if (is_string($success) && $success !== ''): ?>
    <div class="success-alert" role="status"><?= View::escape($success) ?></div>
<?php endif; ?>
<form class="filter-bar" method="get" action="<?= View::escape($baseUrl) ?>admin/staff">
    <div>
        <label for="staff-search">Search staff</label>
        <input id="staff-search" name="q" type="search" value="<?= View::escape($search) ?>" placeholder="Name, email, or staff number">
    </div>
    <div>
        <label for="staff-status">Status</label>
        <select id="staff-status" name="status">
            <option value="">All statuses</option>
            <?php foreach ($statuses as $status): ?>
                <option value="<?= View::escape($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= View::escape(ucfirst(str_replace('_', ' ', $status))) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button class="button button-primary" type="submit">Apply filters</button>
</form>
<section class="admin-panel">
    <div class="panel-heading"><h2><?= number_format($result['total']) ?> staff profile<?= $result['total'] === 1 ? '' : 's' ?></h2></div>
    <?php if ($result['items'] === []): ?>
        <div class="admin-empty">No staff profiles match the current filters.</div>
    <?php else: ?>
        <div class="table-scroll">
            <table class="admin-table">
                <thead><tr><th>Name</th><th>Position</th><th>Department</th><th>Status</th><th><span class="visually-hidden">Action</span></th></tr></thead>
                <tbody>
                <?php foreach ($result['items'] as $profile): ?>
                    <tr>
                        <th><?= View::escape(trim(implode(' ', array_filter([$profile['honorific_title'], $profile['first_name'], $profile['middle_name'], $profile['last_name']])))) ?><small><?= View::escape($profile['institutional_email'] ?? 'No institutional email') ?></small></th>
                        <td><?= View::escape($profile['position_title'] ?? 'Not assigned') ?></td>
                        <td><?= View::escape($profile['department_name'] ?? 'Faculty-wide') ?></td>
                        <td><span class="status-badge status-<?= View::escape($profile['status']) ?>"><?= View::escape(ucfirst(str_replace('_', ' ', $profile['status']))) ?></span></td>
                        <td><a class="button button-secondary button-compact" href="<?= View::escape($baseUrl) ?>admin/staff/<?= (int) $profile['id'] ?>">Open</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php if ($result['pages'] > 1): ?>
    <nav class="pagination" aria-label="Staff pages">
        <?php for ($page = 1; $page <= $result['pages']; $page++): ?>
            <a href="<?= View::escape($baseUrl . 'admin/staff?' . $queryForPage($page)) ?>" <?= $page === $result['page'] ? 'aria-current="page"' : '' ?>><?= $page ?></a>
        <?php endfor; ?>
    </nav>
<?php endif; ?>
