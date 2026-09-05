<?php

declare(strict_types=1);

use FastWebsite\Core\View;

$value = static fn (string $field): string => (string) ($values[$field] ?? '');
$editing = is_int($itemId);
$pageSlug = $value('slug');
$sectionTypes = [
    'rich_text' => 'Rich text',
    'image_text' => 'Image with text',
    'cards' => 'Feature card',
    'statistics' => 'Statistic',
    'call_to_action' => 'Call to action',
    'quote' => 'Quote',
    'gallery' => 'Gallery image',
    'video' => 'Video',
    'custom' => 'Custom',
];
$defaultSectionType = match ($pageSlug) {
    'history-of-the-faculty' => 'gallery',
    'vision-and-mission', 'core-values' => 'cards',
    default => 'rich_text',
};
$pageGuidance = match ($pageSlug) {
    'history-of-the-faculty' => 'Add one Gallery image section for each photograph. The public page automatically combines the first four visible images into a professional history composition.',
    'deans-message' => 'Edit the message here. The Dean’s name and photograph are fetched automatically from the published staff member assigned the current Dean position.',
    'vision-and-mission' => 'Keep one card headed Vision and another headed Mission. Their order, wording and visibility can be changed below.',
    'core-values' => 'Add each core value as a Feature card so values can be reordered and presented consistently.',
    'industrial-training', 'community-outreach', 'partnerships', 'engineering-education',
    'student-life', 'professional-bodies', 'student-mentorship-programme',
    'faculty-mentorship-programme', 'meet-our-mentors' => 'This page uses the FAST visual feature layout. Set a Masthead image above, keep introductory paragraphs concise, and add Gallery image sections for the animated visual rail. Lists in the body are automatically presented as visual cards.',
    default => 'Use ordered content sections to build this page. Every saved section can be edited, reordered, hidden or removed.',
};
?>

<section class="admin-page-heading">
    <div><p class="eyebrow">Page builder</p><h1><?= $editing ? 'Edit page' : 'Create page' ?></h1><p>Manage page details and reusable content sections from one screen.</p></div>
    <a class="button button-secondary" href="<?= View::escape($baseUrl) ?>admin/pages">Back</a>
</section>

<?php if ($errors !== []): ?><div class="form-alert"><ul><?php foreach ($errors as $error): ?><li><?= View::escape($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<form class="content-form" method="post" action="<?= View::escape($baseUrl) ?>admin/pages<?= $editing ? '/' . $itemId : '' ?>">
    <input type="hidden" name="_token" value="<?= View::escape($csrfToken) ?>">
    <section class="form-section">
        <div class="form-grid">
            <div class="form-field span-2"><label for="page-title">Title *</label><input id="page-title" name="title" required value="<?= View::escape($value('title')) ?>"></div>
            <div class="form-field"><label for="page-slug">Public URL</label><input id="page-slug" name="slug" value="<?= View::escape($value('slug')) ?>"></div>
            <div class="form-field"><label for="parent-page">Parent page</label><select id="parent-page" name="parent_page_id"><option value="">Top level</option><?php foreach ($pages as $page): if ((int) $page['id'] === $itemId) continue; ?><option value="<?= (int) $page['id'] ?>" <?= $value('parent_page_id') === (string) $page['id'] ? 'selected' : '' ?>><?= View::escape($page['title']) ?></option><?php endforeach; ?></select></div>
            <div class="form-field"><label for="page-template">Template</label><select id="page-template" name="page_template"><option value="standard">Standard</option><option value="landing" <?= $value('page_template') === 'landing' ? 'selected' : '' ?>>Landing</option></select></div>
            <div class="form-field"><label for="page-order">Display order</label><input id="page-order" type="number" min="0" name="display_order" value="<?= View::escape($value('display_order') ?: '0') ?>"></div>
            <label><input type="checkbox" name="show_in_navigation" value="1" <?= $value('show_in_navigation') === '1' ? 'checked' : '' ?>> Show in navigation</label>
            <div class="form-field span-2"><label for="page-description">Page introduction and search description</label><textarea id="page-description" name="meta_description" rows="3"><?= View::escape($value('meta_description')) ?></textarea></div>
            <div class="form-field span-2"><label for="page-hero-image">Masthead image</label><select id="page-hero-image" name="hero_media_id" data-media-upload="<?= View::escape($baseUrl) ?>admin/media"><option value="">Use the default FAST building image</option><?php foreach ($images as $image): ?><option value="<?= (int) $image['id'] ?>" <?= $value('hero_media_id') === (string) $image['id'] ? 'selected' : '' ?>><?= View::escape($image['original_name']) ?></option><?php endforeach; ?></select><small>This image appears beside the page title.</small><?php if ($value('hero_path') !== ''): ?><small>Current image: <?= View::escape(basename($value('hero_path'))) ?></small><?php endif; ?></div>
        </div>
    </section>
    <div class="form-actions"><button class="button button-secondary" name="submit_action" value="save">Save</button><button class="button button-primary" name="submit_action" value="publish">Save and publish</button></div>
</form>

<?php if ($editing): ?>
    <section class="admin-card page-section-manager" id="sections">
        <div class="page-section-manager-heading">
            <div><p class="eyebrow">Easy page editing</p><h2>Page sections</h2><p><?= View::escape($pageGuidance) ?></p></div>
            <a class="button button-secondary" href="<?= View::escape($baseUrl) ?>admin/media">Open media library</a>
        </div>

        <?php if ($sections === []): ?><div class="admin-empty"><p>No sections yet. Use the form below to add the first one.</p></div><?php endif; ?>

        <div class="page-section-editor-list">
            <?php foreach ($sections as $section): ?>
                <details class="page-section-editor" id="section-<?= (int) $section['id'] ?>">
                    <summary><span><b><?= str_pad((string) ((int) $section['display_order']), 2, '0', STR_PAD_LEFT) ?></b><strong><?= View::escape($section['heading'] ?: ($sectionTypes[$section['section_type']] ?? 'Untitled section')) ?></strong></span><small><?= View::escape($sectionTypes[$section['section_type']] ?? $section['section_type']) ?> · <?= (int) $section['is_visible'] === 1 ? 'Visible' : 'Hidden' ?></small></summary>
                    <?php $sectionExtraIds = array_column($section['extra_photos'] ?? [], 'id'); ?>
                    <form class="content-form page-section-edit-form" method="post" action="<?= View::escape($baseUrl) ?>admin/pages/<?= $itemId ?>/sections/<?= (int) $section['id'] ?>">
                        <input type="hidden" name="_token" value="<?= View::escape($csrfToken) ?>">
                        <div class="form-grid">
                            <div class="form-field"><label>Section type</label><select name="section_type"><?php foreach ($sectionTypes as $type => $label): ?><option value="<?= $type ?>" <?= $section['section_type'] === $type ? 'selected' : '' ?>><?= View::escape($label) ?></option><?php endforeach; ?></select></div>
                            <div class="form-field"><label>Display order</label><input type="number" name="display_order" min="0" value="<?= (int) $section['display_order'] ?>"></div>
                            <div class="form-field span-2"><label>Heading</label><input name="heading" value="<?= View::escape($section['heading']) ?>"></div>
                            <div class="form-field span-2"><label>Short label or subheading</label><input name="subheading" value="<?= View::escape($section['subheading']) ?>"></div>
                            <div class="form-field span-2"><label>Body</label><textarea name="body" rows="8"><?= View::escape($section['body']) ?></textarea></div>
                            <div class="form-field span-2"><label>Image</label><select name="media_id" data-media-upload="<?= View::escape($baseUrl) ?>admin/media"><option value="">No image</option><?php foreach ($images as $image): ?><option value="<?= (int) $image['id'] ?>" <?= (string) $section['media_id'] === (string) $image['id'] ? 'selected' : '' ?>><?= View::escape($image['original_name']) ?></option><?php endforeach; ?></select><?php if ($section['media_path']): ?><small>Current image: <?= View::escape(basename((string) $section['media_path'])) ?></small><?php endif; ?></div>
                            <div class="form-field span-2"><label>Additional photos (optional)</label><p class="field-guidance">Fills extra space when this section sits next to a long paragraph — these stack beside the main image automatically, no separate section needed.</p><div class="form-grid"><select name="extra_media_id[]" data-media-upload="<?= View::escape($baseUrl) ?>admin/media"><option value="">No photo</option><?php foreach ($images as $image): ?><option value="<?= (int) $image['id'] ?>" <?= ($sectionExtraIds[0] ?? null) === (int) $image['id'] ? 'selected' : '' ?>><?= View::escape($image['original_name']) ?></option><?php endforeach; ?></select><select name="extra_media_id[]" data-media-upload="<?= View::escape($baseUrl) ?>admin/media"><option value="">No photo</option><?php foreach ($images as $image): ?><option value="<?= (int) $image['id'] ?>" <?= ($sectionExtraIds[1] ?? null) === (int) $image['id'] ? 'selected' : '' ?>><?= View::escape($image['original_name']) ?></option><?php endforeach; ?></select></div></div>
                            <div class="form-field"><label>Button label</label><input name="button_label" value="<?= View::escape($section['button_label']) ?>"></div>
                            <div class="form-field"><label>Button URL</label><input name="button_url" value="<?= View::escape($section['button_url']) ?>"></div>
                            <label><input type="checkbox" name="is_visible" value="1" <?= (int) $section['is_visible'] === 1 ? 'checked' : '' ?>> Visible on the public page</label>
                        </div>
                        <div class="form-actions"><button class="button button-primary">Update section</button></div>
                    </form>
                    <form class="page-section-remove-form" method="post" action="<?= View::escape($baseUrl) ?>admin/pages/<?= $itemId ?>/sections/<?= (int) $section['id'] ?>/remove">
                        <input type="hidden" name="_token" value="<?= View::escape($csrfToken) ?>"><button class="link-button">Remove section</button>
                    </form>
                </details>
            <?php endforeach; ?>
        </div>

        <details class="page-section-add" <?= $sections === [] ? 'open' : '' ?>>
            <summary>Add another section</summary>
            <form class="content-form" method="post" action="<?= View::escape($baseUrl) ?>admin/pages/<?= $itemId ?>/sections">
                <input type="hidden" name="_token" value="<?= View::escape($csrfToken) ?>">
                <div class="form-grid">
                    <div class="form-field"><label>Section type</label><select name="section_type"><?php foreach ($sectionTypes as $type => $label): ?><option value="<?= $type ?>" <?= $type === $defaultSectionType ? 'selected' : '' ?>><?= View::escape($label) ?></option><?php endforeach; ?></select></div>
                    <div class="form-field"><label>Display order</label><input type="number" name="display_order" min="0" value="<?= (count($sections) + 1) * 10 ?>"></div>
                    <div class="form-field span-2"><label>Heading</label><input name="heading"></div>
                    <div class="form-field span-2"><label>Short label or subheading</label><input name="subheading"></div>
                    <div class="form-field span-2"><label>Body</label><textarea name="body" rows="7"></textarea></div>
                    <div class="form-field span-2"><label>Image</label><select name="media_id" data-media-upload="<?= View::escape($baseUrl) ?>admin/media"><option value="">No image</option><?php foreach ($images as $image): ?><option value="<?= (int) $image['id'] ?>"><?= View::escape($image['original_name']) ?></option><?php endforeach; ?></select><small>For multiple History images, add several Gallery image sections.</small></div>
                    <div class="form-field span-2"><label>Additional photos (optional)</label><p class="field-guidance">Fills extra space when this section sits next to a long paragraph — these stack beside the main image automatically, no separate section needed.</p><div class="form-grid"><select name="extra_media_id[]" data-media-upload="<?= View::escape($baseUrl) ?>admin/media"><option value="">No photo</option><?php foreach ($images as $image): ?><option value="<?= (int) $image['id'] ?>"><?= View::escape($image['original_name']) ?></option><?php endforeach; ?></select><select name="extra_media_id[]" data-media-upload="<?= View::escape($baseUrl) ?>admin/media"><option value="">No photo</option><?php foreach ($images as $image): ?><option value="<?= (int) $image['id'] ?>"><?= View::escape($image['original_name']) ?></option><?php endforeach; ?></select></div></div>
                    <div class="form-field"><label>Button label</label><input name="button_label"></div>
                    <div class="form-field"><label>Button URL</label><input name="button_url"></div>
                    <label><input type="checkbox" name="is_visible" value="1" checked> Visible on the public page</label>
                </div>
                <button class="button button-primary">Add section</button>
            </form>
        </details>
    </section>
<?php endif; ?>
