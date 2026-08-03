<?php

declare(strict_types=1);

use FastWebsite\Core\View;
?>
<section class="auth-section">
    <div class="auth-card auth-card-wide">
        <div class="auth-heading">
            <p class="eyebrow"><?= $firstLogin ? 'First login' : 'Account security' ?></p>
            <h1>Choose a new password</h1>
            <p>
                <?= $firstLogin
                    ? 'You must replace the temporary password before continuing.'
                    : 'Enter your current password and choose a new one.' ?>
            </p>
        </div>

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

        <form method="post" action="<?= View::escape($baseUrl) ?>password/change" novalidate>
            <input type="hidden" name="_token" value="<?= View::escape($csrfToken) ?>">
            <div class="form-field">
                <label for="current_password">Current password</label>
                <input
                    id="current_password"
                    name="current_password"
                    type="password"
                    maxlength="255"
                    autocomplete="current-password"
                    required
                >
            </div>
            <div class="form-field">
                <label for="new_password">New password</label>
                <input
                    id="new_password"
                    name="new_password"
                    type="password"
                    maxlength="255"
                    autocomplete="new-password"
                    aria-describedby="password-guidance"
                    required
                >
                <p id="password-guidance" class="field-guidance">
                    Use at least 12 characters with uppercase, lowercase, number, and symbol.
                </p>
            </div>
            <div class="form-field">
                <label for="new_password_confirmation">Confirm new password</label>
                <input
                    id="new_password_confirmation"
                    name="new_password_confirmation"
                    type="password"
                    maxlength="255"
                    autocomplete="new-password"
                    required
                >
            </div>
            <button class="button button-primary button-full" type="submit">Change password</button>
        </form>
    </div>
</section>

