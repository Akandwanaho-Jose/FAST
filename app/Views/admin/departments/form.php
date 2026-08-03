<?php

declare(strict_types=1);

use FastWebsite\Core\View;

$value = static fn (string $field): string => (string) ($values[$field] ?? '');
$editing = is_int($departmentId);
$published = $editing && $currentStatus === 'published';
$action = $editing
    ? $baseUrl . 'admin/departments/' . $departmentId
    : $baseUrl . 'admin/departments';
$locationLabel = static function (array $location): string {
    $parts = array_filter([
        $location['campus'],
        $location['building'],
        $location['floor'],
        $location['room'],
    ], static fn (mixed $part): bool => is_string($part) && $part !== '');

    return $parts === [] ? 'Location #' . $location['id'] : implode(' — ', $parts);
};
$currentHeroPath = is_string($values['hero_path'] ?? null)
    ? str_replace('\\', '/', (string) $values['hero_path'])
    : '';
$currentHeroUrl = $currentHeroPath !== ''
    && preg_match('#^(?:https?:)?//#i', $currentHeroPath) !== 1
        ? $baseUrl . ltrim(
            preg_replace('#^public/#', '', $currentHeroPath) ?? $currentHeroPath,
            '/'
        )
        : null;
?>
<section class="admin-page-heading">
    <div>
        <p class="eyebrow"><?= $published ? 'Live department' : ($editing ? 'Revision' : 'New draft') ?></p>
        <h1><?= $editing ? 'Edit department' : 'Create department' ?></h1>
        <p>
            <?= $published
                ? 'This department remains public while you edit. Saving applies the changes immediately.'
                : 'Required fields are marked. ' . ($canPublishDirectly
                ? 'You can save the draft or save and publish in one step.'
                : 'Saving details keeps the record in draft.') ?>
        </p>
    </div>
    <a class="button button-secondary" href="<?= View::escape($baseUrl) ?>admin/departments<?= $editing ? '/' . $departmentId : '' ?>">
        Cancel
    </a>
</section>

<?php if ($errors !== []): ?>
    <div class="form-alert" role="alert">
        <strong>Please correct the following:</strong>
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?= View::escape($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>
<?php if ($published): ?>
    <div class="success-alert live-edit-notice" role="status">
        <strong>Editing published content.</strong>
        The current department stays online until you select “Save published changes”.
    </div>
<?php endif; ?>

<form class="content-form" method="post" enctype="multipart/form-data" action="<?= View::escape($action) ?>">
    <input type="hidden" name="_token" value="<?= View::escape($csrfToken) ?>">
    <input type="hidden" name="MAX_FILE_SIZE" value="20971520">

    <section class="form-section">
        <div class="form-section-heading">
            <h2>Identity</h2>
            <p>The canonical name and clean public URL.</p>
        </div>
        <div class="form-grid">
            <div class="form-field span-2">
                <label for="faculty_id">Faculty <span aria-hidden="true">*</span></label>
                <select id="faculty_id" name="faculty_id" required>
                    <option value="<?= (int) $faculty['id'] ?>" <?= $value('faculty_id') === (string) $faculty['id'] ? 'selected' : '' ?>>
                        <?= View::escape($faculty['name']) ?>
                    </option>
                </select>
            </div>
            <div class="form-field span-2">
                <label for="name">Department name <span aria-hidden="true">*</span></label>
                <input id="name" name="name" maxlength="200" required value="<?= View::escape($value('name')) ?>">
            </div>
            <div class="form-field">
                <label for="short_name">Short name</label>
                <input id="short_name" name="short_name" maxlength="30" value="<?= View::escape($value('short_name')) ?>">
            </div>
            <div class="form-field">
                <label for="display_order">Display order</label>
                <input id="display_order" name="display_order" type="number" min="0" max="65535" value="<?= View::escape($value('display_order')) ?>">
            </div>
            <div class="form-field span-2">
                <label for="slug">URL slug</label>
                <input id="slug" name="slug" maxlength="220" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" value="<?= View::escape($value('slug')) ?>">
                <p class="field-guidance">Leave blank to generate it from the department name.</p>
            </div>
        </div>
    </section>

    <section class="form-section">
        <div class="form-section-heading">
            <h2>Public content</h2>
            <p>Use verified faculty-approved wording only.</p>
        </div>
        <div class="form-grid">
            <?php foreach ([
                'overview' => 'Overview',
                'history' => 'History',
                'vision' => 'Vision',
                'mission' => 'Mission',
                'strategic_direction' => 'Strategic direction',
                'hod_message' => 'Head of department message',
            ] as $field => $label): ?>
                <div class="form-field span-2">
                    <label for="<?= View::escape($field) ?>"><?= View::escape($label) ?></label>
                    <textarea id="<?= View::escape($field) ?>" name="<?= View::escape($field) ?>" rows="<?= in_array($field, ['vision', 'mission'], true) ? 4 : 7 ?>"><?= View::escape($value($field)) ?></textarea>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="form-section">
        <div class="form-section-heading">
            <h2>Contact and relationships</h2>
            <p>Connect reusable location and media records where available.</p>
        </div>
        <div class="form-grid">
            <div class="form-field">
                <label for="email">Contact email</label>
                <input id="email" name="email" type="email" maxlength="190" value="<?= View::escape($value('email')) ?>">
            </div>
            <div class="form-field">
                <label for="phone">Contact phone</label>
                <input id="phone" name="phone" maxlength="50" value="<?= View::escape($value('phone')) ?>">
            </div>
            <div class="form-field span-2">
                <label for="location_id">Location</label>
                <select id="location_id" name="location_id">
                    <option value="">No linked location</option>
                    <?php foreach ($locations as $location): ?>
                        <option value="<?= (int) $location['id'] ?>" <?= $value('location_id') === (string) $location['id'] ? 'selected' : '' ?>>
                            <?= View::escape($locationLabel($location)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($locations === []): ?>
                    <p class="field-guidance">No reusable locations exist yet. The department can be saved without one.</p>
                <?php endif; ?>
            </div>
            <div class="form-field span-2">
                <label for="hero_media_id">Existing hero image</label>
                <select id="hero_media_id" name="hero_media_id">
                    <option value="">No hero image</option>
                    <?php foreach ($images as $image): ?>
                        <option value="<?= (int) $image['id'] ?>" <?= $value('hero_media_id') === (string) $image['id'] ? 'selected' : '' ?>>
                            <?= View::escape($image['original_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="field-guidance">Choose an existing image, or upload a new one below.</p>
            </div>
            <?php if ($currentHeroUrl !== null): ?>
                <div class="form-field span-2 current-image">
                    <span class="field-label">Current hero image</span>
                    <img src="<?= View::escape($currentHeroUrl) ?>" alt="<?= View::escape($value('hero_alt_text')) ?>">
                    <code><?= View::escape($currentHeroPath) ?></code>
                </div>
            <?php endif; ?>
            <div class="form-field span-2 upload-field">
                <label for="hero_image">Upload a new hero image</label>
                <input
                    id="hero_image"
                    name="hero_image"
                    type="file"
                    accept="image/jpeg,image/png,image/webp,image/gif"
                >
                <p class="field-guidance">
                    JPEG, PNG, WebP, or GIF; maximum 20 MB. A new upload replaces the existing selection and its generated public URL is stored in the media library.
                </p>
            </div>
            <div class="form-field span-2">
                <label for="hero_alt_text">Image description</label>
                <input
                    id="hero_alt_text"
                    name="hero_alt_text"
                    maxlength="255"
                    value="<?= View::escape($value('hero_alt_text')) ?>"
                    aria-describedby="hero-alt-guidance"
                >
                <p class="field-guidance" id="hero-alt-guidance">
                    Required for a new upload. Describe what the image shows, without starting with “image of”.
                </p>
            </div>
        </div>
    </section>

    <?php if ($editing): ?>
        <section class="form-section">
            <div class="form-field">
                <label for="revision_note">Revision note</label>
                <textarea id="revision_note" name="revision_note" rows="3" maxlength="2000"><?= View::escape($revisionNote) ?></textarea>
                <p class="field-guidance">Briefly explain what changed. A default note is used if left blank.</p>
            </div>
        </section>
    <?php endif; ?>

    <div class="form-actions">
        <?php if ($published): ?>
            <button class="button button-primary publish-button" type="submit" name="submit_action" value="save_live">
                Save published changes
            </button>
        <?php else: ?>
            <button class="button <?= $canPublishDirectly ? 'button-secondary' : 'button-primary' ?>" type="submit" name="submit_action" value="save">
                <?= $editing ? 'Save revision' : 'Create draft' ?>
            </button>
            <?php if ($canPublishDirectly): ?>
                <button class="button button-primary publish-button" type="submit" name="submit_action" value="publish">
                    Save and publish
                </button>
            <?php endif; ?>
        <?php endif; ?>
        <a class="button button-secondary" href="<?= View::escape($baseUrl) ?>admin/departments<?= $editing ? '/' . $departmentId : '' ?>">Cancel</a>
    </div>
</form>
