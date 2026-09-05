<?php

declare(strict_types=1);

use FastWebsite\Core\View;

$groupLabels = [
    'identity' => 'Institutional identity and external services',
    'about' => 'About FAST pages',
    'navigation' => 'Navigation',
    'home' => 'Homepage',
    'departments' => 'Departments page',
    'staff' => 'Staff pages',
    'programmes' => 'Programme pages',
    'research' => 'Research pages',
    'innovations' => 'Innovation pages',
    'facilities' => 'Facilities page',
    'engagement' => 'Engagement pages',
    'news' => 'News pages',
    'events' => 'Event pages',
    'documents' => 'Document pages',
];
$label = static function (string $key): string {
    $parts = explode('.', $key, 2);
    return ucfirst(str_replace('_', ' ', $parts[1] ?? $parts[0]));
};
?>
<section class="admin-page-heading">
    <div><p class="eyebrow">Public website</p><h1>Site content</h1><p>Edit public wording, calls to action, institutional details and external service destinations.</p></div>
    <a class="button button-secondary" href="<?= View::escape($baseUrl) ?>">View public website</a>
</section>

<form class="content-form" method="post" action="<?= View::escape($baseUrl) ?>admin/site-content">
    <input type="hidden" name="_token" value="<?= View::escape($csrfToken) ?>">
    <?php foreach ($settingGroups as $group => $settings): ?>
        <section class="admin-card site-setting-group">
            <div class="compact-heading"><h2><?= View::escape($groupLabels[$group] ?? ucfirst($group)) ?></h2><p>Changes become visible on the public website after saving.</p></div>
            <div class="homepage-admin-grid">
                <?php foreach ($settings as $setting): ?>
                    <div class="form-field homepage-setting-card">
                        <label for="setting-<?= (int) $setting['id'] ?>"><?= View::escape($label((string) $setting['setting_key'])) ?></label>
                        <?php $isLong = strlen((string) $setting['setting_value']) > 90 || preg_match('/(?:intro|description|empty)/', (string) $setting['setting_key']) === 1; ?>
                        <?php if ($isLong): ?>
                            <textarea id="setting-<?= (int) $setting['id'] ?>" name="setting_<?= (int) $setting['id'] ?>" rows="4"><?= View::escape($setting['setting_value']) ?></textarea>
                        <?php else: ?>
                            <input id="setting-<?= (int) $setting['id'] ?>" name="setting_<?= (int) $setting['id'] ?>" type="<?= $setting['value_type'] === 'url' ? 'url' : 'text' ?>" value="<?= View::escape($setting['setting_value']) ?>">
                        <?php endif; ?>
                        <small><?= View::escape($setting['setting_key']) ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
    <div class="admin-form-actions"><button class="button button-primary" type="submit">Save site content</button></div>
</form>
