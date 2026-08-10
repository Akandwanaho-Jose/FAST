<?php

declare(strict_types=1);

use FastWebsite\Core\View;

return static function (): void {
    $view = new View(dirname(__DIR__) . '/app/Views');
    $html = $view->render('admin/staff/form', [
        'baseUrl' => '/fast/public/',
        'csrfToken' => 'token',
        'staffId' => 7,
        'values' => [
            'faculty_id' => '1', 'staff_category' => 'academic',
            'display_order' => '0', 'first_name' => 'Ada', 'last_name' => 'Nabirye',
            'qualifications' => [[
                'qualification' => 'PhD', 'field_of_study' => 'Engineering',
                'institution' => 'MUST', 'country' => 'Uganda',
                'completion_year' => 2024,
            ]],
            'links' => [[
                'link_type' => 'orcid', 'label' => 'ORCID profile',
                'url' => 'https://orcid.org/0000-0000-0000-0000',
            ]],
            'expertise_ids' => [3],
        ],
        'errors' => [], 'departments' => [], 'positions' => [], 'locations' => [],
        'images' => [], 'expertiseAreas' => [['id' => 3, 'name' => 'Robotics']],
        'revisionNote' => '', 'canPublishDirectly' => true,
        'currentStatus' => 'draft',
    ], null);

    foreach ([
        'data-repeatable="qualifications"',
        'name="qualifications[0][qualification]"',
        'data-repeatable="links"',
        'name="links[0][url]"',
        'name="expertise_ids[]"',
        'Professional and research profiles',
        'name="office_room"',
        'FAST Staff Profile Collection 2025',
    ] as $fragment) {
        if (!str_contains($html, $fragment)) {
            throw new RuntimeException('Enhanced staff form is missing: ' . $fragment);
        }
    }
};
