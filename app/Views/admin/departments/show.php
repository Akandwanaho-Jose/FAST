<?php

declare(strict_types=1);

use FastWebsite\Core\View;

$statusLabel = ucfirst(str_replace('_', ' ', (string) $department['status']));
$locationParts = array_filter([
    $department['campus'],
    $department['building'],
    $department['floor'],
    $department['room'],
], static fn (mixed $value): bool => is_string($value) && $value !== '');
$contentSections = [
    'Overview' => $department['overview'],
    'History' => $department['history'],
    'Vision' => $department['vision'],
    'Mission' => $department['mission'],
    'Strategic direction' => $department['strategic_direction'],
];
$heroPath = is_string($department['hero_path'])
    ? str_replace('\\', '/', $department['hero_path'])
    : '';
$heroUrl = $heroPath !== ''
    && preg_match('#^(?:https?:)?//#i', $heroPath) !== 1
        ? $baseUrl . ltrim(
            preg_replace('#^public/#', '', $heroPath) ?? $heroPath,
            '/'
        )
        : null;
?>
<section class="admin-page-heading">
    <div>
        <p class="eyebrow"><?= View::escape($department['faculty_name']) ?></p>
        <h1><?= View::escape($department['name']) ?></h1>
        <p>
            <span class="status-badge status-<?= View::escape($department['status']) ?>">
                <?= View::escape($statusLabel) ?>
            </span>
            <span class="record-slug">/departments/<?= View::escape($department['slug']) ?></span>
        </p>
    </div>
    <div class="heading-actions">
        <a class="button button-secondary" href="<?= View::escape($baseUrl) ?>admin/departments">All departments</a>
        <?php if ($canEdit): ?>
            <a class="button button-primary" href="<?= View::escape($baseUrl) ?>admin/departments/<?= (int) $department['id'] ?>/edit">Edit</a>
        <?php endif; ?>
        <?php if ($canQuickPublish): ?>
            <form class="quick-publish-form" method="post" action="<?= View::escape($baseUrl) ?>admin/departments/<?= (int) $department['id'] ?>/workflow">
                <input type="hidden" name="_token" value="<?= View::escape($csrfToken) ?>">
                <input type="hidden" name="action" value="publish_now">
                <button class="button button-primary publish-button" type="submit">Publish now</button>
            </form>
        <?php endif; ?>
    </div>
</section>

<?php if (is_string($success) && $success !== ''): ?>
    <div class="success-alert" role="status"><?= View::escape($success) ?></div>
<?php endif; ?>

<?php if ($needsOverviewToPublish): ?>
    <div class="form-alert publish-guidance" role="status">
        <strong>Ready to publish?</strong>
        Add a verified overview while editing. You can then use “Save and publish” to complete the workflow in one step.
    </div>
<?php endif; ?>

<div class="record-grid">
    <div class="record-main">
        <?php if ($heroUrl !== null): ?>
            <section class="admin-panel record-hero">
                <img src="<?= View::escape($heroUrl) ?>" alt="<?= View::escape($department['hero_alt_text'] ?? '') ?>">
            </section>
        <?php endif; ?>
        <?php foreach ($contentSections as $heading => $value): ?>
            <?php if (is_string($value) && trim($value) !== ''): ?>
                <section class="admin-panel record-section">
                    <h2><?= View::escape($heading) ?></h2>
                    <div class="prose"><?= nl2br(View::escape($value)) ?></div>
                </section>
            <?php endif; ?>
        <?php endforeach; ?>

        <?php if (array_filter($contentSections, static fn (mixed $value): bool => is_string($value) && trim($value) !== '') === []): ?>
            <div class="admin-empty">This starter record does not yet have public narrative content.</div>
        <?php endif; ?>
    </div>

    <aside class="record-sidebar">
        <section class="admin-panel">
            <h2>Record details</h2>
            <dl class="detail-list">
                <div><dt>Short name</dt><dd><?= View::escape($department['short_name'] ?? 'Not set') ?></dd></div>
                <div><dt>Email</dt><dd><?= View::escape($department['email'] ?? 'Not set') ?></dd></div>
                <div><dt>Phone</dt><dd><?= View::escape($department['phone'] ?? 'Not set') ?></dd></div>
                <div><dt>Location</dt><dd><?= View::escape($locationParts === [] ? 'Not linked' : implode(', ', $locationParts)) ?></dd></div>
                <div>
                    <dt>Hero image URL</dt>
                    <dd>
                        <?= $heroPath === ''
                            ? 'Not linked'
                            : '<code>' . View::escape($heroPath) . '</code>' ?>
                    </dd>
                </div>
                <div><dt>Display order</dt><dd><?= number_format((int) $department['display_order']) ?></dd></div>
                <div><dt>Published</dt><dd><?= View::escape($department['published_at'] ?? 'Not published') ?></dd></div>
            </dl>
        </section>

        <?php if ($workflowActions !== []): ?>
            <section class="admin-panel">
                <h2>Workflow action</h2>
                <form class="workflow-form" method="post" action="<?= View::escape($baseUrl) ?>admin/departments/<?= (int) $department['id'] ?>/workflow">
                    <input type="hidden" name="_token" value="<?= View::escape($csrfToken) ?>">
                    <div class="form-field">
                        <label for="workflow-comment">Comment</label>
                        <textarea id="workflow-comment" name="comment" maxlength="2000" rows="3"></textarea>
                    </div>
                    <div class="workflow-actions">
                        <?php foreach ($workflowActions as $action): ?>
                            <button class="button <?= $action['action'] === 'published' ? 'button-primary' : 'button-secondary' ?>" type="submit" name="action" value="<?= View::escape($action['action']) ?>">
                                <?= View::escape($action['label']) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </form>
            </section>
        <?php endif; ?>

        <section class="admin-panel">
            <h2>Workflow history</h2>
            <?php if ($history === []): ?>
                <p class="admin-empty compact">No workflow actions recorded yet.</p>
            <?php else: ?>
                <ol class="history-list">
                    <?php foreach ($history as $entry): ?>
                        <li>
                            <strong><?= View::escape(ucfirst(str_replace('_', ' ', $entry['action']))) ?></strong>
                            <span><?= View::escape($entry['actor_name'] ?? 'Former user') ?> · <?= View::escape(date('j M Y, H:i', strtotime($entry['created_at']))) ?></span>
                            <?php if (is_string($entry['comment']) && $entry['comment'] !== ''): ?>
                                <p><?= View::escape($entry['comment']) ?></p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>
    </aside>
</div>
