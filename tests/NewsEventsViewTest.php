<?php

declare(strict_types=1);

use FastWebsite\Core\View;

return static function (): void {
    $view = new View(dirname(__DIR__) . '/app/Views');
    $news = [
        'id' => 1, 'title' => 'FAST research story', 'slug' => 'fast-research-story',
        'summary' => 'A faculty research update.', 'body' => '<p>Full story body.</p>',
        'category_name' => 'Research', 'article_date' => '2026-08-04', 'is_featured' => 1,
        'media_path' => 'public/uploads/news.jpg', 'media_alt_text' => 'Researchers at FAST',
        'author_name' => 'FAST Communications',
    ];
    $newsIndex = $view->render('public/content/news-index', [
        'baseUrl' => '/fast/public/', 'siteContent' => [], 'items' => [$news],
        'announcements' => [[
            'title' => 'Scholarship call', 'summary' => 'Applications are open.',
            'announcement_type' => 'scholarship', 'link_url' => '/scholarships',
        ]],
    ], null);
    $newsShow = $view->render('public/content/news-show', [
        'baseUrl' => '/fast/public/', 'item' => $news, 'related' => [],
    ], null);

    $event = [
        'id' => 2, 'title' => 'FAST Innovation Forum', 'slug' => 'innovation-forum',
        'summary' => 'Meet researchers and innovators.', 'description' => '<p>Forum details.</p>',
        'category_name' => 'Conference', 'starts_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
        'ends_at' => date('Y-m-d H:i:s', strtotime('+30 days +4 hours')), 'event_status' => 'scheduled',
        'venue_name' => 'FAST Main Hall', 'campus' => null, 'building' => null, 'room' => null,
        'media_path' => 'public/uploads/event.jpg', 'media_alt_text' => 'Innovation forum audience',
        'registration_url' => '/register', 'registration_deadline' => null, 'directions' => null,
        'honorific_title' => null, 'first_name' => null, 'middle_name' => null, 'last_name' => null,
        'contact_name' => 'Events Office', 'contact_email' => 'events@example.test',
    ];
    $eventsIndex = $view->render('public/content/events-index', [
        'baseUrl' => '/fast/public/', 'siteContent' => [], 'items' => [$event],
    ], null);
    $eventShow = $view->render('public/content/event-show', [
        'baseUrl' => '/fast/public/', 'item' => $event, 'related' => [],
    ], null);

    foreach ([
        [$newsIndex, ['newsroom-feature', 'FAST research story', 'Scholarship call', 'announcement-rail']],
        [$newsShow, ['editorial-masthead', 'Story details', 'Full story body.']],
        [$eventsIndex, ['event-feature', 'Next at FAST', 'FAST Main Hall']],
        [$eventShow, ['event-detail-masthead', 'Event information', 'Register for this event']],
    ] as [$html, $fragments]) {
        foreach ($fragments as $fragment) {
            if (!str_contains($html, $fragment)) {
                throw new RuntimeException('News or events view is missing: ' . $fragment);
            }
        }
    }
};
