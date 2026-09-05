<?php

declare(strict_types=1);

use FastWebsite\Core\View;
?>
<section class="auth-section">
    <div class="auth-card">
        <?php if ($sent): ?>
            <div class="auth-heading">
                <p class="eyebrow">Check your email</p>
                <h1>Reset link sent</h1>
                <p>
                    If that email address has an account, we've sent a link to reset
                    the password. It expires in one hour. Check your inbox (and spam
                    folder) for a message from FAST.
                </p>
            </div>
            <p class="auth-note">
                <a href="<?= View::escape($baseUrl) ?>login">Back to sign in</a>
            </p>
        <?php else: ?>
            <div class="auth-heading">
                <p class="eyebrow">Account recovery</p>
                <h1>Reset your password</h1>
                <p>Enter your account email and we'll send you a link to set a new password.</p>
            </div>

            <form method="post" action="<?= View::escape($baseUrl) ?>password/forgot" novalidate>
                <input type="hidden" name="_token" value="<?= View::escape($csrfToken) ?>">
                <div class="form-field">
                    <label for="email">Email address</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        maxlength="190"
                        autocomplete="username"
                        required
                    >
                </div>
                <button class="button button-primary button-full" type="submit">Send reset link</button>
            </form>
            <p class="auth-note">
                <a href="<?= View::escape($baseUrl) ?>login">Back to sign in</a>
            </p>
        <?php endif; ?>
    </div>
</section>
