<?php

declare(strict_types=1);

use FastWebsite\Core\View;

return static function (): void {
    $view = new View(dirname(__DIR__) . '/app/Views');
    $html = $view->render(
        'layouts/public',
        [
            'pageTitle' => 'Example profile',
            'metaDescription' => 'Example page.',
            'currentPath' => '/staff/example-profile',
            'baseUrl' => '/fast/public/',
            'content' => '<p>Example content</p>',
            'siteContent' => [],
            'siteNavigation' => [
                'departments' => [['name' => 'Biomedical Engineering', 'slug' => 'biomedical-engineering']],
                'undergraduate' => [['name' => 'Bachelor of Biomedical Engineering', 'slug' => 'bachelor-of-biomedical-engineering']],
                'postgraduate' => [],
            ],
        ],
        null
    );

    $required = [
        'aria-label="Primary navigation"',
        'aria-controls="primary-navigation"',
        '>Research &amp; Innovation<',
        '>Programmes<',
        'assets/images/must-logo.webp',
        'programmes?category=undergraduate',
        '>Undergraduate programmes<',
        '>Postgraduate programmes<',
        'bachelor-of-biomedical-engineering',
        'View all undergraduate programmes',
        'No postgraduate programmes are published yet.',
        'departments/biomedical-engineering',
        '>Resources<',
        '>Academic documents<',
        'href="/fast/public/staff"',
        'aria-label="Breadcrumb"',
        '>People<',
        'assets/js/site.js',
        'aria-label="Footer navigation"',
    ];

    foreach ($required as $fragment) {
        if (!str_contains($html, $fragment)) {
            throw new RuntimeException('Public layout is missing required fragment: ' . $fragment);
        }
    }

    if (str_contains($html, 'class="utility-bar"')) {
        throw new RuntimeException('The removed MUST utility strip was rendered.');
    }

    if (str_contains($html, '>System health<')) {
        throw new RuntimeException('The diagnostic health route must not be promoted in public navigation.');
    }

    if (!str_contains($html, '<li aria-current="page">Example profile</li>')) {
        throw new RuntimeException('Detail-page breadcrumb does not expose the current page.');
    }
};
