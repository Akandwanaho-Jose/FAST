<?php

declare(strict_types=1);

use FastWebsite\Core\View;

$headOfDepartment = $headOfDepartment ?? null;
$programmes = $programmes ?? [];
$location = array_filter([
    $department['campus'],
    $department['building'],
    $department['floor'],
    $department['room'],
], static fn (mixed $value): bool => is_string($value) && trim($value) !== '');

$mediaUrl = static function (mixed $path) use ($baseUrl): ?string {
    if (!is_string($path) || trim($path) === '') {
        return null;
    }
    $path = str_replace('\\', '/', $path);
    if (preg_match('#^(?:https?:)?//#i', $path) === 1) {
        return null;
    }
    return $baseUrl . ltrim(preg_replace('#^public/#', '', $path) ?? $path, '/');
};

$heroUrl = $mediaUrl($department['hero_path']);
$headName = null;
$headImageUrl = null;
if (is_array($headOfDepartment)) {
    $headName = implode(' ', array_filter([
        $headOfDepartment['honorific_title'],
        $headOfDepartment['first_name'],
        $headOfDepartment['middle_name'],
        $headOfDepartment['last_name'],
    ], static fn (mixed $part): bool => is_string($part) && trim($part) !== ''));
    if (is_string($headOfDepartment['post_nominals']) && trim($headOfDepartment['post_nominals']) !== '') {
        $headName .= ', ' . trim($headOfDepartment['post_nominals']);
    }
    $headImageUrl = $mediaUrl($headOfDepartment['profile_path']);
}

$hasDirection = array_filter([
    $department['vision'],
    $department['mission'],
    $department['strategic_direction'],
], static fn (mixed $value): bool => is_string($value) && trim($value) !== '') !== [];
?>

<section class="department-masthead">
    <div class="shell department-masthead-grid">
        <div class="department-masthead-copy">
            <a class="department-back-link" href="<?= View::escape($baseUrl) ?>departments">← All departments</a>
            <p class="eyebrow"><?= View::escape($department['faculty_name']) ?></p>
            <h1><?= View::escape($department['name']) ?></h1>
            <?php if (is_string($department['overview']) && trim($department['overview']) !== ''): ?>
                <p class="lead"><?= View::escape(mb_strimwidth(strip_tags($department['overview']), 0, 260, '…')) ?></p>
            <?php endif; ?>
            <div class="actions">
                <?php if ($programmes !== []): ?><a class="button button-primary" href="#programmes">Explore programmes</a><?php endif; ?>
                <a class="button button-secondary" href="<?= View::escape($baseUrl) ?>staff?department=<?= (int) $department['id'] ?>">Meet our people</a>
            </div>
        </div>
        <div class="department-masthead-visual">
            <?php if ($heroUrl !== null): ?>
                <img src="<?= View::escape($heroUrl) ?>" alt="<?= View::escape($department['hero_alt_text'] ?? '') ?>">
            <?php else: ?>
                <div class="department-masthead-placeholder" aria-hidden="true"><span><?= View::escape($department['short_name'] ?? 'FAST') ?></span></div>
            <?php endif; ?>
        </div>
    </div>
</section>

<nav class="department-section-nav" aria-label="On this department page">
    <div class="shell">
        <a href="#overview">Overview</a>
        <?php if ($programmes !== []): ?><a href="#programmes">Programmes</a><?php endif; ?>
        <?php if (is_array($headOfDepartment) || (is_string($department['hod_message']) && trim($department['hod_message']) !== '')): ?><a href="#leadership">Leadership</a><?php endif; ?>
        <?php if ($hasDirection): ?><a href="#direction">Vision &amp; direction</a><?php endif; ?>
        <a href="#contact">Contact</a>
    </div>
</nav>

<section class="section department-overview" id="overview">
    <div class="shell department-overview-grid">
        <div class="department-reading-column">
            <p class="eyebrow">About the department</p>
            <h2>Overview</h2>
            <?php if (is_string($department['overview']) && trim($department['overview']) !== ''): ?>
                <div class="prose department-prose"><?= View::richText($department['overview']) ?></div>
            <?php else: ?>
                <p class="lead">The department overview is being prepared.</p>
            <?php endif; ?>

            <?php if (is_string($department['history']) && trim($department['history']) !== ''): ?>
                <div class="department-history">
                    <h3>Our history</h3>
                    <div class="prose department-prose"><?= View::richText($department['history']) ?></div>
                </div>
            <?php endif; ?>
        </div>

        <aside class="department-contact-panel" id="contact">
            <p class="eyebrow">Contact</p>
            <h2><?= View::escape($department['short_name'] ?? $department['name']) ?></h2>
            <dl>
                <?php if (is_string($department['email']) && trim($department['email']) !== ''): ?>
                    <div><dt>Email</dt><dd><a href="mailto:<?= View::escape($department['email']) ?>"><?= View::escape($department['email']) ?></a></dd></div>
                <?php endif; ?>
                <?php if (is_string($department['phone']) && trim($department['phone']) !== ''): ?>
                    <div><dt>Phone</dt><dd><?= View::escape($department['phone']) ?></dd></div>
                <?php endif; ?>
                <?php if ($location !== []): ?>
                    <div><dt>Location</dt><dd><?= View::escape(implode(', ', $location)) ?></dd></div>
                <?php endif; ?>
            </dl>
            <?php if ((!is_string($department['email']) || trim($department['email']) === '')
                && (!is_string($department['phone']) || trim($department['phone']) === '')
                && $location === []
            ): ?>
                <p>Verified contact information has not yet been published.</p>
            <?php endif; ?>
            <a class="text-link" href="<?= View::escape($baseUrl) ?>staff?department=<?= (int) $department['id'] ?>">View department staff →</a>
        </aside>
    </div>
</section>

<?php if ($programmes !== []): ?>
    <section class="section department-programmes-section" id="programmes">
        <div class="shell">
            <div class="section-heading-row">
                <div><p class="eyebrow">Study with us</p><h2>Programmes</h2></div>
                <a class="text-link" href="<?= View::escape($baseUrl) ?>programmes?department=<?= View::escape($department['slug']) ?>">All department programmes →</a>
            </div>
            <div class="department-programme-list">
                <?php foreach ($programmes as $programme): ?>
                    <article class="department-programme-card">
                        <p class="eyebrow"><?= View::escape($programme['level_name']) ?></p>
                        <h3><a href="<?= View::escape($baseUrl) ?>programmes/<?= View::escape($programme['slug']) ?>"><?= View::escape($programme['name']) ?></a></h3>
                        <p><?= View::escape(implode(' · ', array_filter([$programme['programme_code'], $programme['duration_text']]))) ?></p>
                        <a class="text-link" href="<?= View::escape($baseUrl) ?>programmes/<?= View::escape($programme['slug']) ?>">Programme details →</a>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if (is_array($headOfDepartment) || (is_string($department['hod_message']) && trim($department['hod_message']) !== '')): ?>
    <section class="section department-leadership-section" id="leadership">
        <div class="shell">
            <div class="department-section-heading">
                <p class="eyebrow">Department leadership</p>
                <h2>A message from the Head of Department</h2>
            </div>
            <div class="department-leadership-grid">
                <?php if (is_array($headOfDepartment)): ?>
                    <aside class="department-head-card">
                        <?php if ($headImageUrl !== null): ?>
                            <img src="<?= View::escape($headImageUrl) ?>" alt="<?= View::escape(trim((string) $headOfDepartment['profile_alt_text']) !== '' ? $headOfDepartment['profile_alt_text'] : 'Portrait of ' . $headName) ?>">
                        <?php endif; ?>
                        <div>
                            <h3><?= View::escape($headName) ?></h3>
                            <p><?= View::escape($headOfDepartment['position_title']) ?></p>
                            <?php if (is_string($headOfDepartment['institutional_email']) && trim($headOfDepartment['institutional_email']) !== ''): ?><a href="mailto:<?= View::escape($headOfDepartment['institutional_email']) ?>">Email the Head</a><?php endif; ?>
                            <a href="<?= View::escape($baseUrl) ?>staff/<?= View::escape($headOfDepartment['slug']) ?>">View profile →</a>
                        </div>
                    </aside>
                <?php endif; ?>

                <div class="department-head-message">
                    <?php if (is_string($department['hod_message']) && trim($department['hod_message']) !== ''): ?>
                        <div class="prose department-prose"><?= View::richText($department['hod_message']) ?></div>
                    <?php elseif (is_array($headOfDepartment) && is_string($headOfDepartment['short_biography']) && trim($headOfDepartment['short_biography']) !== ''): ?>
                        <p><?= View::escape($headOfDepartment['short_biography']) ?></p>
                    <?php else: ?>
                        <p>The Head of Department’s message is being prepared.</p>
                    <?php endif; ?>
                    <?php if ($headName !== null && (!is_string($department['hod_message']) || trim($department['hod_message']) === '')): ?><p class="department-message-signature"><?= View::escape($headName) ?></p><?php endif; ?>
                </div>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php if ($hasDirection): ?>
    <section class="section department-direction-section" id="direction">
        <div class="shell">
            <div class="department-section-heading"><p class="eyebrow">Where we are going</p><h2>Vision and direction</h2></div>
            <div class="department-direction-grid">
                <?php foreach (['Vision' => $department['vision'], 'Mission' => $department['mission'], 'Strategic direction' => $department['strategic_direction']] as $heading => $value): ?>
                    <?php if (is_string($value) && trim($value) !== ''): ?>
                        <article><h3><?= View::escape($heading) ?></h3><div class="prose"><?= View::richText($value) ?></div></article>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>
