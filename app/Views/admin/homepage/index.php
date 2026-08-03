<?php

declare(strict_types=1);

use FastWebsite\Core\View;
?>
<section class="admin-page-heading">
    <div>
        <p class="eyebrow">Homepage management</p>
        <h1>Homepage hero</h1>
        <p>Change the faculty image, slide text, call to action, schedule, and display order.</p>
    </div>
    <a class="button button-primary" href="<?= View::escape($baseUrl) ?>admin/homepage/create">Add hero slide</a>
</section>

<section class="admin-card" id="sections">
    <div class="admin-page-heading compact-heading">
        <div><h2>Homepage sections</h2><p>Control section copy, visibility and order. Lower order numbers appear first.</p></div>
    </div>
    <div class="homepage-admin-grid">
        <?php foreach ($sections as $section): ?>
            <form class="content-form homepage-setting-card" method="post" action="<?= View::escape($baseUrl) ?>admin/homepage/sections/<?= View::escape($section['section_key']) ?>">
                <input type="hidden" name="_token" value="<?= View::escape($csrfToken) ?>">
                <h3><?= View::escape($section['label']) ?></h3>
                <div class="form-field"><label>Heading</label><input name="heading" maxlength="255" value="<?= View::escape($section['heading']) ?>"></div>
                <div class="form-field"><label>Introduction</label><textarea name="introduction" rows="3"><?= View::escape($section['introduction']) ?></textarea></div>
                <div class="form-grid">
                    <div class="form-field"><label>Order</label><input name="display_order" type="number" min="0" max="32767" value="<?= (int) $section['display_order'] ?>"></div>
                    <div class="form-field"><label>Visibility</label><select name="is_enabled"><option value="1" <?= (int) $section['is_enabled'] === 1 ? 'selected' : '' ?>>Enabled</option><option value="0" <?= (int) $section['is_enabled'] === 0 ? 'selected' : '' ?>>Hidden</option></select></div>
                </div>
                <button class="button button-secondary" type="submit">Save section</button>
            </form>
        <?php endforeach; ?>
    </div>
</section>

<section class="admin-card" id="quick-links">
    <div class="admin-page-heading compact-heading"><div><h2>Homepage quick links</h2><p>Edit the destinations shown directly below the hero.</p></div></div>
    <div class="homepage-admin-grid">
        <?php foreach ($quickLinks as $link): ?>
            <form class="content-form homepage-setting-card" method="post" action="<?= View::escape($baseUrl) ?>admin/homepage/quick-links/<?= (int) $link['id'] ?>">
                <input type="hidden" name="_token" value="<?= View::escape($csrfToken) ?>">
                <div class="form-field"><label>Label</label><input name="label" required maxlength="120" value="<?= View::escape($link['label']) ?>"></div>
                <div class="form-field"><label>Description</label><input name="description" maxlength="255" value="<?= View::escape($link['description']) ?>"></div>
                <div class="form-field"><label>URL</label><input name="link_url" required maxlength="500" value="<?= View::escape($link['link_url']) ?>"></div>
                <div class="form-grid">
                    <div class="form-field"><label>Order</label><input name="display_order" type="number" min="0" max="32767" value="<?= (int) $link['display_order'] ?>"></div>
                    <div class="form-field"><label>Visibility</label><select name="is_active"><option value="1" <?= (int) $link['is_active'] === 1 ? 'selected' : '' ?>>Visible</option><option value="0" <?= (int) $link['is_active'] === 0 ? 'selected' : '' ?>>Hidden</option></select></div>
                </div>
                <button class="button button-secondary" type="submit">Save quick link</button>
            </form>
        <?php endforeach; ?>
    </div>
</section>

<section class="admin-card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Slide</th><th>Image</th><th>Schedule</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php if ($slides === []): ?>
                    <tr><td colspan="5">No managed slides yet. The public homepage is using its fallback image.</td></tr>
                <?php endif; ?>
                <?php foreach ($slides as $slide): ?>
                    <tr>
                        <td><strong><?= View::escape($slide['title']) ?></strong><br><small>Order <?= (int) $slide['display_order'] ?></small></td>
                        <td><?= View::escape($slide['image_name']) ?></td>
                        <td><?= View::escape($slide['starts_at'] ?? 'Immediately') ?><br><small>to <?= View::escape($slide['ends_at'] ?? 'No end') ?></small></td>
                        <td><?= (int) $slide['is_active'] === 1 ? 'Active' : 'Inactive' ?></td>
                        <td><a href="<?= View::escape($baseUrl) ?>admin/homepage/<?= (int) $slide['id'] ?>/edit">Edit</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
