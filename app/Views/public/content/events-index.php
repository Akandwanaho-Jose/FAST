<?php

declare(strict_types=1);

use FastWebsite\Core\View;

$now = time();
$upcoming = array_values(array_filter($items, static fn (array $event): bool => strtotime((string) $event['starts_at']) >= $now && $event['event_status'] !== 'cancelled'));
$past = array_values(array_filter($items, static fn (array $event): bool => strtotime((string) $event['starts_at']) < $now || $event['event_status'] === 'completed'));
$featured = $upcoming[0] ?? null;
$upcomingList = array_slice($upcoming, 1);
$imageUrl = static fn (string $path): string => $baseUrl . ltrim(str_replace('public/', '', $path), '/');
$venue = static fn (array $event): string => (string) ($event['venue_name'] ?? trim(implode(', ', array_filter([$event['campus'] ?? null, $event['building'] ?? null, $event['room'] ?? null]))));
?>
<div class="events-page">
    <header class="events-intro" data-feature-reveal>
        <div class="shell events-intro-grid">
            <div><p class="eyebrow"><?= View::escape(View::setting($siteContent ?? [], 'events.eyebrow', 'Meet, learn and connect')) ?></p><h1><?= View::escape(View::setting($siteContent ?? [], 'events.title', 'Events at FAST')) ?></h1></div>
            <div class="events-intro-copy"><p><?= View::escape(View::setting($siteContent ?? [], 'events.introduction', 'Lectures, exhibitions, conferences and community events from across the Faculty.')) ?></p><a class="editorial-link" href="<?= View::escape($baseUrl) ?>news">Read faculty news <span aria-hidden="true">&rarr;</span></a></div>
        </div>
    </header>

    <?php if ($featured !== null): ?>
        <section class="event-feature shell" data-feature-reveal aria-labelledby="next-event-title">
            <div class="event-feature-media">
                <?php if (!empty($featured['media_path'])): ?><img src="<?= View::escape($imageUrl($featured['media_path'])) ?>" alt="<?= View::escape($featured['media_alt_text'] ?? '') ?>"><?php else: ?><span class="events-media-placeholder" aria-hidden="true">FAST Events</span><?php endif; ?>
                <time datetime="<?= View::escape(date('Y-m-d', strtotime((string) $featured['starts_at']))) ?>" class="event-date-block"><strong><?= View::escape(date('d', strtotime((string) $featured['starts_at']))) ?></strong><span><?= View::escape(strtoupper(date('M', strtotime((string) $featured['starts_at'])))) ?></span></time>
            </div>
            <div class="event-feature-copy">
                <p class="newsroom-kicker">Next at FAST</p>
                <p class="newsroom-meta"><?= View::escape($featured['category_name']) ?></p>
                <h2 id="next-event-title"><a href="<?= View::escape($baseUrl) ?>events/<?= View::escape($featured['slug']) ?>"><?= View::escape($featured['title']) ?></a></h2>
                <?php if (!empty($featured['summary'])): ?><p><?= View::escape($featured['summary']) ?></p><?php endif; ?>
                <dl class="event-feature-facts"><div><dt>When</dt><dd><?= View::escape(date('l, j F Y · H:i', strtotime((string) $featured['starts_at']))) ?> EAT</dd></div><?php if ($venue($featured) !== ''): ?><div><dt>Where</dt><dd><?= View::escape($venue($featured)) ?></dd></div><?php endif; ?></dl>
                <a class="editorial-link" href="<?= View::escape($baseUrl) ?>events/<?= View::escape($featured['slug']) ?>">View event details <span aria-hidden="true">&rarr;</span></a>
            </div>
        </section>
    <?php endif; ?>

    <section class="event-directory section" data-feature-reveal>
        <div class="shell">
            <div class="editorial-section-heading"><div><p class="eyebrow">Faculty calendar</p><h2>Upcoming events</h2></div><span><?= count($upcoming) ?> scheduled</span></div>
            <?php if ($upcoming === []): ?>
                <div class="public-empty events-empty"><h2>The next event is being prepared.</h2><p><?= View::escape(View::setting($siteContent ?? [], 'events.empty', 'Published events will appear here.')) ?></p></div>
            <?php elseif ($upcomingList === []): ?>
                <div class="newsroom-single-note"><p>More events will be added to the calendar as they are confirmed.</p></div>
            <?php else: ?>
                <div class="event-list">
                    <?php foreach ($upcomingList as $event): ?>
                        <article class="event-list-item">
                            <time datetime="<?= View::escape(date('Y-m-d', strtotime((string) $event['starts_at']))) ?>" class="event-list-date"><strong><?= View::escape(date('d', strtotime((string) $event['starts_at']))) ?></strong><span><?= View::escape(strtoupper(date('M', strtotime((string) $event['starts_at'])))) ?></span><small><?= View::escape(date('Y', strtotime((string) $event['starts_at']))) ?></small></time>
                            <div class="event-list-copy"><p class="newsroom-meta"><?= View::escape($event['category_name']) ?> <span aria-hidden="true">/</span> <?= View::escape(date('H:i', strtotime((string) $event['starts_at']))) ?> EAT</p><h3><a href="<?= View::escape($baseUrl) ?>events/<?= View::escape($event['slug']) ?>"><?= View::escape($event['title']) ?></a></h3><?php if ($venue($event) !== ''): ?><p class="event-list-venue"><?= View::escape($venue($event)) ?></p><?php endif; ?></div>
                            <a class="event-list-arrow" href="<?= View::escape($baseUrl) ?>events/<?= View::escape($event['slug']) ?>" aria-label="View <?= View::escape($event['title']) ?>">&rarr;</a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if ($past !== []): ?>
        <section class="event-archive section" data-feature-reveal>
            <div class="shell"><div class="editorial-section-heading"><div><p class="eyebrow">From the calendar</p><h2>Past events</h2></div></div><div class="event-archive-grid">
                <?php foreach (array_slice(array_reverse($past), 0, 6) as $event): ?>
                    <article><p class="newsroom-meta"><?= View::escape(date('j M Y', strtotime((string) $event['starts_at']))) ?></p><h3><a href="<?= View::escape($baseUrl) ?>events/<?= View::escape($event['slug']) ?>"><?= View::escape($event['title']) ?></a></h3><p><?= View::escape($event['category_name']) ?></p></article>
                <?php endforeach; ?>
            </div></div>
        </section>
    <?php endif; ?>
</div>
