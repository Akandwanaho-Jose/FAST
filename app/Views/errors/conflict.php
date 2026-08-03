<?php

declare(strict_types=1);

use FastWebsite\Core\View;
?>
<section class="error-page">
    <div class="shell narrow">
        <p class="error-code">409</p>
        <h1>That action is not available.</h1>
        <p><?= View::escape($errorMessage ?? 'The record changed or is not in the required workflow state.') ?></p>
        <a class="button button-primary" href="<?= View::escape($baseUrl) ?>admin/departments">
            Return to departments
        </a>
    </div>
</section>
