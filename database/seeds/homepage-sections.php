<?php

declare(strict_types=1);

use FastWebsite\Core\Database;

require dirname(__DIR__, 2) . '/bootstrap/autoload.php';

/** @var Database $database */
$database = require dirname(__DIR__, 2) . '/bootstrap/database.php';
$connection = $database->connection();

$connection->exec(
    'CREATE TABLE IF NOT EXISTS homepage_sections (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        section_key VARCHAR(80) NOT NULL UNIQUE,
        label VARCHAR(120) NOT NULL,
        heading VARCHAR(255) NULL,
        introduction TEXT NULL,
        display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        is_enabled TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$connection->exec(
    'CREATE TABLE IF NOT EXISTS homepage_quick_links (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        label VARCHAR(120) NOT NULL,
        description VARCHAR(255) NULL,
        link_url VARCHAR(500) NOT NULL,
        display_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$sections = [
    ['quick_links', 'Quick actions', 'Start your FAST journey', 'Find the information prospective students, current students, researchers and partners need most.', 10, 1],
    ['why_fast', 'Why FAST', 'Engineering knowledge for real-world impact', 'Learn in an academic community focused on practical skills, innovation and solutions for Uganda and beyond.', 20, 1],
    ['study', 'Study pathways', 'Choose your study pathway', 'Explore undergraduate and postgraduate opportunities across applied science and engineering.', 30, 1],
    ['departments', 'Departments', 'Explore our departments', 'Meet the academic communities responsible for teaching, research and professional practice.', 40, 1],
    ['research', 'Research and innovation', 'Ideas developed for real-world impact', 'Discover projects, publications, technologies and facilities addressing important challenges.', 50, 1],
    ['updates', 'News and events', 'What is happening at FAST', 'Follow faculty news, important notices, opportunities and upcoming events.', 60, 1],
    ['resources', 'Student resources', 'Useful academic resources', 'Quick access to the services and documents students use throughout the academic year.', 70, 1],
    ['admissions', 'Admissions call to action', 'Ready to build the future with FAST?', 'Explore your programme, review admission information and take the next step.', 80, 1],
];
$insertSection = $connection->prepare(
    'INSERT INTO homepage_sections
        (section_key, label, heading, introduction, display_order, is_enabled)
     VALUES (?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE label = VALUES(label)'
);
foreach ($sections as $section) {
    $insertSection->execute($section);
}

if ((int) $connection->query('SELECT COUNT(*) FROM homepage_quick_links')->fetchColumn() === 0) {
    $links = [
        ['Undergraduate programmes', 'Explore bachelor’s degrees at FAST.', '/programmes?category=undergraduate', 10, 1],
        ['Postgraduate programmes', 'Discover advanced study and research pathways.', '/programmes?category=postgraduate', 20, 1],
        ['Departments', 'Find your engineering and applied science discipline.', '/departments', 30, 1],
        ['Apply for admission', 'Continue to MUST admissions information.', 'https://www.must.ac.ug/admissions/', 40, 1],
        ['Student resources', 'Access documents, eLearning and the library.', '/documents', 50, 1],
    ];
    $insertLink = $connection->prepare(
        'INSERT INTO homepage_quick_links
            (label, description, link_url, display_order, is_active)
         VALUES (?, ?, ?, ?, ?)'
    );
    foreach ($links as $link) {
        $insertLink->execute($link);
    }
}

fwrite(STDOUT, "Homepage section settings are ready.\n");
