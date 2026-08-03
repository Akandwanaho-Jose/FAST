<?php
declare(strict_types=1);
use FastWebsite\Core\View;
$name = trim(implode(' ', array_filter([$profile['honorific_title'],$profile['first_name'],$profile['middle_name'],$profile['last_name']])));
$profilePath = is_string($profile['profile_path']) ? str_replace('\\', '/', $profile['profile_path']) : '';
$profileUrl = $profilePath !== '' && preg_match('#^(?:https?:)?//#i', $profilePath) !== 1
    ? $baseUrl . ltrim(preg_replace('#^public/#', '', $profilePath) ?? $profilePath, '/')
    : null;
?>
<section class="admin-page-heading">
    <div><p class="eyebrow"><?= View::escape($profile['position_title'] ?? $profile['staff_category']) ?></p><h1><?= View::escape($name) ?></h1><span class="status-badge status-<?= View::escape($profile['status']) ?>"><?= View::escape(ucfirst(str_replace('_',' ',$profile['status']))) ?></span></div>
    <div class="heading-actions"><a class="button button-secondary" href="<?= View::escape($baseUrl) ?>admin/staff">All staff</a><?php if ($canEdit): ?><a class="button <?= $canQuickPublish ? 'button-secondary' : 'button-primary' ?>" href="<?= View::escape($baseUrl) ?>admin/staff/<?= (int) $profile['id'] ?>/edit">Edit</a><?php endif; ?><?php if ($canQuickPublish): ?><form class="quick-publish-form" method="post" action="<?= View::escape($baseUrl) ?>admin/staff/<?= (int) $profile['id'] ?>/workflow"><input type="hidden" name="_token" value="<?= View::escape($csrfToken) ?>"><input type="hidden" name="action" value="publish_now"><button class="button button-primary publish-button" type="submit">Publish now</button></form><?php endif; ?></div>
</section>
<?php if (is_string($success) && $success !== ''): ?><div class="success-alert" role="status"><?= View::escape($success) ?></div><?php endif; ?>
<?php if ($needsBiographyToPublish): ?><div class="form-alert publish-guidance" role="status"><strong>Ready to publish?</strong> Add a short biography while editing, then use “Save and publish”.</div><?php endif; ?>
<div class="record-grid">
    <div class="record-main">
        <?php if ($profileUrl !== null): ?><section class="admin-panel record-hero"><img src="<?= View::escape($profileUrl) ?>" alt="<?= View::escape($profile['profile_alt_text'] ?: 'Portrait of '.$name) ?>"></section><?php endif; ?>
        <?php foreach (['Short biography'=>$profile['short_biography'],'Biography'=>$profile['biography'],'Research'=>$profile['research_summary'],'Teaching'=>$profile['teaching_summary'],'Supervision interests'=>$profile['supervision_interests']] as $heading=>$text): ?><?php if (is_string($text) && trim($text)!==''): ?><section class="admin-panel record-section"><h2><?= $heading ?></h2><div class="prose"><?= nl2br(View::escape($text)) ?></div></section><?php endif; ?><?php endforeach; ?>
    </div>
    <aside class="record-sidebar">
        <section class="admin-panel"><h2>Profile details</h2><dl class="detail-list"><div><dt>Department</dt><dd><?= View::escape($profile['department_name'] ?? 'Faculty-wide') ?></dd></div><div><dt>Position</dt><dd><?= View::escape($profile['position_title'] ?? 'Not assigned') ?></dd></div><div><dt>Email</dt><dd><?= View::escape($profile['institutional_email'] ?? 'Not set') ?></dd></div><div><dt>Public URL</dt><dd>/staff/<?= View::escape($profile['slug']) ?></dd></div></dl></section>
        <?php if ($actions !== []): ?><section class="admin-panel"><h2>Workflow action</h2><form class="workflow-form" method="post" action="<?= View::escape($baseUrl) ?>admin/staff/<?= (int) $profile['id'] ?>/workflow"><input type="hidden" name="_token" value="<?= View::escape($csrfToken) ?>"><div class="form-field"><label for="staff-comment">Comment</label><textarea id="staff-comment" name="comment" rows="3"></textarea></div><div class="workflow-actions"><?php foreach ($actions as $action): ?><button class="button <?= $action['action']==='published' ? 'button-primary':'button-secondary' ?>" type="submit" name="action" value="<?= View::escape($action['action']) ?>"><?= View::escape($action['label']) ?></button><?php endforeach; ?></div></form></section><?php endif; ?>
        <section class="admin-panel"><h2>Workflow history</h2><?php if ($history===[]): ?><p class="admin-empty compact">No actions yet.</p><?php else: ?><ol class="history-list"><?php foreach ($history as $entry): ?><li><strong><?= View::escape(ucfirst(str_replace('_',' ',$entry['action']))) ?></strong><span><?= View::escape($entry['actor_name'] ?? 'Former user') ?></span></li><?php endforeach; ?></ol><?php endif; ?></section>
    </aside>
</div>
