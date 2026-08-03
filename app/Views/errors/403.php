<?php

declare(strict_types=1);
?>
<section class="error-page">
    <div class="shell narrow">
        <p class="error-code">403</p>
        <h1>Your secure session expired.</h1>
        <p>Return to the form and try again. No changes were made.</p>
        <a class="button button-primary" href="<?= \FastWebsite\Core\View::escape($baseUrl) ?>login">
            Return to login
        </a>
    </div>
</section>

