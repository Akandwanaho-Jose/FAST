<?php

declare(strict_types=1);

use FastWebsite\Core\View;

$name = (string) ($faculty['name'] ?? 'Faculty of Applied Sciences and Technology');
$overview = (string) ($faculty['overview'] ?? 'Discover teaching, research, innovation, and partnerships at FAST.');
$programmeCategoryCounts = $programmeCategoryCounts ?? ['undergraduate' => (int) ($counts['programmes'] ?? 0), 'postgraduate' => 0];
$sections = $sections ?? [];
$quickLinks = $quickLinks ?? [];
$settings = is_array($siteContent ?? null) ? $siteContent : [];
$libraryUrl = View::setting($settings, 'identity.library_url', 'https://elib.must.ac.ug/');
$elearningUrl = View::setting($settings, 'identity.elearning_url', 'https://vle.must.ac.ug/');

$mediaUrl = static function (mixed $path) use ($baseUrl): ?string {
    if (!is_string($path) || trim($path) === '' || preg_match('#^(?:https?:)?//#i', $path) === 1) {
        return null;
    }
    return $baseUrl . ltrim(preg_replace('#^public/#', '', str_replace('\\', '/', $path)) ?? $path, '/');
};
$actionUrl = static function (mixed $url) use ($baseUrl): string {
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $url) === 1) {
        return $url;
    }
    return rtrim($baseUrl, '/') . '/' . ltrim($url, '/');
};

if ($sections === []) {
    $sections = [
        ['section_key' => 'quick_links', 'heading' => 'Start your FAST journey', 'introduction' => '', 'display_order' => 10],
        ['section_key' => 'why_fast', 'heading' => 'Engineering knowledge for real-world impact', 'introduction' => $overview, 'display_order' => 20],
        ['section_key' => 'study', 'heading' => 'Choose your study pathway', 'introduction' => '', 'display_order' => 30],
        ['section_key' => 'departments', 'heading' => 'Explore our departments', 'introduction' => '', 'display_order' => 40],
        ['section_key' => 'research', 'heading' => 'Ideas developed for real-world impact', 'introduction' => '', 'display_order' => 50],
        ['section_key' => 'updates', 'heading' => 'What is happening at FAST', 'introduction' => '', 'display_order' => 60],
        ['section_key' => 'resources', 'heading' => 'Useful academic resources', 'introduction' => '', 'display_order' => 70],
        ['section_key' => 'admissions', 'heading' => 'Ready to build the future with FAST?', 'introduction' => '', 'display_order' => 80],
    ];
}
if ($quickLinks === []) {
    $quickLinks = [
        ['label' => 'Undergraduate programmes', 'description' => 'Explore bachelor’s degrees at FAST.', 'link_url' => '/programmes?category=undergraduate'],
        ['label' => 'Postgraduate programmes', 'description' => 'Discover advanced study pathways.', 'link_url' => '/programmes?category=postgraduate'],
        ['label' => 'Departments', 'description' => 'Find your engineering discipline.', 'link_url' => '/departments'],
        ['label' => 'Apply for admission', 'description' => 'Continue to MUST admissions.', 'link_url' => 'https://www.must.ac.ug/admissions/'],
        ['label' => 'Student resources', 'description' => 'Access documents and learning services.', 'link_url' => '/documents'],
    ];
}

$heroSlides = array_slice($slides, 0, 3);
if ($heroSlides === []) {
    $heroSlides[] = [
        'title' => $name, 'caption' => $overview,
        'file_path' => 'public/assets/images/fast-building.png', 'mobile_file_path' => null,
        'alt_text' => 'FAST building at the MUST Kihumuro Campus',
        'button_label' => 'Explore programmes', 'button_url' => '/programmes',
        'text_alignment' => 'left', 'overlay_strength' => 60,
    ];
}
$slideCount = count($heroSlides);
$featuredInnovation = $innovations[0] ?? null;
$featuredImpact = $impacts[0] ?? null;
$hasUpdates = $news !== [] || $events !== [] || $announcements !== [];
?>

<?php if ($announcements !== []): ?>
    <section class="announcement-strip" aria-label="Important announcement">
        <div class="shell announcement-inner">
            <?php $announcement = $announcements[0]; ?>
            <p><strong><?= View::escape(strtoupper((string) $announcement['announcement_type'])) ?></strong><span><?= View::escape($announcement['title']) ?></span></p>
            <?php if ($announcement['link_url']): ?><a href="<?= View::escape($actionUrl($announcement['link_url'])) ?>">View details →</a><?php endif; ?>
        </div>
    </section>
<?php endif; ?>

<section class="hero-carousel home-editorial-hero" aria-roledescription="carousel" aria-label="FAST highlights" data-carousel>
    <div class="hero-carousel-slides" aria-live="off">
        <?php foreach ($heroSlides as $index => $slide): ?>
            <?php
            $desktopImage = $mediaUrl($slide['file_path'] ?? null);
            $mobileImage = $mediaUrl($slide['mobile_file_path'] ?? null);
            $alignment = in_array($slide['text_alignment'] ?? '', ['left', 'centre', 'right'], true) ? (string) $slide['text_alignment'] : 'left';
            $overlay = max(20, min(80, (int) ($slide['overlay_strength'] ?? 60)));
            $overlayClass = 'overlay-' . (string) (round($overlay / 20) * 20);
            ?>
            <article class="hero-slide hero-align-<?= View::escape($alignment) ?> <?= View::escape($overlayClass) ?><?= $desktopImage !== null ? ' has-media' : '' ?><?= $index === 0 ? ' is-active' : '' ?>" data-carousel-slide role="group" aria-roledescription="slide" aria-label="<?= $index + 1 ?> of <?= $slideCount ?>" aria-hidden="<?= $index === 0 ? 'false' : 'true' ?>" <?= $index === 0 ? '' : 'hidden' ?>>
                <?php if ($desktopImage !== null): ?>
                    <picture class="hero-slide-media">
                        <?php if ($mobileImage !== null): ?><source media="(max-width: 600px)" srcset="<?= View::escape($mobileImage) ?>"><?php endif; ?>
                        <img src="<?= View::escape($desktopImage) ?>" alt="<?= View::escape($slide['alt_text'] ?? '') ?>" <?= $index === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>>
                    </picture>
                <?php endif; ?>
                <div class="shell hero-slide-inner"><div class="hero-slide-copy">
                    <p class="eyebrow"><?= View::escape(View::setting($settings, 'home.hero_eyebrow', 'Mbarara University of Science and Technology')) ?></p>
                    <h1><?= View::escape($slide['title'] ?? $name) ?></h1>
                    <p class="lead"><?= View::escape($slide['caption'] ?? $overview) ?></p>
                    <div class="actions">
                        <?php if (($slide['button_label'] ?? null) && ($slide['button_url'] ?? null)): ?><a class="button button-primary" href="<?= View::escape($actionUrl($slide['button_url'])) ?>"><?= View::escape($slide['button_label']) ?></a><?php endif; ?>
                        <a class="button button-hero-secondary" href="<?= View::escape($baseUrl) ?>departments"><?= View::escape(View::setting($settings, 'home.hero_secondary_label', 'Explore departments')) ?></a>
                    </div>
                </div></div>
            </article>
        <?php endforeach; ?>
    </div>
    <?php if ($slideCount > 1): ?>
        <div class="shell hero-carousel-controls">
            <div class="carousel-arrows"><button type="button" data-carousel-previous aria-label="Previous slide">←</button><button type="button" data-carousel-next aria-label="Next slide">→</button></div>
            <div class="carousel-dots" aria-label="Choose a slide"><?php foreach ($heroSlides as $index => $slide): ?><button type="button" data-carousel-dot="<?= $index ?>" aria-label="Show slide <?= $index + 1 ?>" aria-pressed="<?= $index === 0 ? 'true' : 'false' ?>"></button><?php endforeach; ?></div>
            <button class="carousel-pause" type="button" data-carousel-pause aria-pressed="false"><span aria-hidden="true">Ⅱ</span><span data-carousel-pause-label>Pause slides</span></button>
        </div>
    <?php endif; ?>
</section>

<?php foreach ($sections as $section): ?>
    <?php $key = (string) $section['section_key']; $heading = (string) ($section['heading'] ?? ''); $introduction = (string) ($section['introduction'] ?? ''); ?>

    <?php if ($key === 'quick_links'): ?>
        <section class="home-quick-actions" aria-labelledby="home-quick-actions-title"><div class="shell">
            <div class="home-quick-actions-heading"><p class="eyebrow"><?= View::escape(View::setting($settings, 'home.quick_eyebrow', 'Find your next step')) ?></p><h2 id="home-quick-actions-title"><?= View::escape($heading) ?></h2><?php if ($introduction !== ''): ?><p><?= View::escape($introduction) ?></p><?php endif; ?></div>
            <div class="home-quick-action-grid"><?php foreach ($quickLinks as $index => $link): ?><a href="<?= View::escape($actionUrl($link['link_url'])) ?>"><span>0<?= $index + 1 ?></span><strong><?= View::escape($link['label']) ?></strong><small><?= View::escape($link['description']) ?></small><b aria-hidden="true">→</b></a><?php endforeach; ?></div>
        </div></section>

    <?php elseif ($key === 'why_fast'): ?>
        <section class="section home-why-section"><div class="shell home-why-grid">
            <div class="home-why-copy"><p class="eyebrow"><?= View::escape(View::setting($settings, 'home.why_eyebrow', 'Why choose FAST?')) ?></p><h2><?= View::escape($heading) ?></h2><p class="lead"><?= View::escape($introduction !== '' ? $introduction : $overview) ?></p><a class="text-link" href="<?= View::escape($baseUrl) ?>departments"><?= View::escape(View::setting($settings, 'home.why_link_label', 'Discover the faculty →')) ?></a></div>
            <div class="home-proof-grid">
                <?php foreach ([['programmes', View::setting($settings, 'home.stat_programmes_label', 'Academic programmes'), View::setting($settings, 'home.stat_programmes_note', 'Practice-focused study')], ['departments', View::setting($settings, 'home.stat_departments_label', 'Departments'), View::setting($settings, 'home.stat_departments_note', 'Connected disciplines')], ['staff', View::setting($settings, 'home.stat_staff_label', 'Staff profiles'), View::setting($settings, 'home.stat_staff_note', 'Teachers and researchers')]] as [$countKey, $label, $note]): ?>
                    <?php if ((int) ($counts[$countKey] ?? 0) > 0): ?><article><strong><?= (int) $counts[$countKey] ?></strong><h3><?= View::escape($label) ?></h3><p><?= View::escape($note) ?></p></article><?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div></section>

    <?php elseif ($key === 'study'): ?>
        <section class="section home-study-pathways"><div class="shell">
            <div class="section-heading-row home-section-heading"><div><p class="eyebrow"><?= View::escape(View::setting($settings, 'home.study_eyebrow', 'Study at FAST')) ?></p><h2><?= View::escape($heading) ?></h2><?php if ($introduction !== ''): ?><p class="lead"><?= View::escape($introduction) ?></p><?php endif; ?></div><a class="text-link" href="<?= View::escape($baseUrl) ?>programmes"><?= View::escape(View::setting($settings, 'home.study_link_label', 'All programmes →')) ?></a></div>
            <div class="home-study-pathway-grid">
                <a href="<?= View::escape($baseUrl) ?>programmes?category=undergraduate"><?php if ((int) $programmeCategoryCounts['undergraduate'] > 0): ?><span class="home-pathway-number"><?= (int) $programmeCategoryCounts['undergraduate'] ?></span><?php endif; ?><p class="eyebrow"><?= View::escape(View::setting($settings, 'home.undergraduate_eyebrow', 'First degree')) ?></p><h3><?= View::escape(View::setting($settings, 'home.undergraduate_title', 'Undergraduate programmes')) ?></h3><p><?= View::escape(View::setting($settings, 'home.undergraduate_description', 'Build a strong engineering foundation through practical, professionally relevant study.')) ?></p><b><?= View::escape(View::setting($settings, 'home.undergraduate_link', 'Explore undergraduate study →')) ?></b></a>
                <a href="<?= View::escape($baseUrl) ?>programmes?category=postgraduate"><?php if ((int) $programmeCategoryCounts['postgraduate'] > 0): ?><span class="home-pathway-number"><?= (int) $programmeCategoryCounts['postgraduate'] ?></span><?php endif; ?><p class="eyebrow"><?= View::escape(View::setting($settings, 'home.postgraduate_eyebrow', 'Advanced study')) ?></p><h3><?= View::escape(View::setting($settings, 'home.postgraduate_title', 'Postgraduate programmes')) ?></h3><p><?= View::escape(View::setting($settings, 'home.postgraduate_description', 'Deepen specialist knowledge through advanced coursework and research opportunities.')) ?></p><b><?= View::escape(View::setting($settings, 'home.postgraduate_link', 'Explore postgraduate study →')) ?></b></a>
            </div>
        </div></section>

    <?php elseif ($key === 'departments' && $departments !== []): ?>
        <section class="section home-departments-showcase"><div class="shell">
            <div class="section-heading-row home-section-heading"><div><p class="eyebrow"><?= View::escape(View::setting($settings, 'home.departments_eyebrow', 'Academic communities')) ?></p><h2><?= View::escape($heading) ?></h2><?php if ($introduction !== ''): ?><p class="lead"><?= View::escape($introduction) ?></p><?php endif; ?></div><a class="text-link" href="<?= View::escape($baseUrl) ?>departments"><?= View::escape(View::setting($settings, 'home.departments_link', 'All departments →')) ?></a></div>
            <div class="home-department-showcase-grid"><?php foreach ($departments as $department): ?><?php $departmentImage = $mediaUrl($department['hero_path']); ?><a href="<?= View::escape($baseUrl) ?>departments/<?= View::escape($department['slug']) ?>"><?php if ($departmentImage): ?><img src="<?= View::escape($departmentImage) ?>" alt="<?= View::escape($department['hero_alt_text'] ?: '') ?>" loading="lazy"><?php else: ?><div class="home-department-placeholder" aria-hidden="true"><?= View::escape($department['short_name'] ?: View::setting($settings, 'identity.short_name', 'FAST')) ?></div><?php endif; ?><div><p class="eyebrow"><?= View::escape($department['short_name'] ?: View::setting($settings, 'identity.short_name', 'FAST')) ?></p><h3><?= View::escape(preg_replace('/^Department of /', '', (string) $department['name']) ?? $department['name']) ?></h3><span><?= View::escape(View::setting($settings, 'home.department_card_link', 'Explore department →')) ?></span></div></a><?php endforeach; ?></div>
        </div></section>

    <?php elseif ($key === 'research'): ?>
        <section class="section home-research-story"><div class="shell home-research-story-grid">
            <div class="home-research-feature">
                <?php if (is_array($featuredInnovation)): ?><?php $innovationImage = $mediaUrl($featuredInnovation['hero_path'] ?? null); ?><?php if ($innovationImage): ?><img src="<?= View::escape($innovationImage) ?>" alt="<?= View::escape($featuredInnovation['hero_alt_text'] ?? '') ?>" loading="lazy"><?php endif; ?><div><p class="eyebrow"><?= View::escape(View::setting($settings, 'home.featured_innovation_label', 'Featured innovation')) ?></p><h3><a href="<?= View::escape($baseUrl) ?>innovations/<?= View::escape($featuredInnovation['slug']) ?>"><?= View::escape($featuredInnovation['name']) ?></a></h3><p><?= View::escape(mb_strimwidth(strip_tags((string) $featuredInnovation['description']), 0, 220, '…')) ?></p></div><?php else: ?><div class="home-research-visual" aria-hidden="true"><span><?= View::escape(View::setting($settings, 'identity.short_name', 'FAST')) ?></span></div><?php endif; ?>
            </div>
            <div class="home-research-intro"><p class="eyebrow"><?= View::escape(View::setting($settings, 'home.research_eyebrow', 'Research and innovation')) ?></p><h2><?= View::escape($heading) ?></h2><p class="lead"><?= View::escape($introduction) ?></p><div class="home-research-links"><a href="<?= View::escape($baseUrl) ?>research/projects"><strong><?= View::escape(View::setting($settings, 'home.research_projects_title', 'Research projects')) ?></strong><span><?= View::escape(View::setting($settings, 'home.research_projects_description', 'From important questions to practical outcomes.')) ?></span><b>→</b></a><a href="<?= View::escape($baseUrl) ?>research/publications"><strong><?= View::escape(View::setting($settings, 'home.research_publications_title', 'Publications')) ?></strong><span><?= View::escape(View::setting($settings, 'home.research_publications_description', 'Knowledge shared by FAST researchers.')) ?></span><b>→</b></a><a href="<?= View::escape($baseUrl) ?>innovations"><strong><?= View::escape(View::setting($settings, 'home.research_innovations_title', 'Innovations')) ?></strong><span><?= View::escape(View::setting($settings, 'home.research_innovations_description', 'Technologies designed for real needs.')) ?></span><b>→</b></a></div></div>
        </div></section>

    <?php elseif ($key === 'updates' && $hasUpdates): ?>
        <section class="section home-information-hub"><div class="shell">
            <div class="section-heading-row home-section-heading"><div><p class="eyebrow"><?= View::escape(View::setting($settings, 'home.updates_eyebrow', 'Stay informed')) ?></p><h2><?= View::escape($heading) ?></h2><?php if ($introduction !== ''): ?><p class="lead"><?= View::escape($introduction) ?></p><?php endif; ?></div><a class="text-link" href="<?= View::escape($baseUrl) ?>news">All news →</a></div>
            <div class="home-information-grid">
                <?php if ($news !== []): ?><div class="home-news-panel"><h3><?= View::escape(View::setting($settings, 'home.updates_news_title', 'Latest news')) ?></h3><?php foreach ($news as $item): ?><article><?php $newsImage = $mediaUrl($item['media_path'] ?? null); ?><?php if ($newsImage): ?><img src="<?= View::escape($newsImage) ?>" alt="<?= View::escape($item['media_alt_text'] ?? '') ?>" loading="lazy"><?php endif; ?><div><p class="eyebrow"><?= View::escape($item['category_name']) ?> · <?= View::escape(date('j M Y', strtotime($item['article_date']))) ?></p><h4><a href="<?= View::escape($baseUrl) ?>news/<?= View::escape($item['slug']) ?>"><?= View::escape($item['title']) ?></a></h4></div></article><?php endforeach; ?></div><?php endif; ?>
                <?php if ($announcements !== []): ?><div class="home-notice-panel"><h3><?= View::escape(View::setting($settings, 'home.updates_notices_title', 'Notice board')) ?></h3><?php foreach (array_slice($announcements, 0, 4) as $notice): ?><article><p class="eyebrow"><?= View::escape($notice['announcement_type']) ?></p><h4><?= View::escape($notice['title']) ?></h4><?php if ($notice['link_url']): ?><a href="<?= View::escape($actionUrl($notice['link_url'])) ?>">View notice →</a><?php endif; ?></article><?php endforeach; ?></div><?php endif; ?>
                <?php if ($events !== []): ?><div class="home-events-panel"><h3><?= View::escape(View::setting($settings, 'home.updates_events_title', 'Upcoming events')) ?></h3><?php foreach ($events as $event): ?><article><time datetime="<?= View::escape(date('Y-m-d', strtotime($event['starts_at']))) ?>"><strong><?= View::escape(date('d', strtotime($event['starts_at']))) ?></strong><span><?= View::escape(date('M', strtotime($event['starts_at']))) ?></span></time><div><p class="eyebrow"><?= View::escape($event['category_name']) ?></p><h4><a href="<?= View::escape($baseUrl) ?>events/<?= View::escape($event['slug']) ?>"><?= View::escape($event['title']) ?></a></h4></div></article><?php endforeach; ?><a class="text-link" href="<?= View::escape($baseUrl) ?>events">All events →</a></div><?php endif; ?>
            </div>
        </div></section>

    <?php elseif ($key === 'resources'): ?>
        <section class="section home-resources-section"><div class="shell home-resources-grid"><div><p class="eyebrow"><?= View::escape(View::setting($settings, 'home.resources_eyebrow', 'Current students')) ?></p><h2><?= View::escape($heading) ?></h2><p class="lead"><?= View::escape($introduction) ?></p></div><div class="home-resource-links"><a href="<?= View::escape($baseUrl) ?>documents"><strong><?= View::escape(View::setting($settings, 'home.resource_documents_title', 'Academic documents')) ?></strong><span><?= View::escape(View::setting($settings, 'home.resource_documents_description', 'Handbooks, forms and faculty documents.')) ?></span></a><a href="<?= View::escape($elearningUrl) ?>"><strong><?= View::escape(View::setting($settings, 'home.resource_elearning_title', 'eLearning')) ?></strong><span><?= View::escape(View::setting($settings, 'home.resource_elearning_description', 'Continue to the MUST virtual learning environment.')) ?></span></a><a href="<?= View::escape($libraryUrl) ?>"><strong><?= View::escape(View::setting($settings, 'home.resource_library_title', 'MUST Library')) ?></strong><span><?= View::escape(View::setting($settings, 'home.resource_library_description', 'Search electronic and library resources.')) ?></span></a><a href="<?= View::escape($baseUrl) ?>staff"><strong><?= View::escape(View::setting($settings, 'home.resource_staff_title', 'Staff directory')) ?></strong><span><?= View::escape(View::setting($settings, 'home.resource_staff_description', 'Find a lecturer, researcher or administrator.')) ?></span></a></div></div></section>

    <?php elseif ($key === 'admissions'): ?>
        <section class="home-admissions-cta"><div class="shell"><p class="eyebrow"><?= View::escape(View::setting($settings, 'home.admissions_eyebrow', 'Your next step')) ?></p><h2><?= View::escape($heading) ?></h2><p class="lead"><?= View::escape($introduction) ?></p><div class="actions"><a class="button button-primary" href="<?= View::escape($baseUrl) ?>programmes"><?= View::escape(View::setting($settings, 'home.admissions_primary', 'Explore programmes')) ?></a><a class="button button-hero-secondary" href="<?= View::escape(View::setting($settings, 'home.admissions_url', 'https://www.must.ac.ug/admissions/')) ?>"><?= View::escape(View::setting($settings, 'home.admissions_secondary', 'Apply for admission')) ?></a><a class="text-link" href="<?= View::escape($baseUrl) ?>departments"><?= View::escape(View::setting($settings, 'home.admissions_tertiary', 'Meet our departments →')) ?></a></div></div></section>
    <?php endif; ?>
<?php endforeach; ?>
