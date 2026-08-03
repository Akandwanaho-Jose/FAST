<?php

declare(strict_types=1);

use FastWebsite\Core\View;

$fields = [
    'Overview' => $faculty['overview'],
    'History' => $faculty['history'],
    'Vision' => $faculty['vision'],
    'Mission' => $faculty['mission'],
    'Core values' => $faculty['core_values'],
];
$location = array_filter([
    $faculty['campus'],
    $faculty['building'],
    $faculty['floor'],
    $faculty['room'],
], static fn (mixed $value): bool => is_string($value) && $value !== '');
?>
<section class="admin-page-heading">
    <div>
        <p class="eyebrow">Authoritative faculty record</p>
        <h1><?= View::escape($faculty['name']) ?></h1>
        <p>This Phase 4 view is read-only; no faculty-specific edit permission exists in the current schema.</p>
    </div>
    <a class="button button-secondary" href="<?= View::escape($baseUrl) ?>admin/departments">Departments</a>
</section>

<div class="record-grid">
    <div class="record-main">
        <?php foreach ($fields as $heading => $value): ?>
            <section class="admin-panel record-section">
                <h2><?= View::escape($heading) ?></h2>
                <?php if (is_string($value) && trim($value) !== ''): ?>
                    <div class="prose"><?= nl2br(View::escape($value)) ?></div>
                <?php else: ?>
                    <p class="admin-empty compact">Not yet provided.</p>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>
    <aside class="record-sidebar">
        <section class="admin-panel">
            <h2>Settings</h2>
            <dl class="detail-list">
                <div><dt>Short name</dt><dd><?= View::escape($faculty['short_name']) ?></dd></div>
                <div><dt>Slug</dt><dd><?= View::escape($faculty['slug']) ?></dd></div>
                <div><dt>Status</dt><dd><?= View::escape(ucfirst($faculty['status'])) ?></dd></div>
                <div><dt>Email</dt><dd><?= View::escape($faculty['email'] ?? 'Not set') ?></dd></div>
                <div><dt>Phone</dt><dd><?= View::escape($faculty['phone'] ?? 'Not set') ?></dd></div>
                <div><dt>Postal address</dt><dd><?= View::escape($faculty['postal_address'] ?? 'Not set') ?></dd></div>
                <div><dt>Website</dt><dd><?= View::escape($faculty['website_url'] ?? 'Not set') ?></dd></div>
                <div><dt>Location</dt><dd><?= View::escape($location === [] ? 'Not linked' : implode(', ', $location)) ?></dd></div>
                <div><dt>Logo</dt><dd><?= $faculty['logo_media_id'] === null ? 'Not linked' : 'Media #' . (int) $faculty['logo_media_id'] ?></dd></div>
            </dl>
        </section>
    </aside>
</div>
