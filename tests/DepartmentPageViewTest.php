<?php

declare(strict_types=1);

use FastWebsite\Core\View;

return static function (): void {
    $view = new View(dirname(__DIR__) . '/app/Views');
    $department = [
        'id' => 1,
        'name' => 'Department of Test Engineering',
        'short_name' => 'DTE',
        'slug' => 'test-engineering',
        'faculty_name' => 'Faculty of Applied Sciences and Technology',
        'overview' => '<p>A practical engineering department.</p>',
        'history' => '<p>Established for testing.</p>',
        'vision' => '<p>Useful engineering.</p>',
        'mission' => '<p>Educate responsible engineers.</p>',
        'strategic_direction' => '',
        'hod_message' => '<p>Welcome to the department.</p>',
        'email' => 'engineering@example.test',
        'phone' => '+256 000 000000',
        'campus' => 'Kihumuro Campus',
        'building' => 'FAST Building',
        'floor' => null,
        'room' => null,
        'hero_path' => 'public/assets/images/example.jpg',
        'hero_alt_text' => 'Engineering students',
    ];
    $directory = $view->render('public/departments/index', [
        'baseUrl' => '/fast/public/',
        'search' => '',
        'result' => ['items' => [$department], 'total' => 1, 'page' => 1, 'pages' => 1],
        'siteContent' => [],
    ], null);
    foreach (['department-directory-hero', '1</strong><span>academic department', 'department-filter-bar', 'department-directory-card', 'Where ideas become practice'] as $fragment) {
        if (!str_contains($directory, $fragment)) {
            throw new RuntimeException('Department directory is missing: ' . $fragment);
        }
    }

    $html = $view->render('public/departments/show', [
        'baseUrl' => '/fast/public/',
        'department' => $department,
        'programmes' => [[
            'name' => 'Bachelor of Test Engineering',
            'slug' => 'bachelor-of-test-engineering',
            'programme_code' => 'BTE',
            'duration_text' => '4 years',
            'overview' => 'A test programme.',
            'level_name' => 'Undergraduate',
        ]],
    ], null);

    foreach ([
        'class="department-profile-hero"',
        'aria-label="On this department page"',
        'class="department-fact-strip"',
        'id="programmes"',
        'Bachelor of Test Engineering',
        'id="contact"',
        'Vision and direction',
    ] as $fragment) {
        if (!str_contains($html, $fragment)) {
            throw new RuntimeException('Redesigned department page is missing: ' . $fragment);
        }
    }

    if (str_contains($html, 'department-detail-hero') || str_contains($html, 'class="department-masthead"')) {
        throw new RuntimeException('Legacy oversized department hero was rendered.');
    }

    foreach (['id="leadership"', 'A message from the Head of Department', 'Welcome to the department.'] as $removed) {
        if (str_contains($html, $removed)) {
            throw new RuntimeException('Removed department leadership message was rendered: ' . $removed);
        }
    }
};
