<?php

declare(strict_types=1);

use FastWebsite\Core\View;

return static function (): void {
    $view = new View(dirname(__DIR__) . '/app/Views');
    $html = $view->render('public/content/page', [
        'baseUrl' => '/fast/public/',
        'currentPath' => '/student-life',
        'item' => [
            'slug' => 'student-life',
            'title' => 'Student Life',
            'meta_description' => 'Experience engineering beyond the classroom.',
            'hero_path' => 'public/uploads/student-life.jpg',
            'hero_alt_text' => 'FAST students working together',
        ],
        'sections' => [
            [
                'section_type' => 'rich_text', 'heading' => 'Experience Engineering Beyond the Classroom',
                'subheading' => 'Student Life', 'body' => '<p>A vibrant student experience.</p><h3>Key areas</h3><ul><li>Innovation challenges</li><li>Professional associations</li></ul>',
                'media_path' => null, 'media_alt_text' => null, 'button_label' => null, 'button_url' => null,
            ],
            [
                'section_type' => 'gallery', 'heading' => null, 'subheading' => null, 'body' => null,
                'media_path' => 'public/uploads/student-gallery.jpg', 'media_alt_text' => 'Students in a laboratory',
                'button_label' => null, 'button_url' => null,
            ],
        ],
        'dean' => null,
        'relatedPages' => [[
            'title' => 'Professional Bodies', 'slug' => 'professional-bodies',
            'meta_description' => 'Build professional connections.',
            'hero_path' => 'public/uploads/professional-bodies.jpg',
            'hero_alt_text' => 'Students at a professional event',
        ]],
    ], null);

    foreach ([
        'engagement-landing-hero', 'student-life.jpg', 'student-life-pathways', 'Professional Bodies',
        'professional-bodies.jpg', 'feature-page-layout', 'feature-page-prose',
        'Innovation challenges', 'feature-page-visual-rail', 'student-gallery.jpg',
        'feature-page-footer-panel',
    ] as $fragment) {
        if (!str_contains($html, $fragment)) {
            throw new RuntimeException('Feature page is missing: ' . $fragment);
        }
    }
};
