<?php

declare(strict_types=1);

use FastWebsite\Core\View;

return static function (): void {
    $view = new View(dirname(__DIR__) . '/app/Views');
    $programme = [
        'id' => 1,
        'programme_code' => 'BTE',
        'name' => 'Bachelor of Test Engineering',
        'award_title' => 'Bachelor of Test Engineering',
        'slug' => 'bachelor-of-test-engineering',
        'overview' => '<p>A practical engineering programme.</p>',
        'why_study' => null,
        'objectives' => '<p>Build useful systems.</p>',
        'learning_outcomes' => null,
        'entry_requirements' => '<p>Two principal passes.</p>',
        'career_opportunities' => null,
        'practical_training' => null,
        'accreditation' => null,
        'duration_text' => '4 years',
        'duration_years' => 4,
        'study_mode' => 'full_time',
        'delivery_mode' => 'face_to_face',
        'level_name' => 'Undergraduate',
        'department_name' => 'Department of Test Engineering',
        'department_slug' => 'test-engineering',
        'application_url' => 'https://example.test/apply',
        'hero_path' => 'public/uploads/programme.jpg',
        'hero_alt_text' => 'Engineering students testing a system',
    ];

    $index = $view->render('public/programmes/index', [
        'baseUrl' => '/fast/public/',
        'search' => '',
        'categoryFilter' => '',
        'departmentFilter' => '',
        'departments' => [['name' => 'Department of Test Engineering', 'slug' => 'test-engineering']],
        'categoryCounts' => ['all' => 1, 'undergraduate' => 1, 'postgraduate' => 0],
        'result' => ['items' => [$programme], 'total' => 1, 'page' => 1, 'pages' => 1],
        'siteContent' => [],
    ], null);
    foreach (['programme-directory-hero', 'Programme categories', 'Undergraduate', 'Postgraduate', 'programme-filter-bar', 'programme-directory-card'] as $fragment) {
        if (!str_contains($index, $fragment)) {
            throw new RuntimeException('Programme directory is missing: ' . $fragment);
        }
    }

    $detail = $view->render('public/programmes/show', [
        'baseUrl' => '/fast/public/',
        'programme' => $programme,
        'curriculum' => ['version_name' => 'Test curriculum', 'total_credit_units' => 4],
        'curriculumCourses' => [[
            'study_year' => 1,
            'semester' => 1,
            'course_code' => 'TST1101',
            'title' => 'Engineering Test Methods',
            'requirement_type' => 'core',
            'credit_units_override' => null,
            'default_credit_units' => 4,
        ]],
    ], null);
    foreach (['programme-detail-hero', 'aria-label="On this programme page"', 'programme-fact-strip', 'id="entry-requirements"', 'id="course-structure"', 'programme-action-panel', 'Engineering Test Methods'] as $fragment) {
        if (!str_contains($detail, $fragment)) {
            throw new RuntimeException('Programme detail page is missing: ' . $fragment);
        }
    }
    if (str_contains($detail, 'department-hero')) {
        throw new RuntimeException('Legacy programme hero was rendered.');
    }
};
