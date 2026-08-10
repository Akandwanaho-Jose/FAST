<?php

declare(strict_types=1);

use FastWebsite\Core\View;

return static function (): void {
    $view = new View(dirname(__DIR__) . '/app/Views');
    $profile = [
        'id'=>1,'honorific_title'=>'Dr.','first_name'=>'Ada','middle_name'=>null,'last_name'=>'Nabirye',
        'post_nominals'=>'PhD','slug'=>'ada-nabirye','profile_path'=>'public/uploads/ada.jpg',
        'profile_alt_text'=>'Portrait of Dr. Ada Nabirye','position_title'=>'Senior Lecturer','staff_category'=>'academic',
        'department_name'=>'Department of Test Engineering','faculty_name'=>'Faculty of Applied Sciences and Technology',
        'supervision_available'=>1,'short_biography'=>'<p>An engineering researcher.</p>','biography'=>null,
        'research_summary'=>'<p>Research in useful systems.</p>','teaching_summary'=>null,'supervision_interests'=>null,
        'room'=>'12','office_room'=>'TF01','floor'=>'First Floor','building'=>'FAST Building','campus'=>'Kihumuro Campus',
        'institutional_email'=>'ada@example.test','alternative_email'=>null,'public_phone'=>'+256 700 000000','consultation_hours'=>'Friday, 2–4pm',
    ];
    $html = $view->render('public/staff/show', [
        'baseUrl'=>'/fast/public/','profile'=>$profile,
        'expertise'=>[['name'=>'Robotics']],
        'qualifications'=>[['qualification'=>'PhD','field_of_study'=>'Engineering','institution'=>'MUST','country'=>'Uganda','completion_year'=>2024]],
        'researchUnits'=>[['role_title'=>'Member','membership_role'=>'member','slug'=>'test-lab','name'=>'Test Lab']],
        'projects'=>[],
        'publications'=>[['type_name'=>'Journal article','publication_year'=>2026,'slug'=>'useful-systems','title'=>'Useful Systems','journal_name'=>'FAST Journal']],
        'links'=>[['link_type'=>'orcid','url'=>'https://orcid.org/example','label'=>null]],
    ], null);

    foreach (['staff-profile-directory-grid','staff-profile-sidebar-portrait','role="tablist"','data-profile-tab="about"','id="profile-panel-qualifications"','id="profile-panel-publications"','TF01','Useful Systems'] as $fragment) {
        if (!str_contains($html, $fragment)) throw new RuntimeException('Staff profile is missing: '.$fragment);
    }
    if (str_contains($html, 'staff-profile-masthead')) throw new RuntimeException('The removed profile masthead was rendered.');
};
