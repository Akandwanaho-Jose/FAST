<?php

declare(strict_types=1);
?>
<section class="error-page">
    <div class="shell narrow">
        <p class="error-code">500</p>
        <h1>Something went wrong.</h1>
        <p>The request could not be completed. No internal details have been displayed.</p>
        <a class="button button-primary" href="<?= \FastWebsite\Core\View::escape($baseUrl) ?>">Return to the homepage</a>
    </div>
</section>
