<?php

declare(strict_types=1);

use FastWebsite\Core\View;

$value = static fn (string $field): string => (string) ($values[$field] ?? '');
$editing = is_int($staffId);
$published = $editing && $currentStatus === 'published';
$action = $editing ? $baseUrl . 'admin/staff/' . $staffId : $baseUrl . 'admin/staff';
?>
<section class="admin-page-heading">
    <div><p class="eyebrow"><?= $published ? 'Live profile' : ($editing ? 'Staff revision' : 'New staff draft') ?></p><h1><?= $editing ? 'Edit staff profile' : 'Create staff profile' ?></h1><p><?= $published ? 'This profile remains public while you edit. Saving applies the changes immediately.' : 'Profile, appointment, and image data are stored once and reused for HOD and Dean displays.' ?></p></div>
    <a class="button button-secondary" href="<?= View::escape($baseUrl) ?>admin/staff<?= $editing ? '/' . $staffId : '' ?>">Cancel</a>
</section>
<?php if ($errors !== []): ?>
    <div class="form-alert" role="alert"><strong>Please correct the following:</strong><ul><?php foreach ($errors as $error): ?><li><?= View::escape($error) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<?php if ($published): ?>
    <div class="success-alert live-edit-notice" role="status">
        <strong>Editing published content.</strong>
        The current profile stays online until you select “Save published changes”.
    </div>
<?php endif; ?>
<form class="content-form" method="post" enctype="multipart/form-data" action="<?= View::escape($action) ?>">
    <input type="hidden" name="_token" value="<?= View::escape($csrfToken) ?>">
    <input type="hidden" name="MAX_FILE_SIZE" value="20971520">
    <input type="hidden" name="faculty_id" value="<?= View::escape($value('faculty_id')) ?>">
    <section class="form-section">
        <div class="form-section-heading"><h2>Identity</h2><p>Public name and directory classification.</p></div>
        <div class="form-grid">
            <div class="form-field"><label for="honorific_title">Title</label><input id="honorific_title" name="honorific_title" maxlength="30" value="<?= View::escape($value('honorific_title')) ?>" placeholder="Dr, Prof"></div>
            <div class="form-field"><label for="staff_number">Staff number</label><input id="staff_number" name="staff_number" maxlength="80" value="<?= View::escape($value('staff_number')) ?>"></div>
            <div class="form-field"><label for="first_name">First name *</label><input id="first_name" name="first_name" maxlength="100" required value="<?= View::escape($value('first_name')) ?>"></div>
            <div class="form-field"><label for="middle_name">Middle name</label><input id="middle_name" name="middle_name" maxlength="100" value="<?= View::escape($value('middle_name')) ?>"></div>
            <div class="form-field"><label for="last_name">Last name *</label><input id="last_name" name="last_name" maxlength="100" required value="<?= View::escape($value('last_name')) ?>"></div>
            <div class="form-field"><label for="post_nominals">Post-nominals</label><input id="post_nominals" name="post_nominals" maxlength="120" value="<?= View::escape($value('post_nominals')) ?>" placeholder="PhD, MSc"></div>
            <div class="form-field"><label for="staff_category">Category *</label><select id="staff_category" name="staff_category" required><?php foreach (['academic','administrative','technical','support','research','visiting','emeritus','other'] as $category): ?><option value="<?= $category ?>" <?= $value('staff_category') === $category ? 'selected' : '' ?>><?= ucfirst($category) ?></option><?php endforeach; ?></select></div>
            <div class="form-field"><label for="display_order">Display order</label><input id="display_order" name="display_order" type="number" min="0" max="65535" value="<?= View::escape($value('display_order')) ?>"></div>
            <div class="form-field span-2"><label for="slug">Profile URL</label><input id="slug" name="slug" maxlength="240" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" value="<?= View::escape($value('slug')) ?>"><p class="field-guidance">Leave blank to generate from the name.</p></div>
        </div>
    </section>
    <section class="form-section">
        <div class="form-section-heading"><h2>Appointment</h2><p>This relationship powers HOD and Dean displays.</p></div>
        <div class="form-grid">
            <div class="form-field"><label for="department_id">Primary department</label><select id="department_id" name="department_id"><option value="">Faculty-wide</option><?php foreach ($departments as $department): ?><option value="<?= (int) $department['id'] ?>" <?= $value('department_id') === (string) $department['id'] ? 'selected' : '' ?>><?= View::escape($department['name']) ?></option><?php endforeach; ?></select></div>
            <div class="form-field"><label for="position_id">Current position</label><select id="position_id" name="position_id"><option value="">No position</option><?php foreach ($positions as $position): ?><option value="<?= (int) $position['id'] ?>" <?= $value('position_id') === (string) $position['id'] ? 'selected' : '' ?>><?= View::escape($position['name']) ?></option><?php endforeach; ?></select></div>
            <div class="form-field span-2"><label for="title_override">Public title override</label><input id="title_override" name="title_override" maxlength="150" value="<?= View::escape($value('title_override')) ?>"></div>
        </div>
    </section>
    <section class="form-section">
        <div class="form-section-heading"><h2>Profile photograph</h2><p>Reuse an existing image or securely upload a new one.</p></div>
        <div class="form-grid">
            <div class="form-field span-2"><label for="profile_media_id">Existing image</label><select id="profile_media_id" name="profile_media_id"><option value="">No image</option><?php foreach ($images as $image): ?><option value="<?= (int) $image['id'] ?>" <?= $value('profile_media_id') === (string) $image['id'] ? 'selected' : '' ?>><?= View::escape($image['original_name']) ?></option><?php endforeach; ?></select></div>
            <div class="form-field span-2 upload-field"><label for="profile_image">Upload profile image</label><input id="profile_image" name="profile_image" type="file" accept="image/jpeg,image/png,image/webp,image/gif"><p class="field-guidance">JPEG, PNG, WebP, or GIF; maximum 20 MB.</p></div>
            <div class="form-field span-2"><label for="profile_alt_text">Image description</label><input id="profile_alt_text" name="profile_alt_text" maxlength="255" value="<?= View::escape($value('profile_alt_text')) ?>"><p class="field-guidance">Required for a new upload.</p></div>
        </div>
    </section>
    <section class="form-section">
        <div class="form-section-heading"><h2>Biography and expertise</h2><p>Short biography is required before publication.</p></div>
        <div class="form-grid">
            <?php foreach (['short_biography'=>'Short biography','biography'=>'Full biography','research_summary'=>'Research summary','teaching_summary'=>'Teaching summary','supervision_interests'=>'Supervision interests'] as $field => $label): ?><div class="form-field span-2"><label for="<?= $field ?>"><?= $label ?></label><textarea id="<?= $field ?>" name="<?= $field ?>" rows="<?= $field === 'short_biography' ? 4 : 7 ?>"><?= View::escape($value($field)) ?></textarea></div><?php endforeach; ?>
            <div class="form-field"><label><input type="checkbox" name="supervision_available" value="1" <?= $value('supervision_available') === '1' ? 'checked' : '' ?>> Available for supervision</label></div>
        </div>
    </section>
    <section class="form-section">
        <div class="form-section-heading"><h2>Contact and office</h2></div>
        <div class="form-grid">
            <div class="form-field"><label for="institutional_email">Institutional email</label><input id="institutional_email" name="institutional_email" type="email" value="<?= View::escape($value('institutional_email')) ?>"></div>
            <div class="form-field"><label for="alternative_email">Alternative email</label><input id="alternative_email" name="alternative_email" type="email" value="<?= View::escape($value('alternative_email')) ?>"></div>
            <div class="form-field"><label for="public_phone">Public phone</label><input id="public_phone" name="public_phone" maxlength="50" value="<?= View::escape($value('public_phone')) ?>"></div>
            <div class="form-field"><label for="office_location_id">Office location</label><select id="office_location_id" name="office_location_id"><option value="">Not linked</option><?php foreach ($locations as $location): ?><option value="<?= (int) $location['id'] ?>" <?= $value('office_location_id') === (string) $location['id'] ? 'selected' : '' ?>><?= View::escape(implode(' — ', array_filter([$location['campus'],$location['building'],$location['room']]))) ?></option><?php endforeach; ?></select></div>
            <div class="form-field span-2"><label for="consultation_hours">Consultation hours</label><input id="consultation_hours" name="consultation_hours" maxlength="255" value="<?= View::escape($value('consultation_hours')) ?>"></div>
        </div>
    </section>
    <?php if ($editing): ?><section class="form-section"><div class="form-field"><label for="revision_note">Revision note</label><textarea id="revision_note" name="revision_note" rows="3"><?= View::escape($revisionNote) ?></textarea></div></section><?php endif; ?>
    <div class="form-actions">
        <?php if ($published): ?>
            <button class="button button-primary publish-button" type="submit" name="submit_action" value="save_live">Save published changes</button>
        <?php else: ?>
            <button class="button <?= $canPublishDirectly ? 'button-secondary' : 'button-primary' ?>" type="submit" name="submit_action" value="save"><?= $editing ? 'Save revision' : 'Create draft' ?></button>
            <?php if ($canPublishDirectly): ?><button class="button button-primary publish-button" type="submit" name="submit_action" value="publish"><?= $editing ? 'Save and publish' : 'Create and publish' ?></button><?php endif; ?>
        <?php endif; ?>
        <a class="button button-secondary" href="<?= View::escape($baseUrl) ?>admin/staff<?= $editing ? '/' . $staffId : '' ?>">Cancel</a>
    </div>
</form>
