<?php

declare(strict_types=1);

use FastWebsite\Core\View;

$imageUrl = static fn (string $path): string => $baseUrl . ltrim(str_replace('public/', '', $path), '/');
$venue = (string) ($item['venue_name'] ?? trim(implode(', ', array_filter([$item['campus'] ?? null, $item['building'] ?? null, $item['room'] ?? null]))));
$contactName = trim(implode(' ', array_filter([$item['honorific_title'] ?? null, $item['first_name'] ?? null, $item['middle_name'] ?? null, $item['last_name'] ?? null]))) ?: (string) ($item['contact_name'] ?? '');
?>
<article class="event-detail-page">
    <header class="event-detail-masthead" data-feature-reveal>
        <div class="shell event-detail-masthead-grid">
            <div class="event-detail-copy">
                <a class="editorial-back-link" href="<?= View::escape($baseUrl) ?>events"><span aria-hidden="true">&larr;</span> All events</a>
                <p class="newsroom-meta"><?= View::escape($item['category_name']) ?> <span aria-hidden="true">/</span> <?= View::escape(ucfirst((string) $item['event_status'])) ?></p>
                <h1><?= View::escape($item['title']) ?></h1>
                <?php if (!empty($item['summary'])): ?><p class="editorial-deck"><?= View::escape($item['summary']) ?></p><?php endif; ?>
            </div>
            <div class="event-detail-visual">
                <?php if (!empty($item['media_path'])): ?><img src="<?= View::escape($imageUrl($item['media_path'])) ?>" alt="<?= View::escape($item['media_alt_text'] ?? '') ?>"><?php else: ?><span class="events-media-placeholder" aria-hidden="true">FAST Events</span><?php endif; ?>
                <time datetime="<?= View::escape(date('Y-m-d', strtotime((string) $item['starts_at']))) ?>" class="event-date-block"><strong><?= View::escape(date('d', strtotime((string) $item['starts_at']))) ?></strong><span><?= View::escape(strtoupper(date('M', strtotime((string) $item['starts_at'])))) ?></span></time>
            </div>
        </div>
    </header>

    <section class="event-detail-content section" data-feature-reveal>
        <div class="shell event-detail-grid">
            <div class="prose editorial-prose"><p class="eyebrow">About this event</p><?= View::richText($item['description'] ?? '') ?></div>
            <aside class="event-information-card" aria-labelledby="event-information-title">
                <p class="eyebrow">Plan your visit</p><h2 id="event-information-title">Event information</h2>
                <dl>
                    <div><dt>Date and time</dt><dd><?= View::escape(date('l, j F Y', strtotime((string) $item['starts_at']))) ?><br><?= View::escape(date('H:i', strtotime((string) $item['starts_at']))) ?> EAT<?php if (!empty($item['ends_at'])): ?><br>to <?= View::escape(date('j F Y, H:i', strtotime((string) $item['ends_at']))) ?> EAT<?php endif; ?></dd></div>
                    <?php if ($venue !== ''): ?><div><dt>Venue</dt><dd><?= View::escape($venue) ?><?php if (!empty($item['directions'])): ?><br><small><?= View::escape($item['directions']) ?></small><?php endif; ?></dd></div><?php endif; ?>
                    <?php if ($contactName !== '' || !empty($item['contact_email'])): ?><div><dt>Contact</dt><dd><?php if ($contactName !== ''): ?><?= View::escape($contactName) ?><?php endif; ?><?php if (!empty($item['contact_email'])): ?><br><a href="mailto:<?= View::escape($item['contact_email']) ?>"><?= View::escape($item['contact_email']) ?></a><?php endif; ?></dd></div><?php endif; ?>
                    <?php if (!empty($item['registration_deadline'])): ?><div><dt>Registration closes</dt><dd><?= View::escape(date('j F Y, H:i', strtotime((string) $item['registration_deadline']))) ?> EAT</dd></div><?php endif; ?>
                </dl>
                <?php if (!empty($item['registration_url'])): ?><a class="button button-primary" href="<?= View::escape($item['registration_url']) ?>">Register for this event</a><?php endif; ?>
            </aside>
        </div>
    </section>

    <?php if ($related !== []): ?>
        <section class="editorial-related section" data-feature-reveal><div class="shell"><div class="editorial-section-heading"><div><p class="eyebrow">Keep exploring</p><h2>More upcoming events</h2></div><a class="editorial-link" href="<?= View::escape($baseUrl) ?>events">Full calendar <span aria-hidden="true">&rarr;</span></a></div><div class="event-archive-grid">
            <?php foreach ($related as $event): ?><article><p class="newsroom-meta"><?= View::escape(date('j M Y · H:i', strtotime((string) $event['starts_at']))) ?></p><h3><a href="<?= View::escape($baseUrl) ?>events/<?= View::escape($event['slug']) ?>"><?= View::escape($event['title']) ?></a></h3><p><?= View::escape($event['category_name']) ?></p></article><?php endforeach; ?>
        </div></div></section>
    <?php endif; ?>
</article>
