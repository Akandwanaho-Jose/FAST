<?php

declare(strict_types=1);
?>
<section class="error-page">
    <div class="shell narrow">
        <p class="error-code">404</p>
        <h1>That page could not be found.</h1>
        <p>The address may be incomplete, or the page may not exist yet.</p>
        <a class="button button-primary" href="<?= \FastWebsite\Core\View::escape($baseUrl) ?>">Return to the homepage</a>
    </div>
</section>
