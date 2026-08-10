<?php

declare(strict_types=1);

use FastWebsite\Core\View;

$path = is_string($programme['hero_path']) ? str_replace('\\', '/', $programme['hero_path']) : '';
$image = $path !== '' && preg_match('#^(?:https?:)?//#i', $path) !== 1
    ? $baseUrl . ltrim(preg_replace('#^public/#', '', $path) ?? $path, '/')
    : $baseUrl . 'assets/images/fast-building.png';
$imageAlt = trim((string) ($programme['hero_alt_text'] ?? '')) ?: (string) $programme['name'];
$duration = $programme['duration_text'] ?: ($programme['duration_years'] ? $programme['duration_years'] . ' years' : null);
$sections = [
    'Overview' => 'overview',
    'Why study this programme' => 'why_study',
    'Objectives' => 'objectives',
    'Learning outcomes' => 'learning_outcomes',
    'Entry requirements' => 'entry_requirements',
    'Career opportunities' => 'career_opportunities',
    'Practical training' => 'practical_training',
    'Accreditation' => 'accreditation',
];
$visibleSections = array_filter($sections, static fn (string $field): bool => is_string($programme[$field] ?? null) && trim((string) $programme[$field]) !== '');
$curriculumGroups = [];
if ($curriculum && $curriculumCourses) {
    foreach ($curriculumCourses as $course) {
        $curriculumGroups[(int) $course['study_year']][(int) $course['semester']][] = $course;
    }
}
$slugify = static fn (string $heading): string => trim(strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', trim($heading))), '-') ?: 'section';
?>
<header class="programme-detail-hero">
    <div class="shell programme-detail-hero-grid">
        <div class="programme-detail-copy" data-programme-reveal>
            <a class="programme-back-link" href="<?= View::escape($baseUrl) ?>programmes">← All academic programmes</a>
            <p class="eyebrow"><?= View::escape($programme['level_name']) ?></p>
            <h1><?= View::escape($programme['name']) ?></h1>
            <?php if ($programme['award_title']): ?><p class="lead"><?= View::escape($programme['award_title']) ?></p><?php endif; ?>
            <div class="programme-hero-facts"><?php foreach (array_filter([$programme['programme_code'], $duration, ucwords(str_replace('_', ' ', (string) $programme['study_mode']))]) as $fact): ?><span><?= View::escape($fact) ?></span><?php endforeach; ?></div>
        </div>
        <figure class="programme-detail-image" data-programme-reveal><img src="<?= View::escape($image) ?>" alt="<?= View::escape($imageAlt) ?>"></figure>
    </div>
</header>

<nav class="programme-section-nav" aria-label="On this programme page"><div class="shell"><span>On this page</span><?php foreach ($visibleSections as $heading => $field): ?><a href="#<?= View::escape($slugify($heading)) ?>"><?= View::escape($heading) ?></a><?php endforeach; ?><?php if ($curriculumGroups !== []): ?><a href="#course-structure">Course structure</a><?php endif; ?></div></nav>

<section class="programme-fact-strip"><div class="shell">
    <div data-programme-reveal><span>Duration</span><strong><?= View::escape($duration ?: 'Contact FAST') ?></strong></div>
    <div data-programme-reveal><span>Study mode</span><strong><?= View::escape(ucwords(str_replace('_', ' ', (string) $programme['study_mode']))) ?></strong></div>
    <div data-programme-reveal><span>Delivery</span><strong><?= View::escape(ucwords(str_replace('_', ' ', (string) $programme['delivery_mode']))) ?></strong></div>
    <div data-programme-reveal><span>Lead department</span><strong><?php if ($programme['department_name'] && $programme['department_slug']): ?><a href="<?= View::escape($baseUrl) ?>departments/<?= View::escape($programme['department_slug']) ?>"><?= View::escape(preg_replace('/^Department of /', '', (string) $programme['department_name']) ?? $programme['department_name']) ?></a><?php else: ?><?= View::escape($programme['department_name'] ?: 'FAST') ?><?php endif; ?></strong></div>
</div></section>

<section class="programme-detail-body"><div class="shell programme-detail-layout">
    <div class="programme-detail-content">
        <?php foreach ($visibleSections as $heading => $field): ?>
            <section class="programme-editorial-section<?= $field === 'entry_requirements' ? ' is-highlighted' : '' ?>" id="<?= View::escape($slugify($heading)) ?>" data-programme-reveal>
                <p class="eyebrow"><?= $field === 'entry_requirements' ? 'Before you apply' : 'Programme information' ?></p><h2><?= View::escape($heading) ?></h2><div class="prose"><?= View::richText($programme[$field]) ?></div>
            </section>
        <?php endforeach; ?>

        <?php if ($curriculum && $curriculumGroups !== []): ?>
            <section class="programme-editorial-section programme-curriculum" id="course-structure" data-programme-reveal>
                <p class="eyebrow">Curriculum</p><h2>Course structure</h2><p class="programme-curriculum-summary"><?= View::escape($curriculum['version_name']) ?><?php if ($curriculum['total_credit_units'] !== null): ?> · <?= View::escape(rtrim(rtrim((string) $curriculum['total_credit_units'], '0'), '.')) ?> total credit units<?php endif; ?></p>
                <div class="programme-curriculum-years"><?php foreach ($curriculumGroups as $year => $semesters): ?><details class="curriculum-year" <?= $year === array_key_first($curriculumGroups) ? 'open' : '' ?>><summary><span>Year <?= $year ?></span><small><?= count($semesters) ?> study period<?= count($semesters) === 1 ? '' : 's' ?></small></summary><div class="curriculum-year-content"><?php foreach ($semesters as $semester => $courses): ?><h3><?= $semester === 3 ? 'Recess term' : 'Semester ' . $semester ?></h3><div class="table-wrap"><table class="curriculum-table"><thead><tr><th>Code</th><th>Course</th><th>Type</th><th>CU</th></tr></thead><tbody><?php foreach ($courses as $course): ?><tr><td><strong><?= View::escape($course['course_code']) ?></strong></td><td><?= View::escape($course['title']) ?></td><td><?= View::escape(ucfirst($course['requirement_type'])) ?></td><td><?= View::escape(rtrim(rtrim((string) ($course['credit_units_override'] ?? $course['default_credit_units']), '0'), '.')) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endforeach; ?></div></details><?php endforeach; ?></div>
            </section>
        <?php endif; ?>
    </div>

    <aside class="programme-action-panel" data-programme-reveal>
        <p class="eyebrow">Your next step</p><h2>Interested in this programme?</h2><p>Review the entry requirements, explore the lead department, or continue to the official application information.</p>
        <?php if ($programme['application_url']): ?><a class="button button-primary" href="<?= View::escape($programme['application_url']) ?>">Apply for this programme</a><?php endif; ?>
        <?php if ($programme['department_name'] && $programme['department_slug']): ?><a class="programme-panel-link" href="<?= View::escape($baseUrl) ?>departments/<?= View::escape($programme['department_slug']) ?>">Explore the lead department →</a><?php endif; ?>
        <a class="programme-panel-link" href="<?= View::escape($baseUrl) ?>programmes">Compare all programmes →</a>
    </aside>
</div></section>
