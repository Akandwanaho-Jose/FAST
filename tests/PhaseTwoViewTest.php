<?php

declare(strict_types=1);

use FastWebsite\Core\View;

return static function (): void {
    $view = new View(dirname(__DIR__) . '/app/Views');
    $home = $view->render('public/home', [
        'baseUrl' => '/fast/public/',
        'faculty' => ['name' => 'FAST', 'overview' => 'Applied learning.'],
        'slides' => [
            ['title' => 'First', 'caption' => 'First caption', 'file_path' => null,
                'mobile_file_path' => null, 'alt_text' => '', 'button_label' => null,
                'button_url' => null, 'text_alignment' => 'left', 'overlay_strength' => 60],
            ['title' => 'Second', 'caption' => 'Second caption', 'file_path' => null,
                'mobile_file_path' => null, 'alt_text' => '', 'button_label' => null,
                'button_url' => null, 'text_alignment' => 'centre', 'overlay_strength' => 40],
        ],
        'counts' => ['programmes' => 5, 'departments' => 5, 'staff' => 46, 'research' => 0],
        'announcements' => [], 'programmes' => [], 'departments' => [],
        'news' => [], 'events' => [], 'innovations' => [], 'impacts' => [],
    ], null);

    foreach (['data-carousel', 'data-carousel-pause', 'aria-label="2 of 2"',
        'home-quick-action-grid', 'Academic programmes', 'Staff profiles',
        'Choose your study pathway', 'home-admissions-cta', 'Research and innovation'] as $fragment
    ) {
        if (!str_contains($home, $fragment)) {
            throw new RuntimeException('Phase 2 homepage is missing: ' . $fragment);
        }
    }

    $staff = $view->render('public/staff/index', [
        'baseUrl' => '/fast/public/',
        'search' => '', 'departmentFilter' => null, 'categoryFilter' => '',
        'expertiseFilter' => null,
        'departments' => [['id' => 1, 'name' => 'Engineering', 'staff_count' => 1]],
        'categories' => [['staff_category' => 'academic', 'staff_count' => 1]],
        'expertiseAreas' => [['id' => 1, 'name' => 'Robotics', 'staff_count' => 1]],
        'result' => ['total' => 1, 'page' => 1, 'pages' => 1, 'items' => [[
            'honorific_title' => 'Dr', 'first_name' => 'Ada', 'middle_name' => null,
            'last_name' => 'Nabirye', 'slug' => 'ada-nabirye', 'profile_path' => null,
            'profile_alt_text' => null, 'staff_category' => 'academic',
            'position_title' => 'Lecturer', 'department_name' => 'Engineering',
            'department_slug' => 'engineering', 'expertise_names' => 'Robotics',
            'supervision_available' => 1, 'short_biography' => 'Researcher.',
        ]]],
    ], null);

    foreach (['id="staff-department"', 'id="staff-category"', 'id="staff-expertise"',
        'staff-card-placeholder', 'Available for supervision', '1</strong> staff profile'] as $fragment
    ) {
        if (!str_contains($staff, $fragment)) {
            throw new RuntimeException('Phase 2 staff directory is missing: ' . $fragment);
        }
    }
};
