<?php

declare(strict_types=1);

use FastWebsite\Core\View;

?>
<section class="admin-page-heading">
    <div>
        <p class="eyebrow">Staff self-service</p>
        <h1>My publications</h1>
        <p>Publications you're listed as an author on. Add new ones and keep existing entries up to date.</p>
    </div>
    <a class="button button-primary" href="<?= View::escape($baseUrl) ?>my-publications/create">Add publication</a>
</section>
<?php if (isset($success) && $success): ?>
    <div class="success-alert" role="status"><?= View::escape($success) ?></div>
<?php endif; ?>
<?php if ($items === []): ?>
    <p class="admin-empty">No publications yet. Add your first one above.</p>
<?php else: ?>
    <div class="table-scroll">
        <table class="admin-table">
            <thead>
                <tr><th>Title</th><th>Type</th><th>Year</th><th>Journal / source</th><th>Status</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <th><?= View::escape((string) $item['title']) ?></th>
                        <td><?= View::escape((string) $item['type_name']) ?></td>
                        <td><?= View::escape((string) ($item['publication_year'] ?? '—')) ?></td>
                        <td><?= View::escape((string) ($item['journal_name'] ?? '—')) ?></td>
                        <td><span class="status-badge status-<?= View::escape((string) $item['status']) ?>"><?= View::escape(ucfirst(str_replace('_', ' ', (string) $item['status']))) ?></span></td>
                        <td><a class="button button-secondary button-compact" href="<?= View::escape($baseUrl) ?>my-publications/<?= (int) $item['id'] ?>/edit">Edit</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
