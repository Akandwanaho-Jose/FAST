<?php

declare(strict_types=1);

use FastWebsite\Core\View;

$slug = (string) ($item['slug'] ?? '');
$isAbout = str_starts_with($currentPath ?? '', '/about/');
$featurePageSlugs = [
    'industrial-training',
    'community-outreach',
    'partnerships',
    'engineering-education',
    'student-life',
    'professional-bodies',
    'student-mentorship-programme',
    'faculty-mentorship-programme',
    'meet-our-mentors',
];
$isFeaturePage = in_array($slug, $featurePageSlugs, true);
$isStudentLifeLanding = $slug === 'student-life';
$relatedPages = is_array($relatedPages ?? null) ? $relatedPages : [];
$dean = is_array($dean ?? null) ? $dean : null;

$aboutPages = [
    'faculty-overview' => 'Faculty Overview',
    'history-of-the-faculty' => 'History',
    'deans-message' => 'Dean’s Message',
    'vision-and-mission' => 'Vision & Mission',
    'core-values' => 'Core Values',
];

$mediaUrl = static function (mixed $path) use ($baseUrl): ?string {
    if (!is_string($path) || trim($path) === '') {
        return null;
    }
    $path = str_replace('\\', '/', $path);
    if (preg_match('#^(?:https?:)?//#i', $path) === 1) {
        return $path;
    }
    return $baseUrl . ltrim(preg_replace('#^public/#', '', $path) ?? $path, '/');
};

$gallerySections = array_values(array_filter(
    $sections,
    static fn (array $section): bool => $section['section_type'] === 'gallery' && !empty($section['media_path'])
));
$contentSections = array_values(array_filter(
    $sections,
    static fn (array $section): bool => $section['section_type'] !== 'gallery'
));
$aboutDefaultHeroPath = View::setting($siteContent ?? [], 'about.default_hero_image', 'assets/images/fast-building.png');
$pageHeroImage = $mediaUrl($item['hero_path'] ?? null)
    ?? $mediaUrl($aboutDefaultHeroPath)
    ?? $baseUrl . 'assets/images/fast-building.png';

$deanName = '';
$deanImage = null;
$deanInitials = 'D';
if ($dean !== null) {
    $deanName = trim(implode(' ', array_filter([
        $dean['honorific_title'] ?? null,
        $dean['first_name'] ?? null,
        $dean['middle_name'] ?? null,
        $dean['last_name'] ?? null,
    ])));
    if (trim((string) ($dean['post_nominals'] ?? '')) !== '') {
        $deanName .= ', ' . trim((string) $dean['post_nominals']);
    }
    $deanInitials = mb_strtoupper(
        mb_substr((string) ($dean['first_name'] ?? ''), 0, 1)
        . mb_substr((string) ($dean['last_name'] ?? ''), 0, 1)
    );
    $deanImage = $mediaUrl($dean['profile_path'] ?? null);
}
?>

<?php if ($isStudentLifeLanding): ?>
    <header class="engagement-landing-hero student-life-landing-hero">
        <img src="<?= View::escape($pageHeroImage) ?>" alt="<?= View::escape($item['hero_alt_text'] ?? 'FAST student life') ?>">
        <div class="engagement-landing-overlay"></div>
        <div class="shell engagement-landing-hero-content" data-feature-reveal>
            <p class="eyebrow">Life at FAST</p><h1><?= View::escape($item['title']) ?></h1>
            <?php if ($item['meta_description']): ?><p class="lead"><?= View::escape($item['meta_description']) ?></p><?php endif; ?>
            <a href="#student-life-pathways">Explore student life <span aria-hidden="true">↓</span></a>
        </div>
        <div class="engagement-hero-marker"><strong><?= count($relatedPages) ?></strong><span>student pathways</span></div>
    </header>

    <nav class="feature-page-nav" aria-label="Student Life sections"><div class="shell">
        <span>On this page</span><a href="#student-life-pathways">Explore</a><a href="#student-experience">Student experience</a><a href="<?= View::escape($baseUrl) ?>professional-bodies">Professional bodies</a><a href="<?= View::escape($baseUrl) ?>meet-our-mentors">Mentors</a>
    </div></nav>

    <?php if ($relatedPages !== []): ?>
    <section class="engagement-pathways student-life-pathways" id="student-life-pathways"><div class="shell">
        <div class="engagement-section-heading" data-feature-reveal><div><p class="eyebrow">Beyond the classroom</p><h2>Learn, connect and lead</h2></div><p>Build professional networks, find guidance and take part in the wider FAST academic community.</p></div>
        <div class="engagement-pathway-grid">
            <?php foreach ($relatedPages as $index => $page): ?>
                <?php $cardImage = $mediaUrl($page['hero_path'] ?? null); ?>
                <article class="engagement-pathway-card" data-feature-reveal style="--feature-delay: <?= min(3, $index) * 100 ?>ms">
                    <a class="engagement-pathway-image" href="<?= View::escape($baseUrl . $page['slug']) ?>"><?php if ($cardImage !== null): ?><img src="<?= View::escape($cardImage) ?>" alt="<?= View::escape($page['hero_alt_text'] ?? '') ?>" loading="lazy"><?php else: ?><span aria-hidden="true">FAST</span><?php endif; ?></a>
                    <div><p class="eyebrow">Student Life</p><h3><a href="<?= View::escape($baseUrl . $page['slug']) ?>"><?= View::escape($page['title']) ?></a></h3><p><?= View::escape($page['meta_description'] ?? '') ?></p><a class="engagement-pathway-link" href="<?= View::escape($baseUrl . $page['slug']) ?>">Explore <?= View::escape(mb_strtolower($page['title'])) ?> <span>→</span></a></div>
                </article>
            <?php endforeach; ?>
        </div>
    </div></section>
    <?php endif; ?>

    <section class="student-life-experience" id="student-experience"><div class="shell feature-page-layout">
        <div class="feature-page-editorial">
            <div class="feature-page-heading" data-feature-reveal><p class="eyebrow">The FAST experience</p><h2>Engineering beyond the classroom</h2></div>
            <?php foreach ($contentSections as $index => $section): ?>
                <article class="feature-page-section" data-feature-reveal style="--feature-delay: <?= min(3, $index) * 100 ?>ms"><?php if ($section['body']): ?><div class="prose feature-page-prose"><?= View::richText($section['body']) ?></div><?php endif; ?></article>
            <?php endforeach; ?>
        </div>
        <aside class="feature-page-visual-rail" data-feature-reveal>
            <div class="feature-page-statement"><span aria-hidden="true">FAST</span><p>Create.<br>Connect.<br>Lead.</p></div>
            <?php foreach (array_slice($gallerySections, 0, 3) as $index => $section): ?><figure<?= $index === 0 ? ' id="student-life-gallery"' : '' ?>><img src="<?= View::escape($mediaUrl($section['media_path'])) ?>" alt="<?= View::escape($section['media_alt_text'] ?? '') ?>" loading="lazy"></figure><?php endforeach; ?>
        </aside>
    </div></section>

    <section class="feature-page-footer-panel" data-feature-reveal><div class="shell"><p class="eyebrow">Your FAST community</p><h2>Find your place at FAST</h2><div><a href="<?= View::escape($baseUrl) ?>professional-bodies">Professional bodies <span>→</span></a><a href="<?= View::escape($baseUrl) ?>student-mentorship-programme">Student mentorship <span>→</span></a><a href="<?= View::escape($baseUrl) ?>meet-our-mentors">Meet our mentors <span>→</span></a></div></div></section>
<?php elseif ($isFeaturePage): ?>
    <?php
    $featureContent = $contentSections;
    $featureGallery = array_slice($gallerySections, 0, 3);
    $featureHeading = trim((string) ($featureContent[0]['heading'] ?? '')) ?: (string) $item['title'];
    $featureLabel = trim((string) ($featureContent[0]['subheading'] ?? '')) ?: 'FAST in action';
    ?>
    <header class="feature-page-hero">
        <div class="feature-page-hero-copy" data-feature-reveal>
            <p class="eyebrow"><?= View::escape($featureLabel) ?></p>
            <h1><?= View::escape($item['title']) ?></h1>
            <?php if ($item['meta_description']): ?><p class="lead"><?= View::escape($item['meta_description']) ?></p><?php endif; ?>
            <a class="feature-page-scroll-link" href="#feature-content">Discover more <span aria-hidden="true">↓</span></a>
        </div>
        <figure class="feature-page-hero-image" data-feature-reveal>
            <img src="<?= View::escape($pageHeroImage) ?>" alt="<?= View::escape($item['hero_alt_text'] ?? $item['title']) ?>">
            <figcaption><span>Faculty of Applied Sciences and Technology</span><strong><?= View::escape($item['title']) ?></strong></figcaption>
        </figure>
    </header>

    <nav class="feature-page-nav" aria-label="On this page"><div class="shell">
        <span>Explore</span><a href="#feature-content">Overview</a>
        <?php if ($featureGallery !== []): ?><a href="#feature-gallery">Gallery</a><?php endif; ?>
        <a href="<?= View::escape($baseUrl) ?>about/faculty-overview">About FAST</a>
    </div></nav>

    <main class="feature-page-main" id="feature-content">
        <div class="shell feature-page-layout">
            <div class="feature-page-editorial">
                <div class="feature-page-heading" data-feature-reveal>
                    <p class="eyebrow"><?= View::escape($item['title']) ?></p>
                    <h2><?= View::escape($featureHeading) ?></h2>
                </div>
                <?php if ($featureContent === []): ?>
                    <article class="feature-page-section" data-feature-reveal><p>Content for this page is being prepared.</p></article>
                <?php else: ?>
                    <?php foreach ($featureContent as $index => $section): ?>
                        <?php $sectionImage = $mediaUrl($section['media_path'] ?? null); ?>
                        <article class="feature-page-section<?= $sectionImage !== null ? ' has-image' : '' ?>" data-feature-reveal style="--feature-delay: <?= min(4, $index) * 100 ?>ms">
                            <?php if ($index > 0 && $section['subheading']): ?><p class="eyebrow"><?= View::escape($section['subheading']) ?></p><?php endif; ?>
                            <?php if ($index > 0 && $section['heading']): ?><h2><?= View::escape($section['heading']) ?></h2><?php endif; ?>
                            <?php if ($sectionImage !== null): ?><img src="<?= View::escape($sectionImage) ?>" alt="<?= View::escape($section['media_alt_text'] ?? '') ?>" loading="lazy"><?php endif; ?>
                            <?php if ($section['body']): ?><div class="prose feature-page-prose"><?= View::richText($section['body']) ?></div><?php endif; ?>
                            <?php if ($section['button_label'] && $section['button_url']): ?><a class="button button-primary" href="<?= View::escape($section['button_url']) ?>"><?= View::escape($section['button_label']) ?></a><?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <aside class="feature-page-visual-rail" data-feature-reveal>
                <div class="feature-page-statement"><span aria-hidden="true">FAST</span><p>Knowledge applied.<br>Communities transformed.</p></div>
                <?php foreach ($featureGallery as $index => $section): ?>
                    <figure<?= $index === 0 ? ' id="feature-gallery"' : '' ?>><img src="<?= View::escape($mediaUrl($section['media_path'])) ?>" alt="<?= View::escape($section['media_alt_text'] ?? '') ?>" loading="lazy"></figure>
                <?php endforeach; ?>
            </aside>
        </div>
    </main>

    <section class="feature-page-footer-panel" data-feature-reveal><div class="shell">
        <p class="eyebrow">Connected learning</p><h2>Explore more of FAST</h2>
        <div><a href="<?= View::escape($baseUrl) ?>programmes">Academic programmes <span>→</span></a><a href="<?= View::escape($baseUrl) ?>research">Research &amp; innovation <span>→</span></a><a href="<?= View::escape($baseUrl) ?>staff">Our people <span>→</span></a></div>
    </div></section>
<?php elseif (!$isAbout): ?>
    <header class="page-heading department-heading"><div class="shell">
        <p class="eyebrow">FAST</p>
        <h1><?= View::escape($item['title']) ?></h1>
        <?php if ($item['meta_description']): ?><p class="lead"><?= View::escape($item['meta_description']) ?></p><?php endif; ?>
    </div></header>
    <?php foreach ($sections as $section): ?>
        <section class="section"><div class="shell">
            <?php if ($section['subheading']): ?><p class="eyebrow"><?= View::escape($section['subheading']) ?></p><?php endif; ?>
            <?php if ($section['heading']): ?><h2><?= View::escape($section['heading']) ?></h2><?php endif; ?>
            <?php if ($mediaUrl($section['media_path'] ?? null)): ?><img src="<?= View::escape($mediaUrl($section['media_path'])) ?>" alt="<?= View::escape($section['media_alt_text'] ?? '') ?>"><?php endif; ?>
            <?php if ($section['body']): ?><div class="prose"><?= View::richText($section['body']) ?></div><?php endif; ?>
            <?php if ($section['button_label'] && $section['button_url']): ?><a class="button button-primary" href="<?= View::escape($section['button_url']) ?>"><?= View::escape($section['button_label']) ?></a><?php endif; ?>
        </div></section>
    <?php endforeach; ?>
<?php else: ?>
    <header class="about-masthead">
        <div class="shell about-masthead-grid">
            <div class="about-masthead-copy" data-about-reveal>
                <p class="eyebrow">About FAST</p>
                <h1><?= View::escape($item['title']) ?></h1>
                <?php if ($item['meta_description']): ?><p class="lead"><?= View::escape($item['meta_description']) ?></p><?php endif; ?>
            </div>
            <figure class="about-masthead-image">
                <img src="<?= View::escape($pageHeroImage) ?>" alt="<?= View::escape($item['hero_alt_text'] ?? 'The FAST building at MUST') ?>">
            </figure>
        </div>
    </header>

    <?php if ($slug === 'history-of-the-faculty' && $gallerySections !== []): ?>
        <section class="section about-history-gallery-section"><div class="shell">
            <p class="eyebrow">In pictures</p>
            <div class="about-history-gallery about-history-gallery-<?= min(4, count($gallerySections)) ?>" data-about-reveal>
                <?php foreach (array_slice($gallerySections, 0, 4) as $index => $section): ?>
                    <figure><img src="<?= View::escape($mediaUrl($section['media_path'])) ?>" alt="<?= View::escape($section['media_alt_text'] ?? '') ?>" <?= $index > 0 ? 'loading="lazy"' : '' ?>></figure>
                <?php endforeach; ?>
            </div>
        </div></section>
    <?php endif; ?>

    <nav class="about-page-navigation" aria-label="About FAST pages"><div class="shell">
        <?php foreach ($aboutPages as $aboutSlug => $label): ?>
            <a href="<?= View::escape($baseUrl) ?>about/<?= View::escape($aboutSlug) ?>" <?= $slug === $aboutSlug ? 'aria-current="page"' : '' ?>><?= View::escape($label) ?></a>
        <?php endforeach; ?>
    </div></nav>

    <?php if ($slug === 'deans-message'): ?>
        <section class="section about-dean-section"><div class="shell about-dean-grid" data-about-reveal>
            <aside class="about-dean-profile">
                <?php if ($deanImage !== null): ?>
                    <img src="<?= View::escape($deanImage) ?>" alt="<?= View::escape($dean['profile_alt_text'] ?? ('Portrait of ' . $deanName)) ?>">
                <?php else: ?>
                    <div class="about-dean-placeholder" aria-hidden="true"><span><?= View::escape($deanInitials) ?></span></div>
                <?php endif; ?>
                <div>
                    <p class="eyebrow"><?= View::escape($dean['position_title'] ?? 'Dean of FAST') ?></p>
                    <h2><?= View::escape($deanName !== '' ? $deanName : 'Faculty Dean') ?></h2>
                    <?php if ($dean !== null): ?><a href="<?= View::escape($baseUrl) ?>staff/<?= View::escape($dean['slug']) ?>">View staff profile →</a><?php endif; ?>
                </div>
            </aside>
            <article class="about-dean-message">
                <p class="eyebrow">From the Dean</p>
                <?php if ($contentSections === []): ?>
                    <h2>Welcome to FAST</h2><p>The Dean’s message is being prepared.</p>
                <?php else: ?>
                    <?php foreach ($contentSections as $section): ?>
                        <?php if ($section['subheading']): ?><p class="eyebrow"><?= View::escape($section['subheading']) ?></p><?php endif; ?>
                        <?php if ($section['heading']): ?><h2><?= View::escape($section['heading']) ?></h2><?php endif; ?>
                        <?php if ($section['body']): ?><div class="prose"><?= View::richText($section['body']) ?></div><?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </article>
        </div></section>
    <?php elseif ($slug === 'vision-and-mission'): ?>
        <?php
        $directionCards = [];
        foreach (['vision' => 'Vision', 'mission' => 'Mission'] as $needle => $fallbackHeading) {
            $match = null;
            foreach ($contentSections as $section) {
                if (str_contains(mb_strtolower((string) ($section['heading'] ?? '')), $needle)) {
                    $match = $section;
                    break;
                }
            }
            $directionCards[] = $match ?? ['heading' => $fallbackHeading, 'subheading' => $needle === 'vision' ? 'Where we are going' : 'What we are here to do', 'body' => null, 'media_path' => null];
        }
        ?>
        <section class="section about-direction-section"><div class="shell about-direction-grid">
            <?php foreach ($directionCards as $index => $section): ?>
                <article class="about-direction-card" data-about-reveal style="--about-delay: <?= $index * 140 ?>ms">
                    <span class="about-direction-number" aria-hidden="true">0<?= $index + 1 ?></span>
                    <p class="eyebrow"><?= View::escape($section['subheading'] ?? ($index === 0 ? 'Our direction' : 'Our purpose')) ?></p>
                    <h2><?= View::escape($section['heading'] ?? ($index === 0 ? 'Vision' : 'Mission')) ?></h2>
                    <?php if (!empty($section['body'])): ?><div class="prose"><?= View::richText($section['body']) ?></div><?php else: ?><p>The approved <?= $index === 0 ? 'vision' : 'mission' ?> statement will appear here.</p><?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div></section>
    <?php else: ?>
        <section class="section about-content-section"><div class="shell about-content-stack">
            <?php if ($contentSections === []): ?>
                <article class="about-editorial-card" data-about-reveal><p class="eyebrow">Content in progress</p><h2><?= View::escape($item['title']) ?></h2><p>This faculty page is being prepared.</p></article>
            <?php else: ?>
                <?php foreach ($contentSections as $index => $section): ?>
                    <?php
                    // Each section can carry a couple of "additional photos"
                    // (set in the page builder next to its main Image field) that
                    // stack beside it here, so the photo column can grow to match
                    // a long paragraph instead of one image floating in leftover
                    // white space - see SiteContentRepository::sections().
                    $sectionImage = $mediaUrl($section['media_path'] ?? null);
                    $photos = [];
                    if ($sectionImage !== null) {
                        $photos[] = ['url' => $sectionImage, 'alt' => (string) ($section['media_alt_text'] ?? '')];
                        foreach ($section['extra_photos'] ?? [] as $extraPhoto) {
                            $extraUrl = $mediaUrl($extraPhoto['media_path'] ?? null);
                            if ($extraUrl !== null) {
                                $photos[] = ['url' => $extraUrl, 'alt' => (string) ($extraPhoto['media_alt_text'] ?? '')];
                            }
                        }
                    }
                    ?>
                    <article class="about-editorial-card section-type-<?= View::escape($section['section_type']) ?><?= $photos !== [] ? ' has-image' : '' ?><?= count($photos) > 1 ? ' has-photo-stack' : '' ?>" data-about-reveal style="--about-delay: <?= min(4, $index) * 100 ?>ms">
                        <?php if ($photos !== []): ?>
                            <div class="about-editorial-photos">
                                <?php foreach ($photos as $photo): ?>
                                    <figure><img src="<?= View::escape($photo['url']) ?>" alt="<?= View::escape($photo['alt']) ?>" loading="lazy"></figure>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <div>
                            <?php if ($section['subheading']): ?><p class="eyebrow"><?= View::escape($section['subheading']) ?></p><?php endif; ?>
                            <?php if ($section['heading']): ?><h2><?= View::escape($section['heading']) ?></h2><?php endif; ?>
                            <?php if ($section['body']): ?><div class="prose"><?= View::richText($section['body']) ?></div><?php endif; ?>
                            <?php if ($section['button_label'] && $section['button_url']): ?><a class="button button-primary" href="<?= View::escape($section['button_url']) ?>"><?= View::escape($section['button_label']) ?></a><?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div></section>
    <?php endif; ?>
<?php endif; ?>
