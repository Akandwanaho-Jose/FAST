<?php

declare(strict_types=1);

use FastWebsite\Core\View;

$name = (string) ($faculty['name'] ?? 'Faculty of Applied Sciences and Technology');
$overview = (string) ($faculty['overview'] ?? 'Discover teaching, research, innovation, and partnerships at FAST.');
$programmeCategoryCounts = $programmeCategoryCounts ?? ['undergraduate' => (int) ($counts['programmes'] ?? 0), 'postgraduate' => 0];
$sections = $sections ?? [];
$quickLinks = $quickLinks ?? [];
$historyPage = is_array($historyPage ?? null) ? $historyPage : null;
$historySections = is_array($historySections ?? null) ? $historySections : [];
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
        ['section_key' => 'research', 'heading' => 'Ideas developed for real-world impact', 'introduction' => '', 'display_order' => 50],
        ['section_key' => 'updates', 'heading' => 'What is happening at FAST', 'introduction' => '', 'display_order' => 60],
    ];
}
if ($quickLinks === []) {
    $quickLinks = [
        ['label' => 'Undergraduate programmes', 'description' => 'Explore bachelor’s degrees at FAST.', 'link_url' => '/programmes?category=undergraduate'],
        ['label' => 'Postgraduate programmes', 'description' => 'Discover advanced study pathways.', 'link_url' => '/programmes?category=postgraduate'],
        ['label' => 'Departments', 'description' => 'Find your engineering discipline.', 'link_url' => '/departments'],
        ['label' => 'Partnerships', 'description' => 'Connect through collaboration, outreach and shared impact.', 'link_url' => '/engagement'],
        ['label' => 'Research Labs', 'description' => 'Explore research projects, publications and practical innovations.', 'link_url' => '/research'],
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
$heroInterval = max(7, min(20, (int) View::setting($settings, 'home.hero_interval_seconds', '10')));
$featuredInnovation = $innovations[0] ?? null;
$featuredImpact = $impacts[0] ?? null;
$hasUpdates = $news !== [] || $events !== [];
?>

<section class="home-hero-stage">
<div class="shell home-hero-layout">
<section class="hero-carousel home-editorial-hero" aria-roledescription="carousel" aria-label="FAST highlights" data-carousel data-carousel-interval="<?= $heroInterval ?>">
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
                <div class="hero-slide-inner"><div class="hero-slide-copy">
                    <p class="eyebrow"><?= View::escape(View::setting($settings, 'home.hero_eyebrow', 'Mbarara University of Science and Technology')) ?></p>
                    <?php if ($index === 0): ?>
                        <h1 class="hero-slide-title"><?= View::escape($slide['title'] ?? $name) ?></h1>
                    <?php else: ?>
                        <h2 class="hero-slide-title"><?= View::escape($slide['title'] ?? $name) ?></h2>
                    <?php endif; ?>
                    <?php if (($slide['button_label'] ?? null) && ($slide['button_url'] ?? null)): ?>
                        <div class="actions"><a class="button button-primary" href="<?= View::escape($actionUrl($slide['button_url'])) ?>"><?= View::escape($slide['button_label']) ?></a></div>
                    <?php endif; ?>
                </div></div>
            </article>
        <?php endforeach; ?>
    </div>
    <?php if ($slideCount > 1): ?>
        <div class="hero-carousel-controls">
            <div class="carousel-dots" aria-label="Choose a slide"><?php foreach ($heroSlides as $index => $slide): ?><button type="button" data-carousel-dot="<?= $index ?>" aria-label="Show slide <?= $index + 1 ?>" aria-pressed="<?= $index === 0 ? 'true' : 'false' ?>"></button><?php endforeach; ?></div>
        </div>
    <?php endif; ?>
</section>

<aside class="home-hero-departments" aria-labelledby="home-hero-departments-title">
    <header>
        <p class="eyebrow"><?= View::escape(View::setting($settings, 'home.hero_departments_eyebrow', 'Find your discipline')) ?></p>
        <h2 id="home-hero-departments-title"><?= View::escape(View::setting($settings, 'home.hero_departments_title', 'Explore our departments')) ?></h2>
        <p><?= View::escape(View::setting($settings, 'home.hero_departments_introduction', 'Discover the academic communities shaping engineering, technology and applied science at FAST.')) ?></p>
    </header>
    <?php if ($departments !== []): ?>
        <div class="home-hero-department-grid">
            <?php foreach (array_slice($departments, 0, 5) as $departmentIndex => $department): ?>
                <?php
                $departmentImage = $mediaUrl($department['hero_path'] ?? null);
                $departmentSlug = trim((string) ($department['slug'] ?? ''));
                $departmentShort = trim((string) ($department['short_name'] ?? '')) ?: 'FAST';
                $departmentName = trim((string) ($department['name'] ?? 'Academic department'));
                $departmentLabel = preg_replace('/^Department of /', '', $departmentName) ?: $departmentName;
                $departmentUrl = $baseUrl . 'departments' . ($departmentSlug !== '' ? '/' . $departmentSlug : '');
                ?>
                <a class="<?= $departmentIndex === 0 ? 'is-featured' : '' ?>" href="<?= View::escape($departmentUrl) ?>">
                    <?php if ($departmentImage !== null): ?>
                        <img src="<?= View::escape($departmentImage) ?>" alt="<?= View::escape($department['hero_alt_text'] ?? '') ?>" loading="<?= $departmentIndex === 0 ? 'eager' : 'lazy' ?>">
                    <?php else: ?>
                        <span class="home-hero-department-placeholder" aria-hidden="true"><?= View::escape($departmentShort) ?></span>
                    <?php endif; ?>
                    <span class="home-hero-department-copy"><small><?= View::escape($departmentShort) ?></small><strong><?= View::escape($departmentLabel) ?></strong></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <a class="home-hero-all-departments" href="<?= View::escape($baseUrl) ?>departments"><?= View::escape(View::setting($settings, 'home.hero_departments_link', 'View all departments')) ?> <span aria-hidden="true">&rarr;</span></a>
</aside>
</div>
</section>

<?php foreach ($sections as $section): ?>
    <?php $key = (string) $section['section_key']; $heading = (string) ($section['heading'] ?? ''); $introduction = (string) ($section['introduction'] ?? ''); ?>

    <?php if ($key === 'quick_links'): ?>
        <?php
        $automaticQuickImages = array_values(array_filter(array_merge(array_column($heroSlides, 'file_path'), array_column($departments, 'hero_path'))));
        $quickVisuals = [];
        foreach ($quickLinks as $index => $link) {
            $quickVisual = $mediaUrl($link['image_path'] ?? ($automaticQuickImages[$index % max(1, count($automaticQuickImages))] ?? null));
            if ($quickVisual !== null && !in_array($quickVisual, $quickVisuals, true)) {
                $quickVisuals[] = $quickVisual;
            }
            if (count($quickVisuals) === 3) {
                break;
            }
        }
        $quickHeadingWords = preg_split('/\s+/', trim($heading)) ?: [];
        $quickHeadingAccent = (string) array_pop($quickHeadingWords);
        $quickHeadingLead = implode(' ', $quickHeadingWords);
        ?>
        <section class="home-quick-actions" aria-labelledby="home-quick-actions-title" data-study-reveal><div class="shell home-quick-actions-layout">
            <div class="home-quick-visual-grid" aria-hidden="true"><?php foreach ($quickVisuals as $visual): ?><figure><img src="<?= View::escape($visual) ?>" alt="" loading="lazy"></figure><?php endforeach; ?></div>
            <div class="home-quick-actions-content">
                <p class="eyebrow"><?= View::escape(View::setting($settings, 'home.quick_eyebrow', 'Find your next step')) ?></p>
                <h2 id="home-quick-actions-title"><span class="home-study-title-lead"><?= View::escape($quickHeadingLead) ?></span> <span class="home-study-title-accent"><?= View::escape($quickHeadingAccent) ?></span></h2>
                <?php if ($introduction !== ''): ?><p class="lead"><?= View::escape($introduction) ?></p><?php endif; ?>
                <div class="home-quick-link-grid"><?php foreach ($quickLinks as $index => $link): ?><a href="<?= View::escape($actionUrl($link['link_url'])) ?>"><span>0<?= $index + 1 ?></span><strong><?= View::escape($link['label']) ?></strong><b aria-hidden="true">→</b></a><?php endforeach; ?></div>
            </div>
        </div></section>

    <?php elseif ($key === 'why_fast'): ?>
        <?php
        $historyBody = '';
        $historyImages = [];
        foreach ($historySections as $historySection) {
            if ($historyBody === '' && trim((string) ($historySection['body'] ?? '')) !== '') {
                $historyBody = trim(preg_replace('/\s+/', ' ', strip_tags((string) $historySection['body'])) ?? '');
            }
            $historyImage = $mediaUrl($historySection['media_path'] ?? null);
            if ($historyImage !== null && !in_array($historyImage, $historyImages, true)) {
                $historyImages[] = $historyImage;
            }
        }
        $whyImageCandidates = array_values(array_unique(array_filter(array_merge(
            $historyImages,
            array_map($mediaUrl, array_column($departments, 'hero_path')),
            array_map($mediaUrl, array_column($heroSlides, 'file_path'))
        ))));
        $whyImages = $whyImageCandidates;
        $historyExcerpt = $historyBody !== ''
            ? mb_strimwidth($historyBody, 0, 520, '…')
            : View::setting($settings, 'home.why_history_empty', 'FAST has grown as a community of teaching, research and practical innovation serving Uganda and the region.');
        $whyBrandMessage = View::setting($settings, 'home.why_brand_message', 'Rooted in applied science. Built for Uganda. Creating solutions for tomorrow.');
        $whyBrandLines = preg_split('/(?<=[.!?])\s+/', trim($whyBrandMessage)) ?: [$whyBrandMessage];
        ?>
        <section class="section home-why-section" data-why-reveal><div class="shell">
            <header class="home-why-heading">
                <div><p class="eyebrow"><?= View::escape(View::setting($settings, 'home.why_eyebrow', 'Why choose FAST?')) ?></p><h2><?= View::escape($heading) ?></h2></div>
                <p class="home-why-audience-line"><?= View::escape(View::setting($settings, 'home.why_audience_line', 'For students, researchers, industry and development partners.')) ?></p>
            </header>
            <div class="home-why-bento">
                <article class="home-why-brand-card"><span class="home-why-brand-mark" aria-hidden="true">FAST</span><p class="eyebrow"><?= View::escape(View::setting($settings, 'home.why_signature', 'Education · Research · Partnership')) ?></p><p class="home-why-brand-message"><?php foreach ($whyBrandLines as $brandLine): ?><span><?= View::escape($brandLine) ?></span><?php endforeach; ?></p><div class="home-why-audience-links"><a href="<?= View::escape($baseUrl) ?>programmes"><?= View::escape(View::setting($settings, 'home.why_student_cta', 'Explore programmes →')) ?></a><?php if ($navAvailability['engagement'] ?? true): ?><a href="<?= View::escape($baseUrl) ?>engagement"><?= View::escape(View::setting($settings, 'home.why_partner_cta', 'Partner with FAST →')) ?></a><?php endif; ?></div></article>
                <div class="home-why-visuals" data-why-gallery><?php foreach ($whyImages as $whyIndex => $whyImage): ?><figure data-why-gallery-item<?= $whyIndex < 3 ? ' data-slot="' . ($whyIndex + 1) . '"' : ' hidden' ?>><img src="<?= View::escape($whyImage) ?>" alt="" loading="lazy"></figure><?php endforeach; ?></div>
                <article class="home-why-history-card">
                    <p class="eyebrow"><?= View::escape(View::setting($settings, 'home.why_history_eyebrow', 'Our story')) ?></p>
                    <h3><?= View::escape((string) ($historyPage['title'] ?? 'History of FAST')) ?></h3>
                    <p><?= View::escape($historyExcerpt) ?></p>
                    <a href="<?= View::escape($baseUrl) ?>about/history-of-the-faculty"><?= View::escape(View::setting($settings, 'home.why_history_link', 'Read the full history →')) ?></a>
                </article>
                <div class="home-proof-grid">
                    <?php foreach ([['programmes', View::setting($settings, 'home.stat_programmes_label', 'Academic programmes'), View::setting($settings, 'home.stat_programmes_note', 'Practice-focused study')], ['departments', View::setting($settings, 'home.stat_departments_label', 'Departments'), View::setting($settings, 'home.stat_departments_note', 'Connected disciplines')], ['staff', View::setting($settings, 'home.stat_staff_label', 'Staff profiles'), View::setting($settings, 'home.stat_staff_note', 'Teachers and researchers')]] as [$countKey, $label, $note]): ?>
                        <?php if ((int) ($counts[$countKey] ?? 0) > 0): ?><article><strong><?= (int) $counts[$countKey] ?></strong><h3><?= View::escape($label) ?></h3><p><?= View::escape($note) ?></p></article><?php endif; ?>
                    <?php endforeach; ?>
                </div>
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
                <?php if ($events !== []): ?><div class="home-events-panel"><h3><?= View::escape(View::setting($settings, 'home.updates_events_title', 'Upcoming events')) ?></h3><?php foreach ($events as $event): ?><article><time datetime="<?= View::escape(date('Y-m-d', strtotime($event['starts_at']))) ?>"><strong><?= View::escape(date('d', strtotime($event['starts_at']))) ?></strong><span><?= View::escape(date('M', strtotime($event['starts_at']))) ?></span></time><div><p class="eyebrow"><?= View::escape($event['category_name']) ?></p><h4><a href="<?= View::escape($baseUrl) ?>events/<?= View::escape($event['slug']) ?>"><?= View::escape($event['title']) ?></a></h4></div></article><?php endforeach; ?><a class="text-link" href="<?= View::escape($baseUrl) ?>events">All events →</a></div><?php endif; ?>
            </div>
        </div></section>

    <?php elseif ($key === 'resources'): ?>
        <section class="section home-resources-section"><div class="shell home-resources-grid"><div><p class="eyebrow"><?= View::escape(View::setting($settings, 'home.resources_eyebrow', 'Current students')) ?></p><h2><?= View::escape($heading) ?></h2><p class="lead"><?= View::escape($introduction) ?></p></div><div class="home-resource-links"><a href="<?= View::escape($baseUrl) ?>documents"><strong><?= View::escape(View::setting($settings, 'home.resource_documents_title', 'Academic documents')) ?></strong><span><?= View::escape(View::setting($settings, 'home.resource_documents_description', 'Handbooks, forms and faculty documents.')) ?></span></a><a href="<?= View::escape($elearningUrl) ?>"><strong><?= View::escape(View::setting($settings, 'home.resource_elearning_title', 'eLearning')) ?></strong><span><?= View::escape(View::setting($settings, 'home.resource_elearning_description', 'Continue to the MUST virtual learning environment.')) ?></span></a><a href="<?= View::escape($libraryUrl) ?>"><strong><?= View::escape(View::setting($settings, 'home.resource_library_title', 'MUST Library')) ?></strong><span><?= View::escape(View::setting($settings, 'home.resource_library_description', 'Search electronic and library resources.')) ?></span></a><a href="<?= View::escape($baseUrl) ?>staff"><strong><?= View::escape(View::setting($settings, 'home.resource_staff_title', 'Staff directory')) ?></strong><span><?= View::escape(View::setting($settings, 'home.resource_staff_description', 'Find a lecturer, researcher or administrator.')) ?></span></a></div></div></section>

    <?php elseif ($key === 'admissions'): ?>
        <section class="home-admissions-cta"><div class="shell"><p class="eyebrow"><?= View::escape(View::setting($settings, 'home.admissions_eyebrow', 'Your next step')) ?></p><h2><?= View::escape($heading) ?></h2><p class="lead"><?= View::escape($introduction) ?></p><div class="actions"><a class="button button-primary" href="<?= View::escape($baseUrl) ?>programmes"><?= View::escape(View::setting($settings, 'home.admissions_primary', 'Explore programmes')) ?></a><a class="button button-hero-secondary" href="<?= View::escape(View::setting($settings, 'home.admissions_url', 'https://www.must.ac.ug/admissions/')) ?>"><?= View::escape(View::setting($settings, 'home.admissions_secondary', 'Apply for admission')) ?></a><a class="text-link" href="<?= View::escape($baseUrl) ?>departments"><?= View::escape(View::setting($settings, 'home.admissions_tertiary', 'Meet our departments →')) ?></a></div></div></section>
    <?php endif; ?>
<?php endforeach; ?>
