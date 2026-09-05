<?php

declare(strict_types=1);

use FastWebsite\Core\Database;

require dirname(__DIR__, 2) . '/bootstrap/autoload.php';

/**
 * RBAC and reference-data seed for a freshly reconstructed fast_website_db.
 *
 * CONTEXT: the original database was deleted with no backup. database/schema.sql
 * is a reconstruction of table structure only -- it contains no rows. Every
 * other seed script in this directory only UPDATEs rows that are assumed to
 * already exist (by stable slug/code), so nothing in the repository actually
 * creates the base faculties/departments/roles/permissions/sdgs/reference-data
 * rows that the rest of the application (and the other seed scripts) depend
 * on. This script fills that gap. It is idempotent: safe to run repeatedly,
 * using INSERT ... ON DUPLICATE KEY UPDATE against each table's natural
 * unique key (slug/code), matching the style used by database/seeds/site-settings.php.
 *
 * PROVENANCE / WHAT IS REAL VS AUTHORED:
 *
 * - faculties (1 row): name and university name taken verbatim from README.md's
 *   opening line ("Faculty of Applied Sciences and Technology (FAST), Mbarara
 *   University of Science and Technology (MUST)") and database/seeds/site-settings.php's
 *   identity.* values. REAL / confirmed.
 *
 * - departments (5 rows): slugs are the real, confirmed department_slug values
 *   found in database/seeds/staff-handbook-2025-2026.csv and cross-referenced in
 *   database/seeds/departments-handbook-2025-2026.php. Names:
 *     - civil-engineering, mechanical-engineering,
 *       petroleum-engineering-and-environmental-management: confirmed verbatim
 *       in database/seeds/departments-handbook-2025-2026.php's hod_message prose.
 *     - biomedical-engineering, electrical-and-electronics-engineering:
 *       confirmed verbatim in database/seeds/deans-office-content-2026.php's
 *       $departments[...]['name'] entries (that importer keys the exact same
 *       5 slugs used here).
 *   NOTE: for petroleum-engineering-and-environmental-management,
 *   deans-office-content-2026.php uses a slightly different name ("Department
 *   of Energy, Mineral and Petroleum Engineering") than departments-handbook-2025-2026.php's
 *   hod_message prose ("Department of Energy, Minerals and Petroleum Studies").
 *   Per explicit task instruction this script uses the departments-handbook-2025-2026.php
 *   wording; the deans-office importer will simply update the name again the
 *   next time it runs (it is idempotent by slug), so this is not a conflict.
 *   All 5 rows are seeded with status=published and published_at=NOW() so the
 *   public site shows department content immediately.
 *
 * - roles: only `code = 'super_admin'` is evidence-backed -- it is hard-required
 *   by app/Services/AdminSetupService::isAvailable() and bin/create-admin.php
 *   before the first administrator can be created. docs/DATABASE_SCHEMA.md
 *   states the original had 9 roles but no other names/codes are recoverable
 *   from any source in this repository. The additional roles below
 *   (department_manager, content_editor, content_reviewer, viewer) are
 *   AUTHORED, reasonable defaults consistent with DepartmentScopeService::ACCESS_RANK
 *   (view/create/edit/review/publish/manage) -- not a reconstruction of the
 *   original 9.
 *
 * - permissions: every code below was found by grepping the actual
 *   authorization call sites in this codebase (routes/web.php `$protect(...)`
 *   calls, every `can($userId, 'x.y')` / permission-string literal across
 *   app/Controllers, app/Services, app/Services/AdminNavigation.php::definitions(),
 *   and tests/integration/AuthorizationFlowTest.php / DepartmentFlowTest.php).
 *   That union is 29 permissions, short of the documented 31 -- the remaining
 *   ~2 could not be located in any code path and are NOT invented here.
 *
 * - role_permissions: super_admin is granted every seeded permission. This is
 *   required for DepartmentScopeService::hasGlobalScope(), which checks
 *   authorization->can($userId, 'settings.manage'), and for bin/seed-handbook-batch.php,
 *   which requires an active user holding a specific set of permissions
 *   (settings.manage, departments.edit, departments.publish, staff.create,
 *   staff.edit, staff.publish, programmes.publish, content.approve) before it
 *   will run.
 *
 * - sdgs (17 rows): REAL public factual data -- the official UN Sustainable
 *   Development Goals, their official numbers, names, and official SDG colour
 *   hex codes (un.org SDG communications guidelines). Not proprietary/lost
 *   project data.
 *
 * - programme_levels (4 rows): codes UG/PGD/MASTERS/PHD are confirmed by
 *   app/Repositories/ProgrammeRepository::publicCategoryCounts(). Full names
 *   and display_order are reasonable, AUTHORED companions.
 *
 * - positions (9 rows): codes are every distinct position_code value found in
 *   database/seeds/staff-handbook-2025-2026.csv (the full, real distinct set --
 *   not just the 4 mentioned in the task prompt). position_type/hierarchy_level
 *   are AUTHORED, reasonable orderings (dean highest authority down to
 *   technician), consistent with StaffValidator's confirmed
 *   position_type === 'department_leadership' check and
 *   StaffRepository::positions()'s ORDER BY hierarchy_level.
 *
 * - publication_types (9 rows): codes and the "nine seeded publication types"
 *   count are confirmed verbatim by docs/RESEARCH.md ("journal article,
 *   conference paper, book, book chapter, technical report, policy brief,
 *   thesis/dissertation, dataset, and other").
 *
 * - research_unit_types: NOT directly recoverable from any source found in
 *   this repository. The 5 rows below are a reasonable, AUTHORED, best-effort
 *   default set, clearly not a reconstruction of original data.
 */

/** @var Database $database */
$database = require dirname(__DIR__, 2) . '/bootstrap/database.php';
$pdo = $database->connection();

$report = [];

// ---------------------------------------------------------------------------
// 1. Faculty
// ---------------------------------------------------------------------------
$pdo->prepare(
    'INSERT INTO faculties (name, short_name, slug, status)
     VALUES (:name, :short_name, :slug, "published")
     ON DUPLICATE KEY UPDATE name = VALUES(name), short_name = VALUES(short_name), status = "published"'
)->execute([
    'name' => 'Faculty of Applied Sciences and Technology',
    'short_name' => 'FAST',
    'slug' => 'fast',
]);
$facultyId = (int) $pdo->query(
    "SELECT id FROM faculties WHERE slug = 'fast' LIMIT 1"
)->fetchColumn();
$report['faculty_id'] = $facultyId;

// ---------------------------------------------------------------------------
// 2. Departments
// ---------------------------------------------------------------------------
$departments = [
    [
        'slug' => 'civil-engineering',
        'name' => 'Department of Civil and Building Services Engineering',
        'short_name' => 'CBSE',
    ],
    [
        'slug' => 'mechanical-engineering',
        'name' => 'Department of Mechanical and Industrial Engineering',
        'short_name' => 'MIE',
    ],
    [
        'slug' => 'petroleum-engineering-and-environmental-management',
        'name' => 'Department of Energy, Minerals and Petroleum Studies',
        'short_name' => 'EMPS',
    ],
    [
        'slug' => 'biomedical-engineering',
        'name' => 'Department of Biomedical Sciences and Engineering',
        'short_name' => 'BSE',
    ],
    [
        'slug' => 'electrical-and-electronics-engineering',
        'name' => 'Department of Electrical and Electronics Engineering',
        'short_name' => 'EEE',
    ],
];

$departmentStatement = $pdo->prepare(
    'INSERT INTO departments (faculty_id, name, short_name, slug, display_order, status, published_at)
     VALUES (:faculty_id, :name, :short_name, :slug, :display_order, "published", NOW())
     ON DUPLICATE KEY UPDATE
        name = VALUES(name),
        short_name = VALUES(short_name),
        status = IF(status = "archived", status, "published"),
        published_at = IF(published_at IS NULL, NOW(), published_at)'
);
foreach ($departments as $index => $department) {
    $departmentStatement->execute([
        'faculty_id' => $facultyId,
        'name' => $department['name'],
        'short_name' => $department['short_name'],
        'slug' => $department['slug'],
        'display_order' => $index * 10,
    ]);
}
$report['departments'] = array_column($departments, 'name', 'slug');

// ---------------------------------------------------------------------------
// 3. Roles
// ---------------------------------------------------------------------------
$roles = [
    [
        'code' => 'super_admin',
        'name' => 'Super Administrator',
        'description' => 'Full, unscoped access to every permission and department. Required verbatim by AdminSetupService/bin/create-admin.php.',
        'is_system_role' => 1,
    ],
    [
        'code' => 'department_manager',
        'name' => 'Department Manager',
        'description' => 'Authored default role: manage-level access within assigned departments (DepartmentScopeService access_level=manage).',
        'is_system_role' => 0,
    ],
    [
        'code' => 'content_editor',
        'name' => 'Content Editor',
        'description' => 'Authored default role: create/edit content within assigned departments.',
        'is_system_role' => 0,
    ],
    [
        'code' => 'content_reviewer',
        'name' => 'Content Reviewer',
        'description' => 'Authored default role: review and approve submitted content.',
        'is_system_role' => 0,
    ],
    [
        'code' => 'viewer',
        'name' => 'Viewer',
        'description' => 'Authored default role: read-only administrative access.',
        'is_system_role' => 0,
    ],
];
$roleStatement = $pdo->prepare(
    'INSERT INTO roles (name, code, description, is_system_role)
     VALUES (:name, :code, :description, :is_system_role)
     ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), is_system_role = VALUES(is_system_role)'
);
foreach ($roles as $role) {
    $roleStatement->execute($role);
}
$roleIds = [];
foreach ($pdo->query('SELECT id, code FROM roles')->fetchAll() as $row) {
    $roleIds[(string) $row['code']] = (int) $row['id'];
}
$report['roles'] = array_keys($roleIds);

// ---------------------------------------------------------------------------
// 4. Permissions (union of every permission-code literal actually referenced
//    in routes/web.php, app/Controllers/*, app/Services/*, AdminNavigation,
//    and the integration tests -- see header comment for method)
// ---------------------------------------------------------------------------
$permissions = [
    'dashboard.view' => 'View the administration dashboard',
    'departments.view' => 'View departments',
    'departments.create' => 'Create departments',
    'departments.edit' => 'Edit departments',
    'departments.publish' => 'Publish departments',
    'staff.view' => 'View staff profiles',
    'staff.create' => 'Create staff profiles',
    'staff.edit' => 'Edit staff profiles',
    'staff.publish' => 'Publish staff profiles',
    'programmes.view' => 'View programmes',
    'programmes.create' => 'Create programmes',
    'programmes.edit' => 'Edit programmes',
    'programmes.publish' => 'Publish programmes',
    'research.view' => 'View research records',
    'research.create' => 'Create research records',
    'research.edit' => 'Edit research records',
    'research.publish' => 'Publish research records',
    'news.view' => 'View news',
    'news.create' => 'Create news',
    'news.edit' => 'Edit news',
    'news.publish' => 'Publish news',
    'events.manage' => 'Manage events',
    'pages.manage' => 'Manage homepage, site content, and static pages',
    'media.manage' => 'Manage the media library',
    'documents.manage' => 'Manage public documents',
    'users.manage' => 'Manage user accounts',
    'audit.view' => 'View audit logs',
    'settings.manage' => 'Manage global settings (global scope permission)',
    'content.review' => 'Review submitted content',
    'content.approve' => 'Approve and publish reviewed content',
];
$permissionStatement = $pdo->prepare(
    'INSERT INTO permissions (code, name)
     VALUES (:code, :name)
     ON DUPLICATE KEY UPDATE name = VALUES(name)'
);
foreach ($permissions as $code => $name) {
    $permissionStatement->execute(['code' => $code, 'name' => $name]);
}
$permissionIds = [];
foreach ($pdo->query('SELECT id, code FROM permissions')->fetchAll() as $row) {
    $permissionIds[(string) $row['code']] = (int) $row['id'];
}
$report['permissions'] = array_keys($permissionIds);

// ---------------------------------------------------------------------------
// 5. Role/permission grants
//    super_admin: every seeded permission (required for hasGlobalScope() and
//    the handbook batch importer's administrator lookup).
//    Authored extra roles get a plausible, non-authoritative subset.
// ---------------------------------------------------------------------------
$grantStatement = $pdo->prepare(
    'INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)'
);
foreach ($permissionIds as $permissionId) {
    $grantStatement->execute([
        'role_id' => $roleIds['super_admin'],
        'permission_id' => $permissionId,
    ]);
}

$roleGrants = [
    'department_manager' => [
        'dashboard.view', 'departments.view', 'departments.edit', 'departments.publish',
        'staff.view', 'staff.create', 'staff.edit', 'staff.publish',
        'programmes.view', 'programmes.edit', 'programmes.publish',
        'news.view', 'news.create', 'news.edit', 'news.publish',
        'content.review', 'content.approve',
    ],
    'content_editor' => [
        'dashboard.view', 'departments.view', 'staff.view', 'staff.create', 'staff.edit',
        'programmes.view', 'programmes.create', 'programmes.edit',
        'research.view', 'research.create', 'research.edit',
        'news.view', 'news.create', 'news.edit',
    ],
    'content_reviewer' => [
        'dashboard.view', 'departments.view', 'staff.view', 'programmes.view',
        'research.view', 'news.view', 'content.review', 'content.approve',
    ],
    'viewer' => [
        'dashboard.view', 'departments.view', 'staff.view', 'programmes.view',
        'research.view', 'news.view',
    ],
];
foreach ($roleGrants as $roleCode => $codes) {
    foreach ($codes as $code) {
        if (!isset($permissionIds[$code])) {
            continue;
        }
        $grantStatement->execute([
            'role_id' => $roleIds[$roleCode],
            'permission_id' => $permissionIds[$code],
        ]);
    }
}

// ---------------------------------------------------------------------------
// 6. SDGs -- the 17 official UN Sustainable Development Goals with official
//    SDG communications-guideline colours.
// ---------------------------------------------------------------------------
$sdgs = [
    ['1', 'No Poverty', '#E5243B'],
    ['2', 'Zero Hunger', '#DDA63A'],
    ['3', 'Good Health and Well-being', '#4C9F38'],
    ['4', 'Quality Education', '#C5192D'],
    ['5', 'Gender Equality', '#FF3A21'],
    ['6', 'Clean Water and Sanitation', '#26BDE2'],
    ['7', 'Affordable and Clean Energy', '#FCC30B'],
    ['8', 'Decent Work and Economic Growth', '#A21942'],
    ['9', 'Industry, Innovation and Infrastructure', '#FD6925'],
    ['10', 'Reduced Inequalities', '#DD1367'],
    ['11', 'Sustainable Cities and Communities', '#FD9D24'],
    ['12', 'Responsible Consumption and Production', '#BF8B2E'],
    ['13', 'Climate Action', '#3F7E44'],
    ['14', 'Life Below Water', '#0A97D9'],
    ['15', 'Life on Land', '#56C02B'],
    ['16', 'Peace, Justice and Strong Institutions', '#00689D'],
    ['17', 'Partnerships for the Goals', '#19486A'],
];
$sdgStatement = $pdo->prepare(
    'INSERT INTO sdgs (code, name, colour_code)
     VALUES (:code, :name, :colour_code)
     ON DUPLICATE KEY UPDATE name = VALUES(name), colour_code = VALUES(colour_code)'
);
foreach ($sdgs as [$code, $name, $colour]) {
    $sdgStatement->execute(['code' => $code, 'name' => $name, 'colour_code' => $colour]);
}
$report['sdgs'] = count($sdgs);

// ---------------------------------------------------------------------------
// 7. Programme levels -- codes confirmed by ProgrammeRepository::publicCategoryCounts()
// ---------------------------------------------------------------------------
$programmeLevels = [
    ['UG', "Undergraduate", 1],
    ['PGD', 'Postgraduate Diploma', 2],
    ['MASTERS', "Master's Degree", 3],
    ['PHD', 'Doctor of Philosophy', 4],
];
$levelStatement = $pdo->prepare(
    'INSERT INTO programme_levels (code, name, display_order, is_active)
     VALUES (:code, :name, :display_order, 1)
     ON DUPLICATE KEY UPDATE name = VALUES(name), display_order = VALUES(display_order), is_active = 1'
);
foreach ($programmeLevels as [$code, $name, $order]) {
    $levelStatement->execute(['code' => $code, 'name' => $name, 'display_order' => $order]);
}
$report['programme_levels'] = array_column($programmeLevels, 0);

// ---------------------------------------------------------------------------
// 8. Positions -- every distinct position_code value found in
//    database/seeds/staff-handbook-2025-2026.csv
// ---------------------------------------------------------------------------
$positions = [
    ['dean', 'Dean', 'faculty_leadership', 100],
    ['deputy_dean', 'Deputy Dean', 'faculty_leadership', 90],
    ['head_of_department', 'Head of Department', 'department_leadership', 80],
    ['associate_professor', 'Associate Professor', 'academic', 70],
    ['senior_lecturer', 'Senior Lecturer', 'academic', 60],
    ['lecturer', 'Lecturer', 'academic', 50],
    ['assistant_lecturer', 'Assistant Lecturer', 'academic', 40],
    ['administrative_assistant', 'Administrative Assistant', 'administrative', 20],
    ['laboratory_technician', 'Laboratory Technician', 'other', 10],
];
$positionStatement = $pdo->prepare(
    'INSERT INTO positions (name, code, position_type, hierarchy_level, is_active)
     VALUES (:name, :code, :position_type, :hierarchy_level, 1)
     ON DUPLICATE KEY UPDATE name = VALUES(name), position_type = VALUES(position_type), hierarchy_level = VALUES(hierarchy_level), is_active = 1'
);
foreach ($positions as [$code, $name, $type, $level]) {
    $positionStatement->execute([
        'name' => $name,
        'code' => $code,
        'position_type' => $type,
        'hierarchy_level' => $level,
    ]);
}
$report['positions'] = array_column($positions, 0);

// ---------------------------------------------------------------------------
// 9. Publication types -- confirmed by docs/RESEARCH.md ("Nine seeded
//    publication types: journal article, conference paper, book, book
//    chapter, technical report, policy brief, thesis/dissertation, dataset,
//    and other").
// ---------------------------------------------------------------------------
$publicationTypes = [
    ['journal_article', 'Journal Article', 1],
    ['conference_paper', 'Conference Paper', 2],
    ['book', 'Book', 3],
    ['book_chapter', 'Book Chapter', 4],
    ['technical_report', 'Technical Report', 5],
    ['policy_brief', 'Policy Brief', 6],
    ['thesis_dissertation', 'Thesis / Dissertation', 7],
    ['dataset', 'Dataset', 8],
    ['other', 'Other', 9],
];
$pubTypeStatement = $pdo->prepare(
    'INSERT INTO publication_types (code, name, display_order)
     VALUES (:code, :name, :display_order)
     ON DUPLICATE KEY UPDATE name = VALUES(name), display_order = VALUES(display_order)'
);
foreach ($publicationTypes as [$code, $name, $order]) {
    $pubTypeStatement->execute(['code' => $code, 'name' => $name, 'display_order' => $order]);
}
$report['publication_types'] = array_column($publicationTypes, 0);

// ---------------------------------------------------------------------------
// 10. Research unit types -- NOT recoverable from any source in this
//     repository. Best-effort, authored default set.
// ---------------------------------------------------------------------------
$researchUnitTypes = [
    ['research_centre', 'Research Centre', 1],
    ['research_laboratory', 'Research Laboratory', 2],
    ['research_group', 'Research Group', 3],
    ['innovation_hub', 'Innovation Hub', 4],
    ['research_consortium', 'Research Consortium', 5],
];
$unitTypeStatement = $pdo->prepare(
    'INSERT INTO research_unit_types (code, name, display_order)
     VALUES (:code, :name, :display_order)
     ON DUPLICATE KEY UPDATE name = VALUES(name), display_order = VALUES(display_order)'
);
foreach ($researchUnitTypes as [$code, $name, $order]) {
    $unitTypeStatement->execute(['code' => $code, 'name' => $name, 'display_order' => $order]);
}
$report['research_unit_types'] = array_column($researchUnitTypes, 0);

fwrite(STDOUT, "RBAC and reference data seeded.\n");
fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
