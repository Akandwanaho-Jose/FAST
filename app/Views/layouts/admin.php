<?php

declare(strict_types=1);

use FastWebsite\Core\View;

$title = isset($pageTitle) ? (string) $pageTitle : 'Administration';
$path = isset($currentPath) ? (string) $currentPath : '';
$urlBase = isset($baseUrl) ? (string) $baseUrl : '/';
$roleNames = array_map(
    static fn (array $role): string => (string) $role['name'],
    $roles ?? []
);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= View::escape($metaDescription ?? '') ?>">
    <title><?= View::escape($title) ?> | FAST Admin</title>
    <link rel="stylesheet" href="<?= View::escape($urlBase) ?>assets/css/app.css">
    <link rel="stylesheet" href="<?= View::escape($urlBase) ?>assets/css/admin.css">
    <script defer src="<?= View::escape($urlBase) ?>assets/js/admin-editor.js"></script>
</head>
<body class="admin-body">
    <a class="skip-link" href="#admin-main">Skip to main content</a>
    <div class="admin-shell">
        <aside class="admin-sidebar">
            <a class="admin-brand" href="<?= View::escape($urlBase) ?>admin">
                <span class="identity-mark" aria-hidden="true">F</span>
                <span><strong>FAST</strong><small>Administration</small></span>
            </a>

            <nav class="admin-navigation" aria-label="Administration">
                <?php foreach ($adminNavigation as $item): ?>
                    <?php $itemPath = '/' . $item['route']; ?>
                    <?php $isCurrent = $item['key'] === 'dashboard'
                        ? $path === $itemPath
                        : ($path === $itemPath
                            || str_starts_with($path, $itemPath . '/')
                            || ($item['key'] === 'departments'
                                && $path === '/admin/faculty')); ?>
                    <a
                        href="<?= View::escape($urlBase . $item['route']) ?>"
                        <?= $isCurrent ? 'aria-current="page"' : '' ?>
                    >
                        <?= View::escape($item['label']) ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="admin-sidebar-footer">
                <a href="<?= View::escape($urlBase) ?>">View public website</a>
            </div>
        </aside>

        <div class="admin-workspace">
            <header class="admin-topbar">
                <div>
                    <p class="admin-context">Administration</p>
                    <strong><?= View::escape($title) ?></strong>
                </div>
                <details class="account-menu">
                    <summary>
                        <span><?= View::escape($user['name']) ?></span>
                        <small><?= View::escape(implode(', ', $roleNames) ?: 'No assigned role') ?></small>
                    </summary>
                    <div class="account-popover">
                        <p><?= View::escape($user['email']) ?></p>
                        <p class="scope-note"><?= View::escape($scopeLabel) ?></p>
                        <a href="<?= View::escape($urlBase) ?>password/change">Change password</a>
                        <form method="post" action="<?= View::escape($urlBase) ?>logout">
                            <input type="hidden" name="_token" value="<?= View::escape($csrfToken) ?>">
                            <button type="submit">Sign out securely</button>
                        </form>
                    </div>
                </details>
            </header>

            <main id="admin-main" class="admin-main">
                <?= $content ?>
            </main>
        </div>
    </div>
</body>
</html>
