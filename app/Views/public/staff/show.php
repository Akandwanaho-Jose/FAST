<?php

declare(strict_types=1);

use FastWebsite\Core\View;

$name = trim(implode(' ', array_filter([
    $profile['honorific_title'],
    $profile['first_name'],
    $profile['middle_name'],
    $profile['last_name'],
])));
if (is_string($profile['post_nominals']) && trim($profile['post_nominals']) !== '') {
    $name .= ', ' . $profile['post_nominals'];
}
$path = is_string($profile['profile_path']) ? str_replace('\\', '/', $profile['profile_path']) : '';
$image = $path !== '' && preg_match('#^(?:https?:)?//#i', $path) !== 1
    ? $baseUrl . ltrim(preg_replace('#^public/#', '', $path) ?? $path, '/')
    : null;
$initials = mb_strtoupper(
    mb_substr((string) $profile['first_name'], 0, 1)
    . mb_substr((string) $profile['last_name'], 0, 1)
);
$location = implode(', ', array_filter([
    $profile['room'] ? 'Room ' . $profile['room'] : null,
    $profile['floor'],
    $profile['building'],
    $profile['campus'],
]));
$linkLabels = [
    'orcid' => 'ORCID',
    'google_scholar' => 'Google Scholar',
    'researchgate' => 'ResearchGate',
    'linkedin' => 'LinkedIn',
    'institutional_repository' => 'Institutional repository',
    'personal_website' => 'Personal website',
    'other' => 'External profile',
];
?>

<section class="staff-profile-hero">
    <div class="shell staff-profile-heading">
        <?php if ($image !== null): ?>
            <img src="<?= View::escape($image) ?>" alt="<?= View::escape($profile['profile_alt_text'] ?: 'Portrait of ' . $name) ?>">
        <?php else: ?>
            <div class="staff-profile-placeholder" aria-hidden="true"><span><?= View::escape($initials) ?></span></div>
        <?php endif; ?>
        <div class="staff-profile-intro">
            <p class="eyebrow"><?= View::escape($profile['position_title'] ?? ucfirst((string) $profile['staff_category'])) ?></p>
            <h1><?= View::escape($name) ?></h1>
            <p class="lead"><?= View::escape($profile['department_name'] ?? $profile['faculty_name']) ?></p>
            <?php if ($expertise !== []): ?>
                <ul class="staff-profile-expertise" aria-label="Areas of expertise">
                    <?php foreach ($expertise as $area): ?><li><?= View::escape($area['name']) ?></li><?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <?php if ((int) $profile['supervision_available'] === 1): ?>
                <p class="profile-availability">Available for student supervision</p>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="section staff-profile-content">
    <div class="shell public-record-grid">
        <div class="public-record-main">
            <?php foreach ([
                'Profile' => $profile['short_biography'],
                'Biography' => $profile['biography'],
                'Research' => $profile['research_summary'],
                'Teaching' => $profile['teaching_summary'],
                'Supervision interests' => $profile['supervision_interests'],
            ] as $heading => $text): ?>
                <?php if (is_string($text) && trim($text) !== ''): ?>
                    <section class="public-content-section">
                        <h2><?= View::escape($heading) ?></h2>
                        <div class="prose"><?= View::richText($text) ?></div>
                    </section>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php if ($qualifications !== []): ?>
                <section class="public-content-section">
                    <h2>Qualifications</h2>
                    <ol class="qualification-list">
                        <?php foreach ($qualifications as $qualification): ?>
                            <li>
                                <strong><?= View::escape($qualification['qualification']) ?><?= $qualification['field_of_study'] ? ' in ' . View::escape($qualification['field_of_study']) : '' ?></strong>
                                <?php if ($qualification['institution'] || $qualification['completion_year']): ?>
                                    <span><?= View::escape(implode(', ', array_filter([$qualification['institution'], $qualification['country'], $qualification['completion_year']]))) ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </section>
            <?php endif; ?>

            <?php if ($researchUnits !== []): ?>
                <section class="public-content-section">
                    <h2>Research groups and units</h2>
                    <div class="profile-related-grid">
                        <?php foreach ($researchUnits as $unit): ?>
                            <article>
                                <p class="eyebrow"><?= View::escape($unit['role_title'] ?: ucwords(str_replace('_', ' ', $unit['membership_role']))) ?></p>
                                <h3><a href="<?= View::escape($baseUrl) ?>research/<?= View::escape($unit['slug']) ?>"><?= View::escape($unit['name']) ?></a></h3>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($projects !== []): ?>
                <section class="public-content-section">
                    <h2>Research projects</h2>
                    <div class="profile-related-grid">
                        <?php foreach ($projects as $project): ?>
                            <article>
                                <p class="eyebrow"><?= View::escape(ucfirst((string) $project['project_status'])) ?> project</p>
                                <h3><a href="<?= View::escape($baseUrl) ?>research/projects/<?= View::escape($project['slug']) ?>"><?= View::escape($project['title']) ?></a></h3>
                                <p><?= View::escape($project['role_title'] ?: ucwords(str_replace('_', ' ', $project['project_role']))) ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($publications !== []): ?>
                <section class="public-content-section">
                    <div class="section-heading-row">
                        <h2>Selected publications</h2>
                        <a href="<?= View::escape($baseUrl) ?>research/publications">Browse all publications</a>
                    </div>
                    <div class="publication-list">
                        <?php foreach ($publications as $publication): ?>
                            <article class="publication-card">
                                <p class="eyebrow"><?= View::escape($publication['type_name']) ?><?php if ($publication['publication_year']): ?> · <?= (int) $publication['publication_year'] ?><?php endif; ?></p>
                                <h3><a href="<?= View::escape($baseUrl) ?>research/publications/<?= View::escape($publication['slug']) ?>"><?= View::escape($publication['title']) ?></a></h3>
                                <?php if ($publication['journal_name']): ?><p><?= View::escape($publication['journal_name']) ?></p><?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
        </div>

        <aside class="public-contact-card staff-contact-card">
            <p class="eyebrow">Contact and profile</p>
            <h2><?= View::escape($profile['position_title'] ?? 'Staff member') ?></h2>
            <?php if ($profile['department_name']): ?><p><strong>Department</strong><br><?= View::escape($profile['department_name']) ?></p><?php endif; ?>
            <?php if ($profile['institutional_email']): ?><p><strong>Email</strong><br><a href="mailto:<?= View::escape($profile['institutional_email']) ?>"><?= View::escape($profile['institutional_email']) ?></a></p><?php endif; ?>
            <?php if ($profile['public_phone']): ?><p><strong>Phone</strong><br><a href="tel:<?= View::escape(preg_replace('/[^+0-9]/', '', (string) $profile['public_phone']) ?? '') ?>"><?= View::escape($profile['public_phone']) ?></a></p><?php endif; ?>
            <?php if ($location !== ''): ?><p><strong>Office</strong><br><?= View::escape($location) ?></p><?php endif; ?>
            <?php if ($profile['consultation_hours']): ?><p><strong>Consultation</strong><br><?= View::escape($profile['consultation_hours']) ?></p><?php endif; ?>
            <?php if ($links !== []): ?>
                <div class="profile-links">
                    <strong>Research profiles</strong>
                    <?php foreach ($links as $link): ?>
                        <a href="<?= View::escape($link['url']) ?>"><?= View::escape($link['label'] ?: ($linkLabels[$link['link_type']] ?? 'External profile')) ?> <span aria-hidden="true">↗</span></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <a class="back-to-directory" href="<?= View::escape($baseUrl) ?>staff">← Back to staff directory</a>
        </aside>
    </div>
</section>
