<?php

declare(strict_types=1);

use FastWebsite\Core\View;
?>
<section class="auth-section">
    <div class="auth-card">
        <div class="auth-heading">
            <p class="eyebrow">Staff &amp; administration sign in</p>
            <h1>Sign in to FAST</h1>
            <p>Use your FAST staff or administration account to continue.</p>
        </div>

        <?php if (is_string($error) && $error !== ''): ?>
            <div class="form-alert" role="alert"><?= View::escape($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= View::escape($baseUrl) ?>login" novalidate>
            <input type="hidden" name="_token" value="<?= View::escape($csrfToken) ?>">
            <div class="form-field">
                <label for="email">Email address</label>
                <input
                    id="email"
                    name="email"
                    type="email"
                    maxlength="190"
                    autocomplete="username"
                    value="<?= View::escape($email) ?>"
                    required
                >
            </div>
            <div class="form-field">
                <label for="password">Password</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    maxlength="255"
                    autocomplete="current-password"
                    required
                >
            </div>
            <button class="button button-primary button-full" type="submit">Sign in</button>
        </form>

        <div class="auth-alt-action">
            <p>New to FAST, or don't have a password yet?</p>
            <a class="button button-secondary button-full" href="<?= View::escape($baseUrl) ?>password/forgot">
                Set up or reset your password
            </a>
        </div>

        <p class="auth-note">
            Repeated unsuccessful attempts are temporarily limited and recorded.
        </p>
    </div>
</section>

