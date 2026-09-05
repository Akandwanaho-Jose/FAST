<?php

declare(strict_types=1);

use FastWebsite\Core\View;

$queryForPage = static function (int $page) use ($search, $roleFilter): string {
    return http_build_query(array_filter(
        ['q' => $search, 'role' => $roleFilter, 'page' => $page],
        static fn (mixed $value): bool => $value !== ''
    ));
};
?>
<section class="admin-page-heading">
    <div>
        <p class="eyebrow">Administration</p>
        <h1>Users</h1>
        <p>Create accounts and assign the roles that control what each person can do.</p>
    </div>
    <a class="button button-primary" href="<?= View::escape($baseUrl) ?>admin/users/create">Add user</a>
</section>
<?php if (is_string($success) && $success !== ''): ?>
    <div class="success-alert" role="status"><?= View::escape($success) ?></div>
<?php endif; ?>
<form class="filter-bar" method="get" action="<?= View::escape($baseUrl) ?>admin/users">
    <div>
        <label for="user-search">Search users</label>
        <input id="user-search" name="q" type="search" value="<?= View::escape($search) ?>" placeholder="Name or email">
    </div>
    <div>
        <label for="user-role">Role</label>
        <select id="user-role" name="role">
            <option value="">All roles</option>
            <?php foreach ($roles as $role): ?>
                <option value="<?= View::escape($role['code']) ?>" <?= $roleFilter === $role['code'] ? 'selected' : '' ?>><?= View::escape($role['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button class="button button-primary" type="submit">Apply filters</button>
</form>
<section class="admin-panel">
    <div class="panel-heading"><h2><?= number_format($result['total']) ?> user<?= $result['total'] === 1 ? '' : 's' ?></h2></div>
    <?php if ($result['items'] === []): ?>
        <div class="admin-empty">No users match the current filters.</div>
    <?php else: ?>
        <div class="table-scroll">
            <table class="admin-table">
                <thead><tr><th>Name</th><th>Roles</th><th>Status</th><th><span class="visually-hidden">Action</span></th></tr></thead>
                <tbody>
                <?php foreach ($result['items'] as $row): ?>
                    <tr>
                        <th><?= View::escape((string) $row['name']) ?><small><?= View::escape((string) $row['email']) ?></small></th>
                        <td><?= View::escape($row['role_names'] !== null ? (string) $row['role_names'] : 'No roles assigned') ?></td>
                        <td><span class="status-badge <?= (int) $row['is_active'] === 1 ? 'status-published' : '' ?>"><?= (int) $row['is_active'] === 1 ? 'Active' : 'Inactive' ?></span></td>
                        <td><a class="button button-secondary button-compact" href="<?= View::escape($baseUrl) ?>admin/users/<?= (int) $row['id'] ?>/edit">Edit</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php if ($result['pages'] > 1): ?>
    <nav class="pagination" aria-label="User pages">
        <?php for ($page = 1; $page <= $result['pages']; $page++): ?>
            <a href="<?= View::escape($baseUrl . 'admin/users?' . $queryForPage($page)) ?>" <?= $page === $result['page'] ? 'aria-current="page"' : '' ?>><?= $page ?></a>
        <?php endfor; ?>
    </nav>
<?php endif; ?>
