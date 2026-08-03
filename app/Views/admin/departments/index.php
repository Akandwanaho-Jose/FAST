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
        <p class="eyebrow">Phase 4 content module</p>
        <h1>Departments</h1>
        <p>Manage verified department records and their publication workflow.</p>
    </div>
    <div class="heading-actions">
        <a class="button button-secondary" href="<?= View::escape($baseUrl) ?>admin/faculty">
            Faculty settings
        </a>
        <?php if ($canCreate): ?>
            <a class="button button-primary" href="<?= View::escape($baseUrl) ?>admin/departments/create">
                Create department
            </a>
        <?php endif; ?>
    </div>
</section>

<?php if (is_string($success) && $success !== ''): ?>
    <div class="success-alert" role="status"><?= View::escape($success) ?></div>
<?php endif; ?>

<form class="filter-bar" method="get" action="<?= View::escape($baseUrl) ?>admin/departments">
    <div>
        <label for="department-search">Search departments</label>
        <input
            id="department-search"
            name="q"
            type="search"
            maxlength="100"
            value="<?= View::escape($search) ?>"
            placeholder="Name, short name, or slug"
        >
    </div>
    <div>
        <label for="department-status">Status</label>
        <select id="department-status" name="status">
            <option value="">All statuses</option>
            <?php foreach ($statuses as $status): ?>
                <option value="<?= View::escape($status) ?>" <?= $statusFilter === $status ? 'selected' : '' ?>>
                    <?= View::escape(ucfirst(str_replace('_', ' ', $status))) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <button class="button button-primary" type="submit">Apply filters</button>
    <?php if ($search !== '' || $statusFilter !== ''): ?>
        <a class="button button-secondary" href="<?= View::escape($baseUrl) ?>admin/departments">Clear</a>
    <?php endif; ?>
</form>

<section class="admin-panel">
    <div class="panel-heading">
        <div>
            <p class="eyebrow">Current scope</p>
            <h2><?= number_format($result['total']) ?> department<?= $result['total'] === 1 ? '' : 's' ?></h2>
        </div>
        <span class="scope-pill"><?= View::escape($scopeLabel) ?></span>
    </div>

    <?php if ($result['items'] === []): ?>
        <div class="admin-empty">No departments match the current filters and access scope.</div>
    <?php else: ?>
        <div class="table-scroll">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th scope="col">Department</th>
                        <th scope="col">Faculty</th>
                        <th scope="col">Status</th>
                        <th scope="col">Order</th>
                        <th scope="col">Updated</th>
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($result['items'] as $department): ?>
                        <tr>
                            <th scope="row">
                                <strong><?= View::escape($department['name']) ?></strong>
                                <small><?= View::escape($department['short_name'] ?? 'No short name') ?></small>
                            </th>
                            <td><?= View::escape($department['faculty_short_name']) ?></td>
                            <td>
                                <span class="status-badge status-<?= View::escape($department['status']) ?>">
                                    <?= View::escape(ucfirst(str_replace('_', ' ', $department['status']))) ?>
                                </span>
                            </td>
                            <td><?= number_format((int) $department['display_order']) ?></td>
                            <td><?= View::escape(date('j M Y', strtotime($department['updated_at']))) ?></td>
                            <td>
                                <div class="table-actions">
                                    <?php if ($department['can_edit']): ?>
                                        <a class="button button-primary button-compact" href="<?= View::escape($baseUrl) ?>admin/departments/<?= (int) $department['id'] ?>/edit">
                                            Edit
                                        </a>
                                    <?php endif; ?>
                                    <a class="button button-secondary button-compact" href="<?= View::escape($baseUrl) ?>admin/departments/<?= (int) $department['id'] ?>">
                                        <?= $department['can_edit'] ? 'View' : 'Open' ?>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php if ($result['pages'] > 1): ?>
    <nav class="pagination" aria-label="Department pages">
        <?php for ($page = 1; $page <= $result['pages']; $page++): ?>
            <a
                href="<?= View::escape($baseUrl . 'admin/departments?' . $queryForPage($page)) ?>"
                <?= $page === $result['page'] ? 'aria-current="page"' : '' ?>
            >
                <?= $page ?>
            </a>
        <?php endfor; ?>
    </nav>
<?php endif; ?>
