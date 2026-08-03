<?php

declare(strict_types=1);

use FastWebsite\Core\View;

$title = isset($pageTitle) ? (string) $pageTitle : 'FAST Website';
$description = isset($metaDescription)
    ? (string) $metaDescription
    : 'Faculty of Applied Sciences and Technology website.';
$path = isset($currentPath) ? (string) $currentPath : '';
$urlBase = isset($baseUrl) ? (string) $baseUrl : '/';
$assetVersion = '20260802-14';
$settings = is_array($siteContent ?? null) ? $siteContent : [];
$navigationData = is_array($siteNavigation ?? null) ? $siteNavigation : [];
$programmeDepartments = $navigationData['departments'] ?? [];
$undergraduateProgrammes = $navigationData['undergraduate'] ?? [];
$postgraduateProgrammes = $navigationData['postgraduate'] ?? [];
$brandShort = View::setting($settings, 'identity.short_name', 'FAST');
$facultyName = View::setting($settings, 'identity.faculty_name', 'Faculty of Applied Sciences and Technology');
$universityName = View::setting($settings, 'identity.university_name', 'Mbarara University of Science and Technology');
$postalAddress = View::setting($settings, 'identity.postal_address', 'P.O. Box 1410, Mbarara, Uganda');
$universityUrl = View::setting($settings, 'identity.university_url', 'https://www.must.ac.ug/');
$libraryUrl = View::setting($settings, 'identity.library_url', 'https://elib.must.ac.ug/');
$elearningUrl = View::setting($settings, 'identity.elearning_url', 'https://vle.must.ac.ug/');
$managedLandingPages = [
    '/' => ['identity.faculty_name', 'identity.faculty_name'],
    '/departments' => ['departments.title', 'departments.introduction'],
    '/staff' => ['staff.title', 'staff.introduction'],
    '/programmes' => ['programmes.title', 'programmes.introduction'],
    '/research' => ['research.title', 'research.introduction'],
    '/research/projects' => ['research.projects_title', 'research.projects_intro'],
    '/research/publications' => ['research.publications_title', 'research.publications_intro'],
    '/innovations' => ['innovations.title', 'innovations.introduction'],
    '/facilities' => ['facilities.title', 'facilities.introduction'],
    '/engagement' => ['engagement.title', 'engagement.introduction'],
    '/news' => ['news.title', 'news.empty'],
    '/events' => ['events.title', 'events.empty'],
    '/documents' => ['documents.title', 'documents.introduction'],
];
if (isset($managedLandingPages[$path])) {
    [$titleKey, $descriptionKey] = $managedLandingPages[$path];
    $title = View::setting($settings, $titleKey, $title);
    $description = View::setting($settings, $descriptionKey, $description);
}
$activeProgrammeCategory = isset($categoryFilter) ? (string) $categoryFilter : '';

$pathStartsWith = static function (string ...$prefixes) use ($path): bool {
    foreach ($prefixes as $prefix) {
        if (str_starts_with($path, $prefix)) {
            return true;
        }
    }

    return false;
};

$section = match (true) {
    $pathStartsWith('/programmes') => ['Programmes', 'programmes'],
    $pathStartsWith('/documents') => ['Resources', 'documents'],
    $pathStartsWith('/departments') => ['Departments', 'departments'],
    $pathStartsWith('/research', '/innovations', '/facilities') => ['Research & Innovation', 'research'],
    $pathStartsWith('/staff') => ['People', 'staff'],
    $pathStartsWith('/engagement', '/impact') => ['Engagement', 'engagement'],
    $pathStartsWith('/news', '/events') => ['News & Events', 'news'],
    default => null,
};

$showBreadcrumbs = $path !== ''
    && $path !== '/'
    && $section !== null
    && !$pathStartsWith('/login', '/password');
$sectionPath = $section[1] ?? '';
$isSectionLanding = $section !== null && match ($sectionPath) {
    'programmes' => $path === '/programmes',
    'research' => in_array($path, ['/research', '/innovations', '/facilities'], true),
    'news' => in_array($path, ['/news', '/events'], true),
    default => $path === '/' . $sectionPath,
};
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= View::escape($description) ?>">
    <title><?= View::escape($title) ?> | <?= View::escape($brandShort) ?></title>
    <link rel="stylesheet" href="<?= View::escape($urlBase) ?>assets/css/app.css?v=<?= View::escape($assetVersion) ?>">
    <script src="<?= View::escape($urlBase) ?>assets/js/site.js?v=<?= View::escape($assetVersion) ?>" defer></script>
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <header class="site-header">
        <div class="shell header-inner">
            <a class="identity" href="<?= View::escape($urlBase) ?>" aria-label="<?= View::escape($brandShort) ?> website home">
                <img class="identity-logo" src="<?= View::escape($urlBase) ?>assets/images/must-logo.webp" alt="" width="56" height="56">
                <span class="identity-copy">
                    <strong><?= View::escape($brandShort) ?></strong>
                    <small><?= View::escape($facultyName) ?></small>
                </span>
            </a>

            <button class="navigation-toggle" type="button" aria-expanded="false" aria-controls="primary-navigation">
                <span>Menu</span>
                <span class="navigation-toggle-icon" aria-hidden="true"><i></i><i></i><i></i></span>
            </button>

            <nav class="primary-navigation" id="primary-navigation" aria-label="Primary navigation">
                <details class="nav-group"<?= $sectionPath === 'programmes' ? ' data-current="true"' : '' ?>>
                    <summary><?= View::escape(View::setting($settings, 'navigation.programmes', 'Programmes')) ?></summary>
                    <div class="nav-submenu nav-submenu-programmes">
                        <a <?= $pathStartsWith('/programmes') && $activeProgrammeCategory === '' ? 'aria-current="page"' : '' ?> href="<?= View::escape($urlBase) ?>programmes"><?= View::escape(View::setting($settings, 'navigation.all_programmes', 'All programmes')) ?></a>
                        <div class="nav-subgroup">
                            <a class="nav-subgroup-trigger" <?= $activeProgrammeCategory === 'undergraduate' ? 'aria-current="page"' : '' ?> href="<?= View::escape($urlBase) ?>programmes?category=undergraduate" aria-haspopup="true">
                                <span><?= View::escape(View::setting($settings, 'navigation.undergraduate', 'Undergraduate programmes')) ?></span><span aria-hidden="true">›</span>
                            </a>
                            <div class="nav-subgroup-menu" aria-label="Undergraduate programmes">
                                <a class="nav-subgroup-all" href="<?= View::escape($urlBase) ?>programmes?category=undergraduate">View all undergraduate programmes</a>
                                <?php foreach ($undergraduateProgrammes as $programme): ?>
                                    <a <?= $path === '/programmes/' . $programme['slug'] ? 'aria-current="page"' : '' ?> href="<?= View::escape($urlBase) ?>programmes/<?= View::escape($programme['slug']) ?>"><?= View::escape($programme['name']) ?></a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="nav-subgroup">
                            <a class="nav-subgroup-trigger" <?= $activeProgrammeCategory === 'postgraduate' ? 'aria-current="page"' : '' ?> href="<?= View::escape($urlBase) ?>programmes?category=postgraduate" aria-haspopup="true">
                                <span><?= View::escape(View::setting($settings, 'navigation.postgraduate', 'Postgraduate programmes')) ?></span><span aria-hidden="true">›</span>
                            </a>
                            <div class="nav-subgroup-menu" aria-label="Postgraduate programmes">
                                <a class="nav-subgroup-all" href="<?= View::escape($urlBase) ?>programmes?category=postgraduate">View all postgraduate programmes</a>
                                <?php if ($postgraduateProgrammes === []): ?>
                                    <p class="nav-subgroup-empty">No postgraduate programmes are published yet.</p>
                                <?php else: ?>
                                    <?php foreach ($postgraduateProgrammes as $programme): ?>
                                        <a <?= $path === '/programmes/' . $programme['slug'] ? 'aria-current="page"' : '' ?> href="<?= View::escape($urlBase) ?>programmes/<?= View::escape($programme['slug']) ?>"><?= View::escape($programme['name']) ?></a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </details>

                <details class="nav-group"<?= $sectionPath === 'departments' ? ' data-current="true"' : '' ?>>
                    <summary><?= View::escape(View::setting($settings, 'navigation.departments', 'Departments')) ?></summary>
                    <div class="nav-submenu nav-submenu-departments">
                        <a <?= $path === '/departments' ? 'aria-current="page"' : '' ?> href="<?= View::escape($urlBase) ?>departments"><?= View::escape(View::setting($settings, 'navigation.all_departments', 'All departments')) ?></a>
                        <?php foreach ($programmeDepartments as $department): ?>
                            <a <?= $path === '/departments/' . $department['slug'] ? 'aria-current="page"' : '' ?> href="<?= View::escape($urlBase) ?>departments/<?= View::escape($department['slug']) ?>"><?= View::escape($department['name']) ?></a>
                        <?php endforeach; ?>
                    </div>
                </details>

                <details class="nav-group"<?= $sectionPath === 'research' ? ' data-current="true"' : '' ?>>
                    <summary><?= View::escape(View::setting($settings, 'navigation.research', 'Research & Innovation')) ?></summary>
                    <div class="nav-submenu nav-submenu-wide">
                        <a <?= $path === '/research' ? 'aria-current="page"' : '' ?> href="<?= View::escape($urlBase) ?>research">Research units</a>
                        <a <?= $pathStartsWith('/research/projects') ? 'aria-current="page"' : '' ?> href="<?= View::escape($urlBase) ?>research/projects">Projects</a>
                        <a <?= $pathStartsWith('/research/publications') ? 'aria-current="page"' : '' ?> href="<?= View::escape($urlBase) ?>research/publications">Publications</a>
                        <a <?= $pathStartsWith('/innovations') ? 'aria-current="page"' : '' ?> href="<?= View::escape($urlBase) ?>innovations">Innovations</a>
                        <a <?= $pathStartsWith('/facilities') ? 'aria-current="page"' : '' ?> href="<?= View::escape($urlBase) ?>facilities">Facilities &amp; equipment</a>
                    </div>
                </details>

                <a class="nav-link" <?= $pathStartsWith('/staff') ? 'aria-current="page"' : '' ?> href="<?= View::escape($urlBase) ?>staff"><?= View::escape(View::setting($settings, 'navigation.people', 'People')) ?></a>
                <a class="nav-link" <?= $pathStartsWith('/engagement', '/impact') ? 'aria-current="page"' : '' ?> href="<?= View::escape($urlBase) ?>engagement"><?= View::escape(View::setting($settings, 'navigation.engagement', 'Engagement')) ?></a>

                <details class="nav-group"<?= $sectionPath === 'news' ? ' data-current="true"' : '' ?>>
                    <summary><?= View::escape(View::setting($settings, 'navigation.news_events', 'News & Events')) ?></summary>
                    <div class="nav-submenu nav-submenu-end">
                        <a <?= $pathStartsWith('/news') ? 'aria-current="page"' : '' ?> href="<?= View::escape($urlBase) ?>news">News</a>
                        <a <?= $pathStartsWith('/events') ? 'aria-current="page"' : '' ?> href="<?= View::escape($urlBase) ?>events">Events</a>
                    </div>
                </details>

                <details class="nav-group"<?= $pathStartsWith('/documents') ? ' data-current="true"' : '' ?>>
                    <summary><?= View::escape(View::setting($settings, 'navigation.resources', 'Resources')) ?></summary>
                    <div class="nav-submenu nav-submenu-end">
                        <a <?= $pathStartsWith('/documents') ? 'aria-current="page"' : '' ?> href="<?= View::escape($urlBase) ?>documents">Academic documents</a>
                        <a href="<?= View::escape($libraryUrl) ?>"><?= View::escape(View::setting($settings, 'navigation.library', 'MUST Library')) ?></a>
                        <a href="<?= View::escape($elearningUrl) ?>"><?= View::escape(View::setting($settings, 'navigation.elearning', 'eLearning')) ?></a>
                    </div>
                </details>
            </nav>
        </div>
    </header>

    <?php if ($showBreadcrumbs): ?>
        <nav class="breadcrumbs" aria-label="Breadcrumb">
            <ol class="shell">
                <li><a href="<?= View::escape($urlBase) ?>">Home</a></li>
                <?php if (!$isSectionLanding): ?>
                    <li><a href="<?= View::escape($urlBase . $sectionPath) ?>"><?= View::escape($section[0]) ?></a></li>
                <?php endif; ?>
                <li aria-current="page"><?= View::escape($title) ?></li>
            </ol>
        </nav>
    <?php endif; ?>

    <main id="main-content">
        <?= $content ?>
    </main>

    <footer class="site-footer">
        <div class="shell footer-grid">
            <div class="footer-identity">
                <img class="footer-logo" src="<?= View::escape($urlBase) ?>assets/images/must-logo.webp" alt="" width="64" height="64">
                <div>
                    <strong><?= View::escape($facultyName) ?></strong>
                    <p><?= View::escape($universityName) ?></p>
                    <p><?= View::escape($postalAddress) ?></p>
                </div>
            </div>

            <nav class="footer-navigation" aria-label="Footer navigation">
                <div>
                    <h2><?= View::escape(View::setting($settings, 'navigation.footer_explore', 'Explore')) ?></h2>
                    <a href="<?= View::escape($urlBase) ?>programmes">Programmes</a>
                    <a href="<?= View::escape($urlBase) ?>departments">Departments</a>
                    <a href="<?= View::escape($urlBase) ?>staff">People</a>
                </div>
                <div>
                    <h2><?= View::escape(View::setting($settings, 'navigation.footer_research', 'Research')) ?></h2>
                    <a href="<?= View::escape($urlBase) ?>research">Research units</a>
                    <a href="<?= View::escape($urlBase) ?>research/projects">Projects</a>
                    <a href="<?= View::escape($urlBase) ?>research/publications">Publications</a>
                </div>
                <div>
                    <h2><?= View::escape(View::setting($settings, 'navigation.footer_connect', 'Connect')) ?></h2>
                    <a href="<?= View::escape($urlBase) ?>engagement">Engagement</a>
                    <a href="<?= View::escape($urlBase) ?>news">News</a>
                    <a href="<?= View::escape($urlBase) ?>events">Events</a>
                </div>
                <div>
                    <h2><?= View::escape(View::setting($settings, 'navigation.footer_resources', 'Resources')) ?></h2>
                    <a href="<?= View::escape($urlBase) ?>facilities">Facilities</a>
                    <a href="<?= View::escape($urlBase) ?>documents">Documents</a>
                    <a href="<?= View::escape($urlBase) ?>login">Website administration</a>
                </div>
            </nav>
        </div>
        <div class="shell footer-legal">
            <p>&copy; <?= date('Y') ?> <?= View::escape($universityName) ?></p>
            <a href="<?= View::escape($universityUrl) ?>"><?= View::escape(View::setting($settings, 'navigation.university_website', 'University website')) ?></a>
        </div>
    </footer>
</body>
</html>
