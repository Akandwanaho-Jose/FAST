<?php

declare(strict_types=1);

use FastWebsite\Core\Database;

require dirname(__DIR__, 2) . '/bootstrap/autoload.php';

/** @var Database $database */
$database = require dirname(__DIR__, 2) . '/bootstrap/database.php';
$connection = $database->connection();

$connection->exec(
    'CREATE TABLE IF NOT EXISTS site_settings (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(120) NOT NULL UNIQUE,
        setting_value LONGTEXT NULL,
        value_type ENUM("text", "number", "boolean", "json", "url", "email") NOT NULL DEFAULT "text",
        is_public TINYINT(1) NOT NULL DEFAULT 0,
        updated_by BIGINT UNSIGNED NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
);

$groups = [
    'identity' => [
        ['identity.short_name', 'Short faculty name', 'FAST', 'text'],
        ['identity.faculty_name', 'Faculty name', 'Faculty of Applied Sciences and Technology', 'text'],
        ['identity.university_name', 'University name', 'Mbarara University of Science and Technology', 'text'],
        ['identity.postal_address', 'Postal address', 'P.O. Box 1410, Mbarara, Uganda', 'text'],
        ['identity.university_url', 'University website', 'https://www.must.ac.ug/', 'url'],
        ['identity.library_url', 'MUST Library URL', 'https://elib.must.ac.ug/', 'url'],
        ['identity.elearning_url', 'eLearning URL', 'https://vle.must.ac.ug/', 'url'],
        ['identity.motto', 'University motto', 'Succeed We Must', 'text'],
    ],
    'about' => [
        ['about.default_hero_image', 'Default masthead image for the About FAST pages (site-relative path, or a full URL)', 'assets/images/fast-building.png', 'text'],
    ],
    'navigation' => [
        ['navigation.about', 'About FAST label', 'About FAST', 'text'],
        ['navigation.programmes', 'Programmes label', 'Programmes', 'text'],
        ['navigation.departments', 'Departments label', 'Departments', 'text'],
        ['navigation.research', 'Research and Innovation label (unused since Research Labs became a dropdown)', 'Research Labs', 'text'],
        ['navigation.staff', 'Our Staff label', 'Our Staff', 'text'],
        ['navigation.people', 'People label', 'People', 'text'],
        ['navigation.engagement', 'Engagement label', 'Engagement', 'text'],
        ['navigation.partnerships', 'Partnerships label', 'Partnerships', 'text'],
        ['navigation.news', 'News label', 'News', 'text'],
        ['navigation.events', 'Events label (no longer in primary nav; kept for the /events page and footer)', 'Events', 'text'],
        ['navigation.resources', 'Resources label', 'Resources', 'text'],
        ['navigation.undergraduate', 'Undergraduate programmes label', 'Undergraduate programmes', 'text'],
        ['navigation.postgraduate', 'Postgraduate programmes label', 'Postgraduate programmes', 'text'],
        ['navigation.all_programmes', 'All programmes label', 'All programmes', 'text'],
        ['navigation.all_departments', 'All departments label', 'All departments', 'text'],
        ['navigation.library', 'Library label', 'MUST Library', 'text'],
        ['navigation.library_short', 'Header library label', 'Library', 'text'],
        ['navigation.elearning', 'eLearning label', 'eLearning', 'text'],
        ['navigation.must_home', 'MUST home label', 'MUST Home', 'text'],
        ['navigation.footer_explore', 'Footer Explore heading', 'Explore', 'text'],
        ['navigation.footer_research', 'Footer Research heading', 'Research', 'text'],
        ['navigation.footer_connect', 'Footer Connect heading', 'Connect', 'text'],
        ['navigation.footer_resources', 'Footer Resources heading', 'Resources', 'text'],
        ['navigation.university_website', 'University website label', 'University website', 'text'],
    ],
    'homepage' => [
        ['home.hero_eyebrow', 'Hero eyebrow', 'Mbarara University of Science and Technology', 'text'],
        ['home.hero_interval_seconds', 'Hero slide duration in seconds', '10', 'number'],
        ['home.hero_secondary_label', 'Hero secondary button', 'Explore departments', 'text'],
        ['home.hero_departments_eyebrow', 'Hero departments eyebrow', 'Find your discipline', 'text'],
        ['home.hero_departments_title', 'Hero departments title', 'Explore our departments', 'text'],
        ['home.hero_departments_introduction', 'Hero departments introduction', 'Discover the academic communities shaping engineering, technology and applied science at FAST.', 'textarea'],
        ['home.hero_departments_link', 'Hero departments link', 'View all departments', 'text'],
        ['home.hero_updates_eyebrow', 'Hero updates eyebrow', 'Stay informed', 'text'],
        ['home.hero_updates_title', 'Hero updates title', 'News & Announcements', 'text'],
        ['home.hero_news_title', 'Hero news heading', 'Latest news', 'text'],
        ['home.hero_news_empty', 'Hero news empty message', 'New faculty stories will appear here.', 'text'],
        ['home.hero_news_link', 'Hero news link', 'View all news →', 'text'],
        ['home.quick_eyebrow', 'Quick links eyebrow', 'Find your next step', 'text'],
        ['home.why_eyebrow', 'Why FAST eyebrow', 'Why choose FAST?', 'text'],
        ['home.why_brand_message', 'Why FAST branding message', 'Rooted in applied science. Built for Uganda. Creating solutions for tomorrow.', 'text'],
        ['home.why_audience_line', 'Why FAST audience line', 'For students, researchers, industry and development partners.', 'text'],
        ['home.why_signature', 'Why FAST signature line', 'Education · Research · Partnership', 'text'],
        ['home.why_student_cta', 'Why FAST programmes link', 'Explore programmes →', 'text'],
        ['home.why_partner_cta', 'Why FAST partnerships link', 'Partner with FAST →', 'text'],
        ['home.why_history_eyebrow', 'History story eyebrow', 'Our story', 'text'],
        ['home.why_history_empty', 'History fallback message', 'FAST has grown as a community of teaching, research and practical innovation serving Uganda and the region.', 'textarea'],
        ['home.why_history_link', 'History page link label', 'Read the full history →', 'text'],
        ['home.why_link_label', 'Why FAST link', 'Discover the faculty →', 'text'],
        ['home.stat_programmes_label', 'Programme statistic label', 'Academic programmes', 'text'],
        ['home.stat_programmes_note', 'Programme statistic note', 'Practice-focused study', 'text'],
        ['home.stat_departments_label', 'Department statistic label', 'Departments', 'text'],
        ['home.stat_departments_note', 'Department statistic note', 'Connected disciplines', 'text'],
        ['home.stat_staff_label', 'Staff statistic label', 'Staff profiles', 'text'],
        ['home.stat_staff_note', 'Staff statistic note', 'Teachers and researchers', 'text'],
        ['home.study_eyebrow', 'Study eyebrow', 'Study at FAST', 'text'],
        ['home.study_link_label', 'All programmes link', 'All programmes →', 'text'],
        ['home.undergraduate_eyebrow', 'Undergraduate eyebrow', 'First degree', 'text'],
        ['home.undergraduate_title', 'Undergraduate title', 'Undergraduate programmes', 'text'],
        ['home.undergraduate_description', 'Undergraduate description', 'Build a strong engineering foundation through practical, professionally relevant study.', 'textarea'],
        ['home.undergraduate_link', 'Undergraduate link label', 'Explore undergraduate study →', 'text'],
        ['home.postgraduate_eyebrow', 'Postgraduate eyebrow', 'Advanced study', 'text'],
        ['home.postgraduate_title', 'Postgraduate title', 'Postgraduate programmes', 'text'],
        ['home.postgraduate_description', 'Postgraduate description', 'Deepen specialist knowledge through advanced coursework and research opportunities.', 'textarea'],
        ['home.postgraduate_link', 'Postgraduate link label', 'Explore postgraduate study →', 'text'],
        ['home.departments_eyebrow', 'Departments eyebrow', 'Academic communities', 'text'],
        ['home.departments_link', 'Departments link label', 'All departments →', 'text'],
        ['home.department_card_link', 'Department card link', 'Explore department →', 'text'],
        ['home.research_eyebrow', 'Research eyebrow', 'Research and innovation', 'text'],
        ['home.featured_innovation_label', 'Featured innovation label', 'Featured innovation', 'text'],
        ['home.research_projects_title', 'Research projects link', 'Research projects', 'text'],
        ['home.research_projects_description', 'Research projects description', 'From important questions to practical outcomes.', 'text'],
        ['home.research_publications_title', 'Publications link', 'Publications', 'text'],
        ['home.research_publications_description', 'Publications description', 'Knowledge shared by FAST researchers.', 'text'],
        ['home.research_innovations_title', 'Innovations link', 'Innovations', 'text'],
        ['home.research_innovations_description', 'Innovations description', 'Technologies designed for real needs.', 'text'],
        ['home.updates_eyebrow', 'Updates eyebrow', 'Stay informed', 'text'],
        ['home.updates_news_title', 'News panel title', 'Latest news', 'text'],
        ['home.updates_events_title', 'Events panel title', 'Upcoming events', 'text'],
        ['home.resources_eyebrow', 'Resources eyebrow', 'Current students', 'text'],
        ['home.resource_documents_title', 'Documents resource title', 'Academic documents', 'text'],
        ['home.resource_documents_description', 'Documents resource description', 'Handbooks, forms and faculty documents.', 'text'],
        ['home.resource_elearning_title', 'eLearning resource title', 'eLearning', 'text'],
        ['home.resource_elearning_description', 'eLearning resource description', 'Continue to the MUST virtual learning environment.', 'text'],
        ['home.resource_library_title', 'Library resource title', 'MUST Library', 'text'],
        ['home.resource_library_description', 'Library resource description', 'Search electronic and library resources.', 'text'],
        ['home.resource_staff_title', 'Staff resource title', 'Staff directory', 'text'],
        ['home.resource_staff_description', 'Staff resource description', 'Find a lecturer, researcher or administrator.', 'text'],
        ['home.admissions_eyebrow', 'Admissions eyebrow', 'Your next step', 'text'],
        ['home.admissions_primary', 'Admissions primary button', 'Explore programmes', 'text'],
        ['home.admissions_secondary', 'Admissions secondary button', 'Apply for admission', 'text'],
        ['home.admissions_url', 'Admissions URL', 'https://www.must.ac.ug/admissions/', 'url'],
        ['home.admissions_tertiary', 'Admissions tertiary link', 'Meet our departments →', 'text'],
    ],
    'departments' => [
        ['departments.eyebrow', 'Page eyebrow', 'Faculty structure', 'text'],
        ['departments.title', 'Page title', 'Departments', 'text'],
        ['departments.introduction', 'Page introduction', 'Explore published academic departments in the Faculty of Applied Sciences and Technology.', 'textarea'],
        ['departments.empty', 'Empty-state message', 'Department profiles are being prepared.', 'text'],
        ['departments.card_link', 'Card link label', 'Explore department →', 'text'],
    ],
    'staff' => [
        ['staff.eyebrow', 'Page eyebrow', 'People at FAST', 'text'],
        ['staff.title', 'Page title', 'Staff directory', 'text'],
        ['staff.introduction', 'Page introduction', 'Find academic, technical, administrative, and research staff across the faculty.', 'textarea'],
        ['staff.empty', 'Empty-state message', 'No staff profiles found.', 'text'],
    ],
    'programmes' => [
        ['programmes.eyebrow', 'Page eyebrow', 'Study at FAST', 'text'],
        ['programmes.title', 'Page title', 'Academic programmes', 'text'],
        ['programmes.introduction', 'Page introduction', 'Explore undergraduate and postgraduate study, then refine by department or subject.', 'textarea'],
        ['programmes.empty', 'Empty-state message', 'Programme pages are being prepared.', 'text'],
        ['programmes.card_link', 'Card link label', 'View programme →', 'text'],
    ],
    'research' => [
        ['research.eyebrow', 'Page eyebrow', 'Discovery and innovation', 'text'],
        ['research.title', 'Page title', 'Research at FAST', 'text'],
        ['research.introduction', 'Page introduction', 'Explore our research centres, laboratories, groups, expertise, and opportunities.', 'textarea'],
        ['research.empty', 'Empty-state message', 'Research pages are being prepared.', 'text'],
        ['research.projects_eyebrow', 'Projects eyebrow', 'Research in action', 'text'],
        ['research.projects_title', 'Projects title', 'Research projects', 'text'],
        ['research.projects_intro', 'Projects introduction', 'Discover ongoing, planned, and completed FAST research and innovation projects.', 'textarea'],
        ['research.publications_eyebrow', 'Publications eyebrow', 'Research outputs', 'text'],
        ['research.publications_title', 'Publications title', 'Research publications', 'text'],
        ['research.publications_intro', 'Publications introduction', 'Discover publications by FAST researchers and collaborators.', 'textarea'],
    ],
    'innovation' => [
        ['innovations.eyebrow', 'Innovations eyebrow', 'Ideas into impact', 'text'],
        ['innovations.title', 'Innovations title', 'Innovations', 'text'],
        ['innovations.introduction', 'Innovations introduction', 'Explore FAST prototypes, technologies, products, processes, and services.', 'textarea'],
        ['facilities.eyebrow', 'Facilities eyebrow', 'Research infrastructure', 'text'],
        ['facilities.title', 'Facilities title', 'Facilities and equipment', 'text'],
        ['facilities.introduction', 'Facilities introduction', 'Discover FAST laboratories, workshops, specialist facilities, and research equipment.', 'textarea'],
    ],
    'engagement' => [
        ['engagement.eyebrow', 'Page eyebrow', 'Working together', 'text'],
        ['engagement.title', 'Page title', 'Partnerships and impact', 'text'],
        ['engagement.introduction', 'Page introduction', 'See how FAST collaborates and turns knowledge into public value.', 'textarea'],
    ],
    'updates' => [
        ['news.eyebrow', 'News eyebrow', 'Latest from FAST', 'text'],
        ['news.title', 'News title', 'News', 'text'],
        ['news.empty', 'News empty-state', 'Published news will appear here.', 'text'],
        ['events.eyebrow', 'Events eyebrow', 'What is happening', 'text'],
        ['events.title', 'Events title', 'Events', 'text'],
        ['events.empty', 'Events empty-state', 'Published events will appear here.', 'text'],
        ['documents.eyebrow', 'Documents eyebrow', 'Resources', 'text'],
        ['documents.title', 'Documents title', 'Documents', 'text'],
        ['documents.introduction', 'Documents introduction', 'Official public documents and downloads from FAST.', 'textarea'],
        ['documents.empty', 'Documents empty-state', 'Published public documents will appear here.', 'text'],
    ],
];

$statement = $connection->prepare(
    'INSERT INTO site_settings
        (setting_key, setting_value, value_type, is_public)
     VALUES (?, ?, ?, 1)
     ON DUPLICATE KEY UPDATE is_public = 1'
);

foreach ($groups as $group => $settings) {
    foreach ($settings as $index => $setting) {
        $statement->execute([$setting[0], $setting[2], $setting[3] === 'url' ? 'url' : 'text']);
    }
}

fwrite(STDOUT, "Site-wide content settings are ready.\n");
