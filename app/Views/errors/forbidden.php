<?php

declare(strict_types=1);
?>
<section class="error-page">
    <div class="shell narrow">
        <p class="error-code">403</p>
        <h1>You do not have permission to access this area.</h1>
        <p>Your account is active, but the required database permission is not assigned.</p>
        <a class="button button-primary" href="<?= \FastWebsite\Core\View::escape($baseUrl) ?>admin">
            Return to administration
        </a>
    </div>
</section>

