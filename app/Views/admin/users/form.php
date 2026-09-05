<?php

declare(strict_types=1);

use FastWebsite\Core\View;

$value = static fn (string $field): string => (string) ($values[$field] ?? '');
$editing = is_int($userId);
$action = $editing ? $baseUrl . 'admin/users/' . $userId : $baseUrl . 'admin/users';
$selectedRoleIds = array_map('intval', is_array($values['role_ids'] ?? null) ? $values['role_ids'] : []);
?>
<section class="admin-page-heading">
    <div>
        <p class="eyebrow">Administration</p>
        <h1><?= $editing ? 'Edit user' : 'Add user' ?></h1>
        <p><?= $editing ? 'Update this person\'s name, email, and roles.' : 'A password setup link is emailed to the address below once the account is created.' ?></p>
    </div>
    <a class="button button-secondary" href="<?= View::escape($baseUrl) ?>admin/users">Cancel</a>
</section>
<?php if ($errors !== []): ?>
    <div class="form-alert" role="alert"><strong>Please correct the following:</strong><ul><?php foreach ($errors as $error): ?><li><?= View::escape($error) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<form class="content-form" method="post" action="<?= View::escape($action) ?>">
    <input type="hidden" name="_token" value="<?= View::escape($csrfToken) ?>">
    <section class="form-section">
        <div class="form-section-heading"><h2>Identity</h2><p>Used for sign-in and correspondence.</p></div>
        <div class="form-grid">
            <div class="form-field"><label for="name">Full name *</label><input id="name" name="name" maxlength="200" required value="<?= View::escape($value('name')) ?>"></div>
            <div class="form-field"><label for="email">Email *</label><input id="email" name="email" type="email" maxlength="190" required value="<?= View::escape($value('email')) ?>"></div>
        </div>
    </section>
    <section class="form-section">
        <div class="form-section-heading"><h2>Roles</h2><p>Choose every role this person should hold. Permissions come entirely from the roles selected here.</p></div>
        <fieldset class="form-field span-2 staff-expertise-picker">
            <legend>Assigned roles *</legend>
            <div class="staff-expertise-options">
                <?php foreach ($roles as $role): ?>
                    <label>
                        <input type="checkbox" name="role_ids[]" value="<?= (int) $role['id'] ?>" <?= in_array((int) $role['id'], $selectedRoleIds, true) ? 'checked' : '' ?>>
                        <?= View::escape($role['name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>
    </section>
    <div class="admin-form-actions"><button class="button button-primary" type="submit"><?= $editing ? 'Save changes' : 'Create user and send invite' ?></button></div>
</form>
