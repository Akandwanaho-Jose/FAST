<?php

declare(strict_types=1);

use FastWebsite\Core\View;
?>
<section class="auth-section">
    <div class="auth-card auth-card-wide">
        <?php if ($success): ?>
            <div class="auth-heading">
                <p class="eyebrow">Password updated</p>
                <h1>You're all set</h1>
                <p>Your password has been changed. You can now sign in with it.</p>
            </div>
            <p class="auth-note">
                <a class="button button-primary" href="<?= View::escape($baseUrl) ?>login">Sign in</a>
            </p>
        <?php else: ?>
            <div class="auth-heading">
                <p class="eyebrow">Account recovery</p>
                <h1>Choose a new password</h1>
                <p>Enter a new password for your account below.</p>
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

            <form method="post" action="<?= View::escape($baseUrl) ?>password/reset/<?= View::escape($token) ?>" novalidate>
                <input type="hidden" name="_token" value="<?= View::escape($csrfToken) ?>">
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
                <button class="button button-primary button-full" type="submit">Set new password</button>
            </form>
            <p class="auth-note">
                <a href="<?= View::escape($baseUrl) ?>password/forgot">Request a new link</a>
            </p>
        <?php endif; ?>
    </div>
</section>
