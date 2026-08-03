<?php

declare(strict_types=1);

use FastWebsite\Core\View;

$formatDate = static function (string $value): string {
    $timestamp = strtotime($value);

    return $timestamp === false ? $value : date('j M Y, H:i', $timestamp);
};
$statusLabel = static fn (string $status): string => ucfirst(
    str_replace('_', ' ', $status)
);
?>
<section class="admin-page-heading">
    <div>
        <p class="eyebrow">Overview</p>
        <h1>Welcome, <?= View::escape($user['name']) ?>.</h1>
        <p>Live information from the website database, filtered to your permissions and department scope.</p>
    </div>
    <span class="scope-pill"><?= View::escape($scopeLabel) ?></span>
</section>

<?php if (is_string($success) && $success !== ''): ?>
    <div class="success-alert" role="status"><?= View::escape($success) ?></div>
<?php endif; ?>

<section aria-labelledby="content-counts">
    <h2 class="section-title" id="content-counts">Content at a glance</h2>
    <?php if ($counts === []): ?>
        <div class="admin-empty">No countable content modules are available for your current permissions.</div>
    <?php else: ?>
        <div class="metric-grid">
            <?php foreach ($counts as $count): ?>
                <a class="metric-card" href="<?= View::escape($baseUrl . $count['route']) ?>">
                    <span><?= View::escape($count['label']) ?></span>
                    <strong><?= number_format($count['value']) ?></strong>
                    <small>View module</small>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<div class="dashboard-grid">
    <section class="admin-panel" aria-labelledby="recent-heading">
        <div class="panel-heading">
            <div>
                <p class="eyebrow">Database activity</p>
                <h2 id="recent-heading">Recently updated</h2>
            </div>
        </div>
        <?php if ($recentContent === []): ?>
            <div class="admin-empty">No recent content is available in your scope.</div>
        <?php else: ?>
            <div class="activity-list">
                <?php foreach ($recentContent as $item): ?>
                    <article>
                        <div>
                            <span class="content-type"><?= View::escape($item['type']) ?></span>
                            <h3><?= View::escape($item['title']) ?></h3>
                            <time datetime="<?= View::escape($item['updated_at']) ?>">
                                <?= View::escape($formatDate($item['updated_at'])) ?>
                            </time>
                        </div>
                        <span class="status-badge status-<?= View::escape($item['status']) ?>">
                            <?= View::escape($statusLabel($item['status'])) ?>
                        </span>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <div class="queue-column">
        <section class="admin-panel" aria-labelledby="draft-heading">
            <div class="panel-heading">
                <div>
                    <p class="eyebrow">Work queue</p>
                    <h2 id="draft-heading">Drafts</h2>
                </div>
                <span class="queue-count"><?= count($draftQueue) ?></span>
            </div>
            <?php if ($draftQueue === []): ?>
                <p class="admin-empty compact">No drafts are waiting in your scope.</p>
            <?php else: ?>
                <ul class="queue-list">
                    <?php foreach ($draftQueue as $item): ?>
                        <li>
                            <span><?= View::escape($item['type']) ?></span>
                            <strong><?= View::escape($item['title']) ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <?php if ($canReview): ?>
            <section class="admin-panel" aria-labelledby="review-heading">
                <div class="panel-heading">
                    <div>
                        <p class="eyebrow">Editorial workflow</p>
                        <h2 id="review-heading">Awaiting review</h2>
                    </div>
                    <span class="queue-count"><?= count($reviewQueue) ?></span>
                </div>
                <?php if ($reviewQueue === []): ?>
                    <p class="admin-empty compact">Nothing is awaiting review in your scope.</p>
                <?php else: ?>
                    <ul class="queue-list">
                        <?php foreach ($reviewQueue as $item): ?>
                            <li>
                                <span><?= View::escape($item['type']) ?></span>
                                <strong><?= View::escape($item['title']) ?></strong>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>
</div>
