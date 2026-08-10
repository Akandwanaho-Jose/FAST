<?php

declare(strict_types=1);

use FastWebsite\Core\View;
?>
<section class="admin-page-heading">
    <div><p class="eyebrow">FAST engagement</p><h1>Engagement</h1><p>Manage engagement pages, formal partnerships and published impact stories from one place.</p></div>
    <div class="heading-actions">
        <?php if ($canCreate): ?><a class="button button-secondary" href="<?= View::escape($baseUrl) ?>admin/engagement/impact/create">Add impact story</a><?php endif; ?>
        <?php if ($canCreate && $canManagePartnerships): ?><a class="button button-primary" href="<?= View::escape($baseUrl) ?>admin/engagement/partnerships/create">Add partnership record</a><?php endif; ?>
    </div>
</section>

<section class="admin-panel">
    <div class="compact-heading"><p class="eyebrow">Public engagement content</p><h2>Engagement pages</h2><p>These visual pages appear under Partnerships on the public website.</p></div>
    <?php if (($engagementPages ?? []) === []): ?>
        <p class="admin-empty">The engagement pages have not been created.</p>
    <?php else: ?>
        <div class="admin-card-grid">
            <?php foreach ($engagementPages as $page): ?>
                <article class="admin-card">
                    <p class="eyebrow"><?= View::escape(ucfirst((string) $page['status'])) ?></p>
                    <h3><?= View::escape($page['title']) ?></h3>
                    <p><?= View::escape($page['meta_description'] ?? '') ?></p>
                    <div class="table-actions">
                        <?php if ($canManagePages): ?><a href="<?= View::escape($baseUrl) ?>admin/pages/<?= (int) $page['id'] ?>/edit">Manage content and images</a><?php endif; ?>
                        <a href="<?= View::escape($baseUrl . $page['slug']) ?>">View page</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="admin-panel">
    <h2>Formal partnership records</h2>
    <p>Use these records for named partner organisations, agreements and collaborations.</p>
    <?php if ($partnerships === []): ?><p class="admin-empty">No formal partnerships recorded.</p>
    <?php else: ?><div class="table-scroll"><table class="admin-table"><thead><tr><th>Partnership</th><th>Partner</th><th>Type</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach ($partnerships as $partnership): ?><tr><th><?= View::escape($partnership['title']) ?></th><td><?= View::escape($partnership['partner_name']) ?></td><td><?= View::escape(ucwords(str_replace('_', ' ', $partnership['partnership_type']))) ?></td><td><?= View::escape($partnership['status']) ?></td><td><div class="table-actions"><?php if ($canEdit && $canManagePartnerships): ?><a href="<?= View::escape($baseUrl) ?>admin/engagement/partnerships/<?= (int) $partnership['id'] ?>/edit">Edit</a><?php $action=$partnership['status']==='published'?'archive':($partnership['status']==='archived'?'restore':'publish'); if ($action!=='publish'||$canPublish): ?><form method="post" action="<?= View::escape($baseUrl) ?>admin/engagement/partnerships/<?= (int) $partnership['id'] ?>/workflow"><input type="hidden" name="_token" value="<?= View::escape($csrfToken) ?>"><button class="link-button" name="action" value="<?= $action ?>"><?= ucfirst($action) ?></button></form><?php endif; endif; ?></div></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>

<section class="admin-panel" id="impact">
    <h2>Impact stories</h2>
    <?php if ($impacts === []): ?><p class="admin-empty">No impact stories recorded.</p>
    <?php else: ?><div class="table-scroll"><table class="admin-table"><thead><tr><th>Story</th><th>Date</th><th>Department</th><th>Status</th><th>Actions</th></tr></thead><tbody><?php foreach ($impacts as $impact): ?><tr><th><?= View::escape($impact['title']) ?></th><td><?= View::escape($impact['impact_date'] ?? 'Not set') ?></td><td><?= View::escape($impact['department_name'] ?? 'Faculty-wide') ?></td><td><?= View::escape($impact['status']) ?></td><td><div class="table-actions"><?php if ($canEdit): ?><a href="<?= View::escape($baseUrl) ?>admin/engagement/impact/<?= (int) $impact['id'] ?>/edit">Edit</a><?php $action=$impact['status']==='published'?'archive':($impact['status']==='archived'?'restore':'publish'); if ($action!=='publish'||$canPublish): ?><form method="post" action="<?= View::escape($baseUrl) ?>admin/engagement/impact/<?= (int) $impact['id'] ?>/workflow"><input type="hidden" name="_token" value="<?= View::escape($csrfToken) ?>"><button class="link-button" name="action" value="<?= $action ?>"><?= ucfirst($action) ?></button></form><?php endif; endif; ?></div></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
