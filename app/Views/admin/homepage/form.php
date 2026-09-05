<?php

declare(strict_types=1);

use FastWebsite\Core\View;

$editing = is_int($itemId);
$value = static fn (string $field): string => (string) ($values[$field] ?? '');
$dateValue = static function (string $field) use ($value): string {
    $raw = $value($field);
    if ($raw === '') {
        return '';
    }
    return str_replace(' ', 'T', mb_substr($raw, 0, 16));
};
?>
<section class="admin-page-heading">
    <div>
        <p class="eyebrow">Homepage management</p>
        <h1><?= $editing ? 'Edit hero slide' : 'Add hero slide' ?></h1>
        <p>Upload a new faculty image or select one already stored in the Media Library.</p>
    </div>
    <a class="button button-secondary" href="<?= View::escape($baseUrl) ?>admin/homepage">Cancel</a>
</section>

<?php if ($errors !== []): ?>
    <div class="form-alert"><ul><?php foreach ($errors as $error): ?><li><?= View::escape($error) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<form class="content-form" method="post" enctype="multipart/form-data" action="<?= View::escape($baseUrl) ?>admin/homepage<?= $editing ? '/' . (int) $itemId : '' ?>">
    <input type="hidden" name="_token" value="<?= View::escape($csrfToken) ?>">
    <input type="hidden" name="MAX_FILE_SIZE" value="20971520">

    <section class="form-section">
        <div class="form-section-heading"><h2>Slide content</h2><p>This text appears over the faculty image.</p></div>
        <div class="form-grid">
            <div class="form-field span-2"><label for="title">Title *</label><input id="title" name="title" required maxlength="255" value="<?= View::escape($value('title')) ?>"></div>
            <div class="form-field span-2"><label for="caption">Caption</label><textarea id="caption" name="caption" rows="4"><?= View::escape($value('caption')) ?></textarea></div>
            <div class="form-field"><label for="button_label">Button label</label><input id="button_label" name="button_label" maxlength="100" value="<?= View::escape($value('button_label')) ?>"></div>
            <div class="form-field"><label for="button_url">Button URL</label><input id="button_url" name="button_url" maxlength="500" value="<?= View::escape($value('button_url')) ?>" placeholder="/programmes or https://..."></div>
        </div>
    </section>

    <section class="form-section">
        <div class="form-section-heading"><h2>Faculty image</h2><p>A new upload automatically becomes the selected desktop image.</p></div>
        <div class="form-grid">
            <div class="form-field span-2"><label for="media_id">Existing desktop image</label><select id="media_id" name="media_id" data-media-upload="<?= View::escape($baseUrl) ?>admin/media"><option value="">Choose an image</option><?php foreach ($images as $image): ?><option value="<?= (int) $image['id'] ?>" <?= $value('media_id') === (string) $image['id'] ? 'selected' : '' ?>><?= View::escape($image['original_name']) ?> (<?= (int) $image['width'] ?>×<?= (int) $image['height'] ?>)</option><?php endforeach; ?></select></div>
            <div class="form-field"><label for="hero_image">Upload replacement image</label><input id="hero_image" name="hero_image" type="file" accept="image/jpeg,image/png,image/webp,image/gif"><p class="field-guidance">Landscape image recommended; maximum 20 MB.</p></div>
            <div class="form-field"><label for="hero_alt_text">New image description</label><input id="hero_alt_text" name="hero_alt_text" maxlength="255" placeholder="Required when uploading a new image"></div>
            <div class="form-field span-2"><label for="mobile_media_id">Optional mobile image</label><select id="mobile_media_id" name="mobile_media_id" data-media-upload="<?= View::escape($baseUrl) ?>admin/media"><option value="">Use desktop image</option><?php foreach ($images as $image): ?><option value="<?= (int) $image['id'] ?>" <?= $value('mobile_media_id') === (string) $image['id'] ? 'selected' : '' ?>><?= View::escape($image['original_name']) ?></option><?php endforeach; ?></select></div>
        </div>
    </section>

    <section class="form-section">
        <div class="form-section-heading"><h2>Presentation and schedule</h2></div>
        <div class="form-grid">
            <div class="form-field"><label for="text_alignment">Text alignment</label><select id="text_alignment" name="text_alignment"><?php foreach (['left' => 'Left', 'centre' => 'Centre', 'right' => 'Right'] as $key => $label): ?><option value="<?= $key ?>" <?= $value('text_alignment') === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
            <div class="form-field"><label for="overlay_strength">Image overlay</label><select id="overlay_strength" name="overlay_strength"><?php foreach ([20 => 'Light', 40 => 'Medium', 60 => 'Strong', 80 => 'Very strong'] as $key => $label): ?><option value="<?= $key ?>" <?= $value('overlay_strength') === (string) $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
            <div class="form-field"><label for="starts_at">Start time</label><input id="starts_at" name="starts_at" type="datetime-local" value="<?= View::escape($dateValue('starts_at')) ?>"></div>
            <div class="form-field"><label for="ends_at">End time</label><input id="ends_at" name="ends_at" type="datetime-local" value="<?= View::escape($dateValue('ends_at')) ?>"></div>
            <div class="form-field"><label for="display_order">Display order</label><input id="display_order" name="display_order" type="number" min="0" max="32767" value="<?= View::escape($value('display_order')) ?>"></div>
            <div class="form-field"><label for="is_active">Visibility</label><select id="is_active" name="is_active"><option value="1" <?= $value('is_active') === '1' ? 'selected' : '' ?>>Active</option><option value="0" <?= $value('is_active') === '0' ? 'selected' : '' ?>>Inactive</option></select></div>
        </div>
    </section>

    <div class="form-actions"><button class="button button-primary" type="submit"><?= $editing ? 'Save homepage slide' : 'Create homepage slide' ?></button></div>
</form>
