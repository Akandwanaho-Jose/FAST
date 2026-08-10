<?php

declare(strict_types=1);

use FastWebsite\Core\View;

$title = isset($pageTitle) ? (string) $pageTitle : 'FAST Website';
$description = isset($metaDescription)
    ? (string) $metaDescription
    : 'Faculty of Applied Sciences and Technology website.';
$path = isset($currentPath) ? (string) $currentPath : '';
$urlBase = isset($baseUrl) ? (string) $baseUrl : '/';
$assetVersion = '20260806-04';
$settings = is_array($siteContent ?? null) ? $siteContent : [];
$navigationData = is_array($siteNavigation ?? null) ? $siteNavigation : [];
$programmeDepartments = $navigationData['departments'] ?? [];
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
    $pathStartsWith('/about') => ['About FAST', 'about'],
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
    <link rel="icon" type="image/webp" href="<?= View::escape($urlBase) ?>assets/images/must-logo.webp?v=20260806-01">
    <link rel="apple-touch-icon" href="<?= View::escape($urlBase) ?>assets/images/must-logo.webp?v=20260806-01">
    <link rel="stylesheet" href="<?= View::escape($urlBase) ?>assets/css/app.css?v=<?= View::escape($assetVersion) ?>">
    <script src="<?= View::escape($urlBase) ?>assets/js/site.js?v=<?= View::escape($assetVersion) ?>" defer></script>
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <header class="site-header">
        <div class="header-utility-band">
            <div class="shell header-utility-row">
                <nav aria-label="University links">
                    <a href="<?= View::escape($universityUrl) ?>"><?= View::escape(View::setting($settings, 'navigation.must_home', 'MUST Home')) ?></a>
                    <a href="<?= View::escape($elearningUrl) ?>"><?= View::escape(View::setting($settings, 'navigation.elearning', 'eLearning')) ?></a>
                    <a href="<?= View::escape($libraryUrl) ?>"><?= View::escape(View::setting($settings, 'navigation.library_short', 'Library')) ?></a>
                </nav>
            </div>
        </div>

        <div class="header-identity-band">
            <div class="shell header-identity-row">
                <a class="identity" href="<?= View::escape($urlBase) ?>" aria-label="<?= View::escape($brandShort) ?> website home">
                    <img class="identity-logo" src="<?= View::escape($urlBase) ?>assets/images/must-logo.webp" alt="" width="104" height="104">
                    <span class="identity-copy">
                       <strong class="identity-name"><?= View::escape($facultyName) ?></strong>
                         <!-- <span class="identity-acronym"><?= View::escape($brandShort) ?></span>
                    </span> -->
                </a>

                <button class="navigation-toggle" type="button" aria-expanded="false" aria-controls="primary-navigation">
                    <span>Menu</span>
                    <span class="navigation-toggle-icon" aria-hidden="true"><i></i><i></i><i></i></span>
                </button>
            </div>
        </div>

        <div class="header-motto-band"><div class="shell"><strong><?= View::escape(View::setting($settings, 'identity.motto', 'Succeed We Must')) ?></strong></div></div>

        <div class="header-navigation-band">
            <div class="shell header-navigation-row">
                <nav class="primary-navigation" id="primary-navigation" aria-label="Primary navigation">
                <a class="nav-link" <?= $path === '/' ? 'aria-current="page"' : '' ?> href="<?= View::escape($urlBase) ?>">Home</a>

                <details class="nav-group"<?= $sectionPath === 'about' ? ' data-current="true"' : '' ?>>
                    <summary><?= View::escape(View::setting($settings, 'navigation.about', 'About FAST')) ?></summary>
                    <div class="nav-submenu">
                        <?php foreach ([
                            'faculty-overview' => 'Faculty Overview',
                            'history-of-the-faculty' => 'History of the Faculty',
                            'deans-message' => 'Dean\'s Message',
                            'vision-and-mission' => 'Vision and Mission',
                            'core-values' => 'Core Values',
                        ] as $aboutSlug => $aboutLabel): ?>
                            <a <?= $path === '/about/' . $aboutSlug ? 'aria-current="page"' : '' ?> href="<?= View::escape($urlBase) ?>about/<?= View::escape($aboutSlug) ?>"><?= View::escape($aboutLabel) ?></a>
                        <?php endforeach; ?>
                    </div>
                </details>

                <details class="nav-group"<?= $sectionPath === 'programmes' ? ' data-current="true"' : '' ?>>
                    <summary><?= View::escape(View::setting($settings, 'navigation.programmes', 'Programmes')) ?></summary>
                    <div class="nav-submenu nav-submenu-programmes">
                        <a <?= $pathStartsWith('/programmes') && $activeProgrammeCategory === '' ? 'aria-current="page"' : '' ?> href="<?= View::escape($urlBase) ?>programmes"><?= View::escape(View::setting($settings, 'navigation.all_programmes', 'All programmes')) ?></a>
                        <a <?= $activeProgrammeCategory === 'undergraduate' ? 'aria-current="page"' : '' ?> href="<?= View::escape($urlBase) ?>programmes?category=undergraduate"><?= View::escape(View::setting($settings, 'navigation.undergraduate', 'Undergraduate programmes')) ?></a>
                        <a <?= $activeProgrammeCategory === 'postgraduate' ? 'aria-current="page"' : '' ?> href="<?= View::escape($urlBase) ?>programmes?category=postgraduate"><?= View::escape(View::setting($settings, 'navigation.postgraduate', 'Postgraduate programmes')) ?></a>
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

                <a class="nav-link" <?= $pathStartsWith('/staff') ? 'aria-current="page"' : '' ?> href="<?= View::escape($urlBase) ?>staff"><?= View::escape(View::setting($settings, 'navigation.staff', 'Our Staff')) ?></a>
                <details class="nav-group"<?= $pathStartsWith('/engagement', '/impact', '/industrial-training', '/community-outreach', '/partnerships', '/engineering-education') ? ' data-current="true"' : '' ?>>
                    <summary><?= View::escape(View::setting($settings, 'navigation.partnerships', 'Partnerships')) ?></summary>
                    <div class="nav-submenu nav-submenu-wide">
                        <a href="<?= View::escape($urlBase) ?>engagement">Partnerships and impact</a>
                        <a href="<?= View::escape($urlBase) ?>industrial-training">Industrial training</a>
                        <a href="<?= View::escape($urlBase) ?>community-outreach">Community outreach</a>
                        <a href="<?= View::escape($urlBase) ?>partnerships">Our partnerships</a>
                        <a href="<?= View::escape($urlBase) ?>engineering-education">Engineering education</a>
                    </div>
                </details>

                <details class="nav-group"<?= $pathStartsWith('/student-life', '/professional-bodies', '/student-mentorship-programme', '/faculty-mentorship-programme', '/meet-our-mentors') ? ' data-current="true"' : '' ?>>
                    <summary>Student Life</summary>
                    <div class="nav-submenu nav-submenu-end">
                        <a href="<?= View::escape($urlBase) ?>student-life">Student experience</a>
                        <a href="<?= View::escape($urlBase) ?>professional-bodies">Professional bodies</a>
                        <a href="<?= View::escape($urlBase) ?>student-mentorship-programme">Student mentorship</a>
                        <a href="<?= View::escape($urlBase) ?>faculty-mentorship-programme">Faculty mentorship</a>
                        <a href="<?= View::escape($urlBase) ?>meet-our-mentors">Meet our mentors</a>
                    </div>
                </details>

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

    <footer class="site-footer" id="site-footer">
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
                    <a href="<?= View::escape($urlBase) ?>about/faculty-overview">About FAST</a>
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
                    <a href="<?= View::escape($urlBase) ?>student-life">Student life</a>
                    <a href="<?= View::escape($urlBase) ?>student-mentorship-programme">Mentorship</a>
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
