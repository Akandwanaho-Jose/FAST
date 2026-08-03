<?php

declare(strict_types=1);

use FastWebsite\Core\View;
?>
<section class="admin-page-heading">
    <div>
        <p class="eyebrow"><?= View::escape($module['phase']) ?></p>
        <h1><?= View::escape($module['label']) ?></h1>
        <p>Your account has permission to access this area.</p>
    </div>
</section>

<section class="admin-panel placeholder-panel">
    <span class="placeholder-mark" aria-hidden="true">→</span>
    <div>
        <h2>Module destination is ready</h2>
        <p>
            The protected route and permission-aware navigation are active.
            Full <?= View::escape(strtolower($module['label'])) ?> management is scheduled for
            <?= View::escape($module['phase']) ?>.
        </p>
        <a class="button button-secondary" href="<?= View::escape($baseUrl) ?>admin">
            Return to dashboard
        </a>
    </div>
</section>
