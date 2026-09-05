<?php

declare(strict_types=1);

use FastWebsite\Core\View;

$value = static fn (string $field): string => (string) ($profile[$field] ?? '');
$qualificationRows = is_array($profile['qualifications'] ?? null)
    ? array_values($profile['qualifications']) : [];
$linkRows = is_array($profile['links'] ?? null) ? array_values($profile['links']) : [];
$selectedExpertise = array_map('intval', is_array($profile['expertise_ids'] ?? null)
    ? $profile['expertise_ids'] : []);
if ($qualificationRows === []) {
    $qualificationRows[] = [];
}
if ($linkRows === []) {
    $linkRows[] = [];
}
$linkTypes = [
    'orcid' => 'ORCID', 'google_scholar' => 'Google Scholar',
    'researchgate' => 'ResearchGate', 'linkedin' => 'LinkedIn',
    'institutional_repository' => 'Institutional repository',
    'personal_website' => 'Personal website', 'other' => 'Other profile',
];
?>
<section class="admin-page-heading">
    <div>
        <p class="eyebrow">Staff self-service</p>
        <h1>My profile</h1>
        <p>Changes save immediately to your public profile. Every change is logged and can be reviewed by an administrator.</p>
    </div>
    <a class="button button-secondary" href="<?= View::escape($baseUrl) ?>staff/<?= View::escape((string) ($profile['slug'] ?? '')) ?>" target="_blank" rel="noopener">View public profile</a>
</section>
<?php if ($errors !== []): ?>
    <div class="form-alert" role="alert"><strong>Please correct the following:</strong><ul><?php foreach ($errors as $error): ?><li><?= View::escape($error) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<?php if (isset($success) && $success): ?>
    <div class="success-alert" role="status"><?= View::escape($success) ?></div>
<?php endif; ?>
<form class="content-form" method="post" enctype="multipart/form-data" action="<?= View::escape($baseUrl) ?>my-profile">
    <input type="hidden" name="_token" value="<?= View::escape($csrfToken) ?>">
    <input type="hidden" name="MAX_FILE_SIZE" value="20971520">
    <section class="form-section">
        <div class="form-section-heading"><h2>Profile photograph</h2><p>Upload a new photo, or leave this blank to keep your current one.</p></div>
        <div class="form-grid">
            <div class="form-field span-2 upload-field"><label for="profile_image">Upload profile image</label><input id="profile_image" name="profile_image" type="file" accept="image/jpeg,image/png,image/webp,image/gif"><p class="field-guidance">JPEG, PNG, WebP, or GIF; maximum 20 MB.</p></div>
            <div class="form-field span-2"><label for="profile_alt_text">Image description</label><input id="profile_alt_text" name="profile_alt_text" maxlength="255" value="<?= View::escape($value('profile_alt_text')) ?>"><p class="field-guidance">Required when uploading a new photo.</p></div>
        </div>
    </section>
    <section class="form-section">
        <div class="form-section-heading"><h2>Biography and expertise</h2><p>Short biography is shown across the site alongside your name.</p></div>
        <div class="form-grid">
            <?php foreach (['short_biography'=>'Short biography','biography'=>'Full biography','research_summary'=>'Research summary','teaching_summary'=>'Teaching summary','supervision_interests'=>'Supervision interests'] as $field => $label): ?><div class="form-field span-2"><label for="<?= $field ?>"><?= $label ?></label><textarea id="<?= $field ?>" name="<?= $field ?>" rows="<?= $field === 'short_biography' ? 4 : 7 ?>"><?= View::escape($value($field)) ?></textarea></div><?php endforeach; ?>
            <div class="form-field"><label><input type="checkbox" name="supervision_available" value="1" <?= $value('supervision_available') === '1' ? 'checked' : '' ?>> Available for supervision</label></div>
            <?php if ($expertiseAreas !== []): ?><fieldset class="form-field span-2 staff-expertise-picker"><legend>Areas of expertise</legend><p class="field-guidance">Select the most important area first; selected areas appear on your public profile.</p><div class="staff-expertise-options"><?php foreach ($expertiseAreas as $area): ?><label><input type="checkbox" name="expertise_ids[]" value="<?= (int) $area['id'] ?>" <?= in_array((int) $area['id'], $selectedExpertise, true) ? 'checked' : '' ?>> <?= View::escape($area['name']) ?></label><?php endforeach; ?></div></fieldset><?php endif; ?>
        </div>
    </section>
    <section class="form-section" data-repeatable="qualifications">
        <div class="form-section-heading"><div><h2>Qualifications</h2><p>Add academic and professional qualifications in display order.</p></div><button class="button button-secondary" type="button" data-repeatable-add>Add qualification</button></div>
        <div class="staff-repeatable-list" data-repeatable-list data-next-index="<?= count($qualificationRows) ?>">
            <?php foreach ($qualificationRows as $index => $row): ?><div class="staff-repeatable-row" data-repeatable-row>
                <div class="form-grid">
                    <div class="form-field"><label>Qualification or award</label><input name="qualifications[<?= $index ?>][qualification]" maxlength="200" value="<?= View::escape((string) ($row['qualification'] ?? '')) ?>" placeholder="PhD, MSc, BEng"></div>
                    <div class="form-field"><label>Field of study</label><input name="qualifications[<?= $index ?>][field_of_study]" maxlength="200" value="<?= View::escape((string) ($row['field_of_study'] ?? '')) ?>"></div>
                    <div class="form-field"><label>Institution</label><input name="qualifications[<?= $index ?>][institution]" maxlength="255" value="<?= View::escape((string) ($row['institution'] ?? '')) ?>"></div>
                    <div class="form-field"><label>Country</label><input name="qualifications[<?= $index ?>][country]" maxlength="100" value="<?= View::escape((string) ($row['country'] ?? '')) ?>"></div>
                    <div class="form-field"><label>Completion year</label><input name="qualifications[<?= $index ?>][completion_year]" type="number" min="1900" max="<?= (int) date('Y') + 10 ?>" value="<?= View::escape((string) ($row['completion_year'] ?? '')) ?>"></div>
                    <div class="form-field staff-repeatable-action"><button type="button" class="text-button" data-repeatable-remove>Remove</button></div>
                </div>
            </div><?php endforeach; ?>
        </div>
        <template data-repeatable-template><div class="staff-repeatable-row" data-repeatable-row><div class="form-grid">
            <div class="form-field"><label>Qualification or award</label><input name="qualifications[__INDEX__][qualification]" maxlength="200" placeholder="PhD, MSc, BEng"></div>
            <div class="form-field"><label>Field of study</label><input name="qualifications[__INDEX__][field_of_study]" maxlength="200"></div>
            <div class="form-field"><label>Institution</label><input name="qualifications[__INDEX__][institution]" maxlength="255"></div>
            <div class="form-field"><label>Country</label><input name="qualifications[__INDEX__][country]" maxlength="100"></div>
            <div class="form-field"><label>Completion year</label><input name="qualifications[__INDEX__][completion_year]" type="number" min="1900" max="<?= (int) date('Y') + 10 ?>"></div>
            <div class="form-field staff-repeatable-action"><button type="button" class="text-button" data-repeatable-remove>Remove</button></div>
        </div></div></template>
    </section>
    <section class="form-section" data-repeatable="links">
        <div class="form-section-heading"><div><h2>Professional and research profiles</h2><p>Link verified research platforms and professional profiles.</p></div><button class="button button-secondary" type="button" data-repeatable-add>Add profile link</button></div>
        <div class="staff-repeatable-list" data-repeatable-list data-next-index="<?= count($linkRows) ?>">
            <?php foreach ($linkRows as $index => $row): ?><div class="staff-repeatable-row" data-repeatable-row><div class="form-grid">
                <div class="form-field"><label>Profile type</label><select name="links[<?= $index ?>][link_type]"><?php foreach ($linkTypes as $type => $label): ?><option value="<?= $type ?>" <?= (string) ($row['link_type'] ?? 'google_scholar') === $type ? 'selected' : '' ?>><?= View::escape($label) ?></option><?php endforeach; ?></select></div>
                <div class="form-field"><label>Public label</label><input name="links[<?= $index ?>][label]" maxlength="120" value="<?= View::escape((string) ($row['label'] ?? '')) ?>" placeholder="Optional"></div>
                <div class="form-field span-2"><label>Complete web address</label><input name="links[<?= $index ?>][url]" type="url" maxlength="500" value="<?= View::escape((string) ($row['url'] ?? '')) ?>" placeholder="https://"></div>
                <div class="form-field staff-repeatable-action"><button type="button" class="text-button" data-repeatable-remove>Remove</button></div>
            </div></div><?php endforeach; ?>
        </div>
        <template data-repeatable-template><div class="staff-repeatable-row" data-repeatable-row><div class="form-grid">
            <div class="form-field"><label>Profile type</label><select name="links[__INDEX__][link_type]"><?php foreach ($linkTypes as $type => $label): ?><option value="<?= $type ?>"><?= View::escape($label) ?></option><?php endforeach; ?></select></div>
            <div class="form-field"><label>Public label</label><input name="links[__INDEX__][label]" maxlength="120" placeholder="Optional"></div>
            <div class="form-field span-2"><label>Complete web address</label><input name="links[__INDEX__][url]" type="url" maxlength="500" placeholder="https://"></div>
            <div class="form-field staff-repeatable-action"><button type="button" class="text-button" data-repeatable-remove>Remove</button></div>
        </div></div></template>
    </section>
    <section class="form-section">
        <div class="form-section-heading"><h2>Contact and office</h2><p>Your institutional email is your login and can't be changed here — contact an administrator if it needs to change.</p></div>
        <div class="form-grid">
            <div class="form-field"><label>Institutional email</label><p><?= View::escape($value('institutional_email')) ?></p></div>
            <div class="form-field"><label for="alternative_email">Alternative email</label><input id="alternative_email" name="alternative_email" type="email" maxlength="190" value="<?= View::escape($value('alternative_email')) ?>"></div>
            <div class="form-field"><label for="public_phone">Public phone</label><input id="public_phone" name="public_phone" maxlength="50" value="<?= View::escape($value('public_phone')) ?>"></div>
            <div class="form-field"><label for="office_room">Office room</label><input id="office_room" name="office_room" maxlength="120" value="<?= View::escape($value('office_room')) ?>" placeholder="TF01"></div>
            <div class="form-field span-2"><label for="consultation_hours">Consultation hours</label><input id="consultation_hours" name="consultation_hours" maxlength="255" value="<?= View::escape($value('consultation_hours')) ?>"></div>
        </div>
    </section>
    <div class="form-actions">
        <button class="button button-primary" type="submit">Save changes</button>
    </div>
</form>
