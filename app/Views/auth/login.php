<?php

declare(strict_types=1);

use FastWebsite\Core\View;
?>
<section class="auth-section">
    <div class="auth-card">
        <div class="auth-heading">
            <p class="eyebrow">Secure administration</p>
            <h1>Sign in to FAST</h1>
            <p>Use your authorised website administration account.</p>
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
        <p class="auth-note">
            Repeated unsuccessful attempts are temporarily limited and recorded.
        </p>
    </div>
</section>

