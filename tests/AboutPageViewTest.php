<?php

declare(strict_types=1);

use FastWebsite\Core\View;

return static function (): void {
    $view = new View(dirname(__DIR__) . '/app/Views');
    $base = ['baseUrl' => '/fast/public/'];

    $history = $view->render('public/content/page', [
        ...$base,
        'currentPath' => '/about/history-of-the-faculty',
        'item' => ['slug' => 'history-of-the-faculty', 'title' => 'History of the Faculty', 'meta_description' => 'Our history.'],
        'sections' => [
            ['section_type'=>'gallery','media_path'=>'public/uploads/history-one.jpg','media_alt_text'=>'History one','subheading'=>null,'heading'=>null,'body'=>null,'button_label'=>null,'button_url'=>null],
            ['section_type'=>'gallery','media_path'=>'public/uploads/history-two.jpg','media_alt_text'=>'History two','subheading'=>null,'heading'=>null,'body'=>null,'button_label'=>null,'button_url'=>null],
        ],
        'dean' => null,
    ], null);
    foreach (['about-history-gallery-2', 'History one', 'about-page-navigation'] as $fragment) {
        if (!str_contains($history, $fragment)) {
            throw new RuntimeException('History page is missing: ' . $fragment);
        }
    }

    $dean = $view->render('public/content/page', [
        ...$base,
        'currentPath' => '/about/deans-message',
        'item' => ['slug' => 'deans-message', 'title' => "Dean's Message", 'meta_description' => 'From the Dean.'],
        'sections' => [[
            'section_type'=>'rich_text','media_path'=>null,'media_alt_text'=>null,'subheading'=>null,
            'heading'=>'Welcome','body'=>'A message from the Dean.','button_label'=>null,'button_url'=>null,
        ]],
        'dean' => [
            'slug'=>'dean-profile','honorific_title'=>'Dr.','first_name'=>'Grace','middle_name'=>null,
            'last_name'=>'Kato','post_nominals'=>null,'position_title'=>'Dean','profile_path'=>'public/uploads/dean.jpg',
            'profile_alt_text'=>'Portrait of the Dean',
        ],
    ], null);
    foreach (['Dr. Grace Kato', 'Portrait of the Dean', '/staff/dean-profile', 'A message from the Dean.', 'about-masthead-image', 'assets/images/fast-building.png'] as $fragment) {
        if (!str_contains($dean, $fragment)) {
            throw new RuntimeException('Dean page is missing: ' . $fragment);
        }
    }

    $direction = $view->render('public/content/page', [
        ...$base,
        'currentPath' => '/about/vision-and-mission',
        'item' => ['slug' => 'vision-and-mission', 'title' => 'Vision and Mission', 'meta_description' => 'Our direction.'],
        'sections' => [
            ['section_type'=>'cards','heading'=>'Vision','subheading'=>'Direction','body'=>'A clear vision.','media_path'=>null,'media_alt_text'=>null,'button_label'=>null,'button_url'=>null],
            ['section_type'=>'cards','heading'=>'Mission','subheading'=>'Purpose','body'=>'A clear mission.','media_path'=>null,'media_alt_text'=>null,'button_label'=>null,'button_url'=>null],
        ],
        'dean' => null,
    ], null);
    if (substr_count($direction, 'about-direction-card') !== 2 || !str_contains($direction, 'A clear mission.')) {
        throw new RuntimeException('Vision and Mission cards were not rendered correctly.');
    }

    $admin = $view->render('admin/content/page-form', [
        ...$base,
        'itemId' => 26,
        'values' => ['title'=>"Dean's Message",'slug'=>'deans-message','page_template'=>'standard','display_order'=>'30','show_in_navigation'=>'1','meta_description'=>''],
        'errors' => [], 'pages' => [], 'images' => [['id'=>8,'original_name'=>'dean.jpg']],
        'csrfToken' => 'token',
        'sections' => [[
            'id'=>7,'section_type'=>'rich_text','display_order'=>10,'heading'=>'Welcome','subheading'=>null,
            'body'=>'Message','media_id'=>8,'media_path'=>'public/uploads/dean.jpg','button_label'=>null,
            'button_url'=>null,'is_visible'=>1,
        ]],
    ], null);
    foreach (['Easy page editing', '/admin/pages/26/sections/7', 'fetched automatically', 'Update section', 'name="hero_media_id"', 'Masthead image'] as $fragment) {
        if (!str_contains($admin, $fragment)) {
            throw new RuntimeException('About page editor is missing: ' . $fragment);
        }
    }
};
