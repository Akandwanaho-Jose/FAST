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
        'programmes' => [], 'departments' => [
            ['hero_path' => 'public/assets/images/department-1.jpg'],
            ['hero_path' => 'public/assets/images/department-2.jpg'],
            ['hero_path' => 'public/assets/images/department-3.jpg'],
            ['hero_path' => 'public/assets/images/department-4.jpg'],
        ],
        'news' => [], 'events' => [], 'innovations' => [], 'impacts' => [],
    ], null);

    foreach (['data-carousel', 'aria-label="2 of 2"',
        'home-quick-visual-grid', 'home-quick-link-grid', 'data-why-gallery',
        'Academic programmes', 'Staff profiles',
        'Research and innovation'] as $fragment
    ) {
        if (!str_contains($home, $fragment)) {
            throw new RuntimeException('Phase 2 homepage is missing: ' . $fragment);
        }
    }

    if (str_contains($home, 'data-carousel-pause')) {
        throw new RuntimeException('Phase 2 homepage still renders the removed hero pause control.');
    }

    foreach (['data-why-gallery-next', 'data-why-gallery-previous', 'home-why-gallery-controls', '<figcaption>'] as $removedFragment) {
        if (str_contains($home, $removedFragment)) {
            throw new RuntimeException('Phase 2 homepage still renders a removed Why FAST gallery control: ' . $removedFragment);
        }
    }

    if (str_contains($home, 'home-study-pathways')
        || str_contains($home, 'home-departments-showcase')
        || str_contains($home, 'home-resources-section')
        || str_contains($home, 'home-admissions-cta')
    ) {
        throw new RuntimeException('A removed duplicate homepage section was rendered.');
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
        'staff-directory-row', 'staff-row-portrait', 'Available for supervision', '1</strong> staff profile'] as $fragment
    ) {
        if (!str_contains($staff, $fragment)) {
            throw new RuntimeException('Phase 2 staff directory is missing: ' . $fragment);
        }
    }
};
