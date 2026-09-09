-- =============================================================================
-- FAST website — reconstructed database schema
-- =============================================================================
--
-- CONTEXT: The original `fast_website_db` MySQL/MariaDB database (the folder
-- C:\xampp\mysql\data\fast_website_db) was accidentally deleted from disk.
-- There was no mysqldump backup, no committed schema.sql, and no migrations
-- folder anywhere in this repository or elsewhere. This file is a BEST-EFFORT
-- RECONSTRUCTION of that schema, produced by reading:
--   - docs/DATABASE_SCHEMA.md, CONNECTIVITY.md, AUTHENTICATION.md,
--     DEPARTMENTS.md, STAFF.md, PROGRAMMES.md, RESEARCH.md, HANDBOOK_SEED.md,
--     ADMIN_PORTAL.md, BRANDING.md, UI_UX.md, CHANGELOG.md
--   - app/Core/DatabaseVerifier.php (required tables + seed-count assertions)
--   - every app/Repositories/*.php file (raw SQL: SELECT/INSERT/UPDATE/JOIN)
--   - every app/Validation/*.php file (lengths, enums, formats -> column defs)
--   - app/Services/*.php (workflow state machine, audit signature, access
--     levels, upload constraints)
--   - every bin/*-check.php verification script (columns/values queried
--     against the live database — the strongest ground truth available)
--   - database/seeds/*.php and *.csv (three files contain LITERAL, verbatim
--     CREATE TABLE DDL that was run against the live database: site_settings,
--     homepage_sections, homepage_quick_links — those three table defs below
--     are byte-for-byte reconstructions, not inferences)
--   - tests/integration/*.php (several tests INSERT directly into tables
--     inside rolled-back transactions, confirming exact column lists for
--     users, roles, role_permissions, user_roles, user_departments, media,
--     staff, staff_positions, expertise_areas)
--
-- IT IS NOT A BYTE-EXACT RECREATION. docs/DATABASE_SCHEMA.md states the
-- original had 82 tables, 128 foreign keys, 62 unique keys, and 16 CHECK
-- constraints (20 on MariaDB, which auto-adds 4 JSON_VALID checks for JSON
-- columns). Those figures are recorded here as a GOAL, not a guarantee:
--   - This file defines 67 tables with direct or strong inferential evidence
--     from application source. The remaining ~15 tables implied by the "82"
--     figure could not be located in any code path, seed file, validator,
--     or test and are NOT invented here. See the summary delivered alongside
--     this file for the specific list of gaps.
--   - ~104 foreign keys and ~40 unique keys are defined below, short of the
--     documented 128 / 62. Any additional relationships in the original
--     schema that no application code path touches could not be recovered.
--   - 16 CHECK constraints are defined, chosen to mirror validation rules
--     enforced in PHP (e.g. date ordering, numeric ranges). Their exact
--     original SQL expressions are NOT recoverable — these are best-effort
--     equivalents, each commented "-- inferred check".
--
-- CONVENTIONS USED THROUGHOUT (chosen consistently where the original
-- convention was not directly observable):
--   - Every table: id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY (confirmed
--     directly for site_settings/homepage_sections/homepage_quick_links via
--     literal seed DDL; applied consistently elsewhere).
--   - InnoDB, utf8mb4, utf8mb4_unicode_ci on every table (confirmed by
--     docs/DATABASE_SCHEMA.md and literal seed DDL).
--   - created_at/updated_at TIMESTAMP columns on every table (confirmed
--     literally on site_settings/homepage_sections/homepage_quick_links;
--     assumed elsewhere as a repo-wide convention — marked "-- assumed"
--     only where the convention is less certain, e.g. pure pivot tables).
--   - `deleted_at DATETIME NULL` soft-delete column added exactly where
--     repository code filters `WHERE ... deleted_at IS NULL` (confirmed
--     per-table from repository SQL, not assumed globally).
--   - The five-state editorial workflow confirmed identically in
--     ContentWorkflowService::TRANSITIONS and StaffService::TRANSITIONS
--     (draft -> under_review -> approved -> published -> archived, with
--     changes_requested and restored transitions back to draft) is modelled
--     as ENUM('draft','under_review','approved','published','archived') on
--     every entity that docs/RESEARCH.md, DEPARTMENTS.md, STAFF.md, and
--     PROGRAMMES.md describe as sharing this workflow.
--   - Foreign key ON DELETE behaviour: CASCADE for pure join/detail tables
--     owned by one parent (e.g. staff_qualifications, programme_departments);
--     SET NULL for optional/nullable references (e.g. hero_media_id,
--     uploaded_by); RESTRICT for required references to core, rarely-deleted
--     entities (e.g. departments.faculty_id, staff.faculty_id). This is a
--     judgement call, not observed directly, and is noted inline as
--     "-- inferred ON DELETE".
--
-- Targets restated from docs/DATABASE_SCHEMA.md (goal, not guaranteed here):
--   82 tables · 128 foreign keys · 62 unique keys · 16 CHECK constraints
--   (20 on MariaDB, +4 JSON_VALID) · seed minimums: faculties=1,
--   departments=5, roles=9, permissions=31, sdgs=17.
-- =============================================================================

-- This file does NOT create or select a database - it only creates tables
-- in whatever database the mysql client is already connected to. Run it as:
--   mysql -u <user> -p <database_name> < database/schema.sql
-- That works the same way on local dev and on shared hosting (e.g. cPanel),
-- where the database must already exist under a host-assigned name (often
-- prefixed with your account username) rather than the literal
-- "fast_website_db" this project uses as its own convention.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =============================================================================
-- SECTION 1: Authentication & authorization
-- Confirmed by: DatabaseVerifier::REQUIRED_TABLES, UserRepository, AuthService,
-- AuthorizationService, AdminSetupService, DepartmentScopeService,
-- tests/integration/AuthFlowTest.php, AuthorizationFlowTest.php.
-- =============================================================================

CREATE TABLE `users` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    -- length 2..200 enforced in AdminSetupService::create()
    `name` VARCHAR(200) NOT NULL,
    -- length <=190 + FILTER_VALIDATE_EMAIL enforced in AdminSetupService
    `email` VARCHAR(190) NOT NULL,
    -- password_hash(PASSWORD_DEFAULT) per docs/AUTHENTICATION.md; bcrypt/argon
    -- hashes fit comfortably in 255 chars
    `password_hash` VARCHAR(255) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
    `failed_login_attempts` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `locked_until` DATETIME NULL DEFAULT NULL,
    `last_login_at` DATETIME NULL DEFAULT NULL,
    `password_changed_at` DATETIME NULL DEFAULT NULL,
    -- cleared to NULL on password change (UserRepository::changePassword);
    -- exact original purpose/length inferred -- assumed
    `remember_token_hash` VARCHAR(255) NULL DEFAULT NULL,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_deleted_active` (`deleted_at`, `is_active`),
    -- inferred check: failed-attempt counters cannot go negative
    CONSTRAINT `chk_users_failed_attempts` CHECK (`failed_login_attempts` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Self-service "forgot password" flow. Only the token's sha256 hash is
-- stored (see app/Services/PasswordResetService.php); the raw token exists
-- only in the emailed link and the request that redeems it.
CREATE TABLE `password_reset_tokens` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `token_hash` CHAR(64) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used_at` DATETIME NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_password_reset_tokens_hash` (`token_hash`),
    KEY `idx_password_reset_tokens_user` (`user_id`),
    CONSTRAINT `fk_password_reset_tokens_user` FOREIGN KEY (`user_id`)
        REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `roles` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    -- columns confirmed verbatim by tests/integration/AuthorizationFlowTest.php
    -- and DepartmentFlowTest.php: INSERT INTO roles (name, code, description, is_system_role)
    `name` VARCHAR(150) NOT NULL,
    `code` VARCHAR(100) NOT NULL,
    `description` TEXT NULL DEFAULT NULL,
    `is_system_role` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_roles_code` (`code`)
    -- seed minimum: 9 roles (DatabaseVerifier::EXPECTED_SEED_COUNTS)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `permissions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    -- `code` confirmed (UserRepository::permissionCodes SELECT p.code); `name`
    -- and `description` are -- assumed companions for an admin-facing list
    `code` VARCHAR(100) NOT NULL,
    `name` VARCHAR(150) NULL DEFAULT NULL,
    `description` TEXT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_permissions_code` (`code`)
    -- seed minimum: 31 permissions (DatabaseVerifier)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_roles` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `role_id` BIGINT UNSIGNED NOT NULL,
    -- confirmed by AdminSetupService::create() INSERT INTO user_roles (user_id, role_id, assigned_by)
    `assigned_by` BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_roles_user_role` (`user_id`, `role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `role_permissions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `role_id` BIGINT UNSIGNED NOT NULL,
    `permission_id` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_role_permissions_role_permission` (`role_id`, `permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_departments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `department_id` BIGINT UNSIGNED NOT NULL,
    -- confirmed values and ranking in DepartmentScopeService::ACCESS_RANK
    `access_level` ENUM('view','create','edit','review','publish','manage') NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_user_departments_user_department` (`user_id`, `department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SECTION 2: Organisational structure
-- Confirmed by: DepartmentRepository, DepartmentValidator, StaffRepository,
-- docs/DEPARTMENTS.md, docs/STAFF.md.
-- =============================================================================

CREATE TABLE `locations` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    -- all four nullable: confirmed by findOrCreateRoom() inserting room-only rows
    `campus` VARCHAR(150) NULL DEFAULT NULL,
    `building` VARCHAR(150) NULL DEFAULT NULL,
    `floor` VARCHAR(50) NULL DEFAULT NULL,
    `room` VARCHAR(50) NULL DEFAULT NULL,
    `directions` TEXT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `media` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    -- only "image" is ever inserted by MediaUploadService/MediaRepository;
    -- "document" retained for symmetry with the separate documents table
    -- -- inferred enum member
    `media_type` ENUM('image','document') NOT NULL DEFAULT 'image',
    `original_name` VARCHAR(255) NOT NULL,
    `stored_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `mime_type` VARCHAR(150) NOT NULL,
    `file_extension` VARCHAR(10) NOT NULL,
    `file_size` INT UNSIGNED NOT NULL,
    `width` INT UNSIGNED NULL DEFAULT NULL,
    `height` INT UNSIGNED NULL DEFAULT NULL,
    `alt_text` VARCHAR(255) NULL DEFAULT NULL,
    -- confirmed by AssetRepository::updateMedia (alt_text, caption, copyright_notice)
    `caption` VARCHAR(500) NULL DEFAULT NULL,
    `copyright_notice` VARCHAR(255) NULL DEFAULT NULL,
    -- confirmed values: "active" filtered everywhere; AssetRepository::mediaStatus
    -- sets arbitrary status -- other members inferred
    `status` ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
    `uploaded_by` BIGINT UNSIGNED NULL DEFAULT NULL,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_media_type_status_deleted` (`media_type`, `status`, `deleted_at`),
    -- inferred check: an uploaded file cannot be recorded as empty
    CONSTRAINT `chk_media_file_size` CHECK (`file_size` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `faculties` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    -- FAST is the only seeded faculty (seed minimum = 1); columns mirror the
    -- parallel department shape used by DepartmentRepository::faculty()
    `name` VARCHAR(255) NOT NULL,
    `short_name` VARCHAR(50) NULL DEFAULT NULL,
    -- -- assumed: not directly queried, but every other public-facing entity
    -- in this codebase has a slug; kept nullable/unique defensively
    `slug` VARCHAR(255) NULL DEFAULT NULL,
    `overview` LONGTEXT NULL DEFAULT NULL,
    `history` LONGTEXT NULL DEFAULT NULL,
    `email` VARCHAR(190) NULL DEFAULT NULL,
    `phone` VARCHAR(50) NULL DEFAULT NULL,
    `location_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `logo_media_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    -- HomepageRepository::faculty() filters status="published"; no evidence of
    -- the full editorial workflow (no content_revisions rows seen for
    -- entity_type="faculty") -- inferred, simplified status set
    `status` ENUM('draft','published','archived') NOT NULL DEFAULT 'published',
    `deleted_at` DATETIME NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_faculties_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `departments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `faculty_id` BIGINT UNSIGNED NOT NULL,
    -- length <=200 per DepartmentValidator
    `name` VARCHAR(200) NOT NULL,
    -- length <=30 per DepartmentValidator
    `short_name` VARCHAR(30) NULL DEFAULT NULL,
    -- length <=220, lowercase/hyphen pattern per DepartmentValidator
    `slug` VARCHAR(220) NOT NULL,
    `overview` LONGTEXT NULL DEFAULT NULL,
    `history` LONGTEXT NULL DEFAULT NULL,
    -- length <=20000 per DepartmentValidator
    `vision` TEXT NULL DEFAULT NULL,
    `mission` TEXT NULL DEFAULT NULL,
    `strategic_direction` LONGTEXT NULL DEFAULT NULL,
    `hod_message` LONGTEXT NULL DEFAULT NULL,
    -- length <=190 per DepartmentValidator
    `email` VARCHAR(190) NULL DEFAULT NULL,
    -- length <=50 per DepartmentValidator
    `phone` VARCHAR(50) NULL DEFAULT NULL,
    `location_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `hero_media_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    -- range 0..65535 per DepartmentValidator
    `display_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    -- five-state workflow confirmed in docs/DEPARTMENTS.md and
    -- ContentWorkflowService::TRANSITIONS
    `status` ENUM('draft','under_review','approved','published','archived') NOT NULL DEFAULT 'draft',
    `published_at` DATETIME NULL DEFAULT NULL,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_departments_slug` (`slug`),
    -- confirmed by DepartmentRepository::facultyNameExists (uniqueness scoped
    -- to faculty, not global)
    UNIQUE KEY `uq_departments_faculty_name` (`faculty_id`, `name`),
    KEY `idx_departments_status_published` (`status`, `published_at`, `deleted_at`)
    -- seed minimum: 5 departments (DatabaseVerifier)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `positions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(150) NOT NULL,
    -- confirmed values in use: dean, deputy_dean, head_of_department
    `code` VARCHAR(100) NOT NULL,
    -- confirmed member "department_leadership" (StaffValidator); others
    -- -- inferred by symmetry (dean/deputy_dean imply faculty leadership)
    `position_type` ENUM('faculty_leadership','department_leadership','academic','administrative','other') NOT NULL DEFAULT 'academic',
    -- ORDER BY hierarchy_level confirmed in StaffRepository::positions()
    `hierarchy_level` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_positions_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SECTION 3: Staff directory
-- Confirmed by: StaffRepository, StaffValidator, DepartmentRepository,
-- tests/integration/DepartmentFlowTest.php (literal staff/staff_positions
-- INSERTs), bin/migrate-staff-office-room.php (office_room column + type),
-- docs/STAFF.md.
-- =============================================================================

CREATE TABLE `expertise_areas` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(150) NOT NULL,
    -- confirmed queried by slug in StaffRepository::publicExpertise
    `slug` VARCHAR(170) NOT NULL,
    -- confirmed values: "active" filtered/inserted throughout
    `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_expertise_areas_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `staff` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `faculty_id` BIGINT UNSIGNED NOT NULL,
    -- length <=50, uniqueness checked (StaffRepository::staffNumberExists)
    `staff_number` VARCHAR(50) NULL DEFAULT NULL,
    -- length <=30 per StaffValidator
    `honorific_title` VARCHAR(30) NULL DEFAULT NULL,
    -- length <=100 per StaffValidator
    `first_name` VARCHAR(100) NOT NULL,
    `middle_name` VARCHAR(100) NULL DEFAULT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    -- length <=120 per StaffValidator
    `post_nominals` VARCHAR(120) NULL DEFAULT NULL,
    -- length <=240, lowercase/hyphen pattern per StaffValidator
    `slug` VARCHAR(240) NOT NULL,
    -- confirmed values in StaffRepository::publicCategories FIELD() ordering
    `staff_category` ENUM('academic','research','technical','administrative','support','visiting','emeritus','other') NOT NULL,
    -- length <=65535 per StaffValidator -> TEXT capacity
    `short_biography` TEXT NULL DEFAULT NULL,
    `biography` LONGTEXT NULL DEFAULT NULL,
    `research_summary` LONGTEXT NULL DEFAULT NULL,
    `teaching_summary` LONGTEXT NULL DEFAULT NULL,
    `supervision_interests` LONGTEXT NULL DEFAULT NULL,
    -- length <=190, unique per StaffRepository::emailExists
    `institutional_email` VARCHAR(190) NULL DEFAULT NULL,
    -- links this profile to a login-capable users row for staff self-service;
    -- NULL until an account is provisioned (bin/provision-staff-accounts.php)
    `user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `alternative_email` VARCHAR(190) NULL DEFAULT NULL,
    -- length <=50 per StaffValidator
    `public_phone` VARCHAR(50) NULL DEFAULT NULL,
    `profile_media_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `office_location_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    -- confirmed literally: bin/migrate-staff-office-room.php
    -- ALTER TABLE staff ADD office_room VARCHAR(120) NULL AFTER office_location_id
    `office_room` VARCHAR(120) NULL DEFAULT NULL,
    -- length <=255 per StaffValidator
    `consultation_hours` VARCHAR(255) NULL DEFAULT NULL,
    `supervision_available` TINYINT(1) NOT NULL DEFAULT 0,
    `display_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `status` ENUM('draft','under_review','approved','published','archived') NOT NULL DEFAULT 'draft',
    `published_at` DATETIME NULL DEFAULT NULL,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_staff_slug` (`slug`),
    UNIQUE KEY `uq_staff_institutional_email` (`institutional_email`),
    UNIQUE KEY `uq_staff_staff_number` (`staff_number`),
    UNIQUE KEY `uq_staff_user_id` (`user_id`),
    KEY `idx_staff_status_published` (`status`, `published_at`, `deleted_at`),
    CONSTRAINT `fk_staff_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `staff_departments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `staff_id` BIGINT UNSIGNED NOT NULL,
    `department_id` BIGINT UNSIGNED NOT NULL,
    -- confirmed: sd.is_primary=1 filters throughout StaffRepository
    `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
    -- confirmed: sd.is_current=1 filters in Project/Publication/ResearchRepository
    `is_current` TINYINT(1) NOT NULL DEFAULT 1,
    `start_date` DATE NULL DEFAULT NULL,
    -- confirmed: sd.end_date IS NULL OR sd.end_date >= CURDATE() everywhere
    `end_date` DATE NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_staff_departments_staff` (`staff_id`),
    KEY `idx_staff_departments_department_primary` (`department_id`, `is_primary`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `staff_positions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `staff_id` BIGINT UNSIGNED NOT NULL,
    `position_id` BIGINT UNSIGNED NOT NULL,
    `faculty_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `department_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    -- length <=150 per StaffValidator
    `title_override` VARCHAR(150) NULL DEFAULT NULL,
    `start_date` DATE NULL DEFAULT NULL,
    `end_date` DATE NULL DEFAULT NULL,
    `is_current` TINYINT(1) NOT NULL DEFAULT 1,
    `display_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_staff_positions_staff_current` (`staff_id`, `is_current`),
    KEY `idx_staff_positions_department_current` (`department_id`, `is_current`),
    KEY `idx_staff_positions_faculty_current` (`faculty_id`, `is_current`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `staff_qualifications` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `staff_id` BIGINT UNSIGNED NOT NULL,
    -- length <=200 per StaffValidator relations()
    `qualification` VARCHAR(200) NOT NULL,
    `field_of_study` VARCHAR(200) NULL DEFAULT NULL,
    -- length <=255 per StaffValidator relations()
    `institution` VARCHAR(255) NULL DEFAULT NULL,
    -- length <=100 per StaffValidator relations()
    `country` VARCHAR(100) NULL DEFAULT NULL,
    `completion_year` SMALLINT UNSIGNED NULL DEFAULT NULL,
    `display_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_staff_qualifications_staff` (`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `staff_links` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `staff_id` BIGINT UNSIGNED NOT NULL,
    -- confirmed values: StaffValidator::LINK_TYPES
    `link_type` ENUM('orcid','google_scholar','researchgate','linkedin','institutional_repository','personal_website','other') NOT NULL,
    -- length <=120 per StaffValidator relations()
    `label` VARCHAR(120) NULL DEFAULT NULL,
    -- length <=500 per StaffValidator relations()
    `url` VARCHAR(500) NOT NULL,
    `display_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_staff_links_staff` (`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `staff_expertise` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `staff_id` BIGINT UNSIGNED NOT NULL,
    `expertise_area_id` BIGINT UNSIGNED NOT NULL,
    -- confirmed: first item in submitted array flagged primary (StaffRepository::replaceProfileRelations)
    `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
    `display_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_staff_expertise_staff_area` (`staff_id`, `expertise_area_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SECTION 4: Content workflow & audit
-- Confirmed by: docs/DATABASE_SCHEMA.md, AuditService::record(),
-- every *Repository::recordRevision()/recordApproval()/approvalHistory()
-- method (identical shape across Department/Staff/Programme/Research/
-- Project/Publication repositories).
-- =============================================================================

CREATE TABLE `content_revisions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    -- confirmed values in use: department, staff, programme, research_unit,
    -- project, publication (RESEARCH.md/DEPARTMENTS.md/STAFF.md/PROGRAMMES.md
    -- describe the same mechanism for research themes/partners; no literal
    -- entity_type string observed for those two)
    `entity_type` VARCHAR(50) NOT NULL,
    `entity_id` BIGINT UNSIGNED NOT NULL,
    `revision_number` INT UNSIGNED NOT NULL,
    `previous_data` JSON NULL DEFAULT NULL,
    `revised_data` JSON NOT NULL,
    `revised_by` BIGINT UNSIGNED NULL DEFAULT NULL,
    -- length -- assumed; note text is short editorial commentary
    `revision_note` VARCHAR(500) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_content_revisions_entity` (`entity_type`, `entity_id`, `revision_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `content_approvals` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `entity_type` VARCHAR(50) NOT NULL,
    `entity_id` BIGINT UNSIGNED NOT NULL,
    -- confirmed values: ContentWorkflowService::TRANSITIONS keys
    `action` ENUM('submitted','changes_requested','approved','published','archived','restored') NOT NULL,
    `acted_by` BIGINT UNSIGNED NULL DEFAULT NULL,
    `comment` TEXT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_content_approvals_entity` (`entity_type`, `entity_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `audit_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    -- exact parameter order/names confirmed by AuditService::record()
    `user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `action` VARCHAR(100) NOT NULL,
    `entity_type` VARCHAR(50) NULL DEFAULT NULL,
    `entity_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `ip_address` VARCHAR(45) NULL DEFAULT NULL,
    `user_agent` VARCHAR(255) NULL DEFAULT NULL,
    `metadata` JSON NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_audit_logs_user` (`user_id`),
    KEY `idx_audit_logs_entity` (`entity_type`, `entity_id`),
    KEY `idx_audit_logs_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SECTION 5: Documents
-- Confirmed by: AssetRepository, DocumentUploadService.
-- =============================================================================

CREATE TABLE `document_types` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(150) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `documents` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `document_type_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    -- -- assumed length, consistent with other title fields
    `title` VARCHAR(255) NOT NULL,
    `description` LONGTEXT NULL DEFAULT NULL,
    -- confirmed shape via DocumentUploadService::store() return array
    `original_name` VARCHAR(255) NOT NULL,
    `stored_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `mime_type` VARCHAR(150) NOT NULL,
    `file_size` INT UNSIGNED NOT NULL,
    -- confirmed: AssetRepository::documents() orders/filters d.effective_date
    `effective_date` DATE NULL DEFAULT NULL,
    -- confirmed: public listing filters d.is_public=1
    `is_public` TINYINT(1) NOT NULL DEFAULT 0,
    `status` ENUM('draft','under_review','approved','published','archived') NOT NULL DEFAULT 'draft',
    `published_at` DATETIME NULL DEFAULT NULL,
    `uploaded_by` BIGINT UNSIGNED NULL DEFAULT NULL,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_documents_status_public` (`status`, `is_public`, `deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SECTION 6: Programmes & curriculum
-- Confirmed by: ProgrammeRepository, ProgrammeValidator, CurriculumRepository,
-- CurriculumValidator, docs/PROGRAMMES.md, docs/HANDBOOK_SEED.md,
-- bin/curriculum-check.php, bin/programme-check.php,
-- bin/seed-handbook-curricula.php.
-- =============================================================================

CREATE TABLE `programme_levels` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    -- confirmed values: UG, PGD, MASTERS, PHD (ProgrammeRepository::publicCategoryCounts)
    `code` VARCHAR(20) NOT NULL,
    `display_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_programme_levels_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `programmes` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `programme_level_id` BIGINT UNSIGNED NOT NULL,
    -- length <=50 per ProgrammeValidator
    `programme_code` VARCHAR(50) NULL DEFAULT NULL,
    -- length <=255 per ProgrammeValidator
    `name` VARCHAR(255) NOT NULL,
    `award_title` VARCHAR(255) NULL DEFAULT NULL,
    -- length <=280 per ProgrammeValidator
    `slug` VARCHAR(280) NOT NULL,
    `overview` LONGTEXT NULL DEFAULT NULL,
    `why_study` LONGTEXT NULL DEFAULT NULL,
    `objectives` LONGTEXT NULL DEFAULT NULL,
    `learning_outcomes` LONGTEXT NULL DEFAULT NULL,
    `entry_requirements` LONGTEXT NULL DEFAULT NULL,
    `career_opportunities` LONGTEXT NULL DEFAULT NULL,
    `practical_training` LONGTEXT NULL DEFAULT NULL,
    -- range 0.1..99.9 per ProgrammeValidator
    `duration_years` DECIMAL(3,1) NULL DEFAULT NULL,
    -- length <=100 per ProgrammeValidator
    `duration_text` VARCHAR(100) NULL DEFAULT NULL,
    `study_mode` ENUM('full_time','part_time','both','other') NOT NULL DEFAULT 'full_time',
    `delivery_mode` ENUM('face_to_face','online','blended','other') NOT NULL DEFAULT 'face_to_face',
    `accreditation` LONGTEXT NULL DEFAULT NULL,
    -- length -- assumed; FILTER_VALIDATE_URL enforced
    `application_url` VARCHAR(500) NULL DEFAULT NULL,
    `hero_media_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `display_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `status` ENUM('draft','under_review','approved','published','archived') NOT NULL DEFAULT 'draft',
    `published_at` DATETIME NULL DEFAULT NULL,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_programmes_slug` (`slug`),
    UNIQUE KEY `uq_programmes_code` (`programme_code`),
    KEY `idx_programmes_status_published` (`status`, `published_at`, `deleted_at`),
    -- inferred check: mirrors ProgrammeValidator's 0.1..99.9 range
    CONSTRAINT `chk_programmes_duration_years` CHECK (`duration_years` IS NULL OR (`duration_years` > 0 AND `duration_years` <= 99.9))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `programme_departments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `programme_id` BIGINT UNSIGNED NOT NULL,
    `department_id` BIGINT UNSIGNED NOT NULL,
    -- confirmed: pd.is_lead_department=1 filter throughout
    `is_lead_department` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_programme_departments_programme_department` (`programme_id`, `department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `courses` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    -- pattern ^[A-Z]{2,8}[0-9]{3,5}$ per CurriculumValidator
    `course_code` VARCHAR(20) NOT NULL,
    -- length <=255 per CurriculumValidator
    `title` VARCHAR(255) NOT NULL,
    -- -- assumed: derived via slugify() in seed-handbook-curricula.php
    `slug` VARCHAR(255) NULL DEFAULT NULL,
    `description` LONGTEXT NULL DEFAULT NULL,
    -- range 0..30, 2dp per CurriculumValidator
    `default_credit_units` DECIMAL(4,2) NULL DEFAULT NULL,
    `course_type` ENUM('core','elective','audited') NOT NULL DEFAULT 'core',
    -- confirmed values queried: draft, published (bin/curriculum-check.php)
    `status` ENUM('draft','published') NOT NULL DEFAULT 'draft',
    `deleted_at` DATETIME NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_courses_course_code` (`course_code`),
    -- inferred check: mirrors CurriculumValidator's 0..30 credit range
    CONSTRAINT `chk_courses_default_credit_units` CHECK (`default_credit_units` IS NULL OR (`default_credit_units` > 0 AND `default_credit_units` <= 30))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `curriculum_versions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `programme_id` BIGINT UNSIGNED NOT NULL,
    -- length <=120 per CurriculumValidator
    `version_name` VARCHAR(120) NOT NULL,
    `effective_year` SMALLINT UNSIGNED NOT NULL,
    `expiry_year` SMALLINT UNSIGNED NULL DEFAULT NULL,
    -- length <=150 per CurriculumValidator
    `approval_reference` VARCHAR(150) NULL DEFAULT NULL,
    `approval_date` DATE NULL DEFAULT NULL,
    `total_credit_units` DECIMAL(6,2) NULL DEFAULT NULL,
    -- confirmed values: draft, published (CurriculumRepository::publish/createVersion)
    `status` ENUM('draft','published') NOT NULL DEFAULT 'draft',
    `is_current` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_curriculum_versions_programme_current` (`programme_id`, `is_current`),
    -- inferred check: an expiry year cannot precede its own effective year
    CONSTRAINT `chk_curriculum_versions_year_order` CHECK (`expiry_year` IS NULL OR `expiry_year` >= `effective_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `curriculum_courses` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `curriculum_version_id` BIGINT UNSIGNED NOT NULL,
    `course_id` BIGINT UNSIGNED NOT NULL,
    -- range 1..10 per CurriculumValidator
    `study_year` TINYINT UNSIGNED NOT NULL,
    -- range 1..3 (3 = recess term) per CurriculumValidator
    `semester` TINYINT UNSIGNED NOT NULL,
    `requirement_type` ENUM('core','elective','optional','audited') NOT NULL DEFAULT 'core',
    `credit_units_override` DECIMAL(4,2) NULL DEFAULT NULL,
    -- range 0..1000 per CurriculumValidator
    `display_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_curriculum_courses_version` (`curriculum_version_id`, `study_year`, `semester`, `display_order`),
    KEY `idx_curriculum_courses_course` (`course_id`),
    -- inferred check: mirrors CurriculumValidator's study_year range
    CONSTRAINT `chk_curriculum_courses_study_year` CHECK (`study_year` BETWEEN 1 AND 10),
    -- inferred check: mirrors CurriculumValidator's semester range
    CONSTRAINT `chk_curriculum_courses_semester` CHECK (`semester` BETWEEN 1 AND 3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Referenced explicitly in docs/PROGRAMMES.md: "the existing
-- course_prerequisites table is unchanged and ready for [prerequisite
-- editing]." Confirmed to exist; NOT observed in any repository/controller
-- SQL, so its column list below is entirely -- inferred from the obvious
-- pivot shape (course -> prerequisite course) and should be treated as
-- low-confidence.
CREATE TABLE `course_prerequisites` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `course_id` BIGINT UNSIGNED NOT NULL,
    `prerequisite_course_id` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_course_prerequisites_pair` (`course_id`, `prerequisite_course_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SECTION 7: Research core (units, projects, publications)
-- Confirmed by: ResearchRepository, ResearchUnitValidator, ProjectRepository,
-- ProjectValidator, PublicationRepository, PublicationValidator,
-- docs/RESEARCH.md, bin/research-check.php, bin/project-check.php,
-- bin/publication-check.php.
-- =============================================================================

CREATE TABLE `research_unit_types` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(150) NOT NULL,
    `code` VARCHAR(50) NOT NULL,
    `description` TEXT NULL DEFAULT NULL,
    `display_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_research_unit_types_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `research_units` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `faculty_id` BIGINT UNSIGNED NOT NULL,
    `department_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `unit_type_id` BIGINT UNSIGNED NOT NULL,
    -- length <=255 per ResearchUnitValidator
    `name` VARCHAR(255) NOT NULL,
    -- length <=40 per ResearchUnitValidator
    `acronym` VARCHAR(40) NULL DEFAULT NULL,
    -- length <=280 per ResearchUnitValidator
    `slug` VARCHAR(280) NOT NULL,
    `overview` LONGTEXT NULL DEFAULT NULL,
    `research_focus` LONGTEXT NULL DEFAULT NULL,
    `capabilities` LONGTEXT NULL DEFAULT NULL,
    `student_opportunities` LONGTEXT NULL DEFAULT NULL,
    -- field key confirmed in ResearchUnitValidator text[] list
    `industry_services` LONGTEXT NULL DEFAULT NULL,
    `location_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `hero_media_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `email` VARCHAR(190) NULL DEFAULT NULL,
    `phone` VARCHAR(50) NULL DEFAULT NULL,
    `website_url` VARCHAR(500) NULL DEFAULT NULL,
    `display_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `status` ENUM('draft','under_review','approved','published','archived') NOT NULL DEFAULT 'draft',
    `published_at` DATETIME NULL DEFAULT NULL,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_research_units_slug` (`slug`),
    KEY `idx_research_units_status_published` (`status`, `published_at`, `deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `research_unit_members` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `research_unit_id` BIGINT UNSIGNED NOT NULL,
    `staff_id` BIGINT UNSIGNED NOT NULL,
    -- confirmed values/ordering: ResearchRepository FIELD() clause
    `membership_role` ENUM('lead','deputy_lead','researcher','technician','graduate_researcher','student_researcher','affiliate','external_collaborator','other') NOT NULL,
    -- length -- assumed, consistent with staff_positions.title_override
    `role_title` VARCHAR(150) NULL DEFAULT NULL,
    `start_date` DATE NULL DEFAULT NULL,
    `end_date` DATE NULL DEFAULT NULL,
    `is_current` TINYINT(1) NOT NULL DEFAULT 1,
    `display_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_research_unit_members_unit_current` (`research_unit_id`, `is_current`),
    KEY `idx_research_unit_members_staff` (`staff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `facilities` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `research_unit_id` BIGINT UNSIGNED NOT NULL,
    -- length <=255 per InnovationValidator::facility
    `name` VARCHAR(255) NOT NULL,
    -- confirmed values: InnovationValidator::FACILITY_TYPES
    `facility_type` ENUM('laboratory','workshop','studio','computer_lab','testing_facility','field_site','office','other') NOT NULL,
    `description` LONGTEXT NULL DEFAULT NULL,
    `location_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `status` ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_facilities_unit` (`research_unit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `equipment` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `research_unit_id` BIGINT UNSIGNED NOT NULL,
    `facility_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    -- length <=255 per InnovationValidator::equipment
    `name` VARCHAR(255) NOT NULL,
    -- length <=180 per InnovationValidator::equipment
    `manufacturer` VARCHAR(180) NULL DEFAULT NULL,
    -- length <=120 per InnovationValidator::equipment
    `model` VARCHAR(120) NULL DEFAULT NULL,
    -- length <=100 per InnovationValidator::equipment
    `asset_reference` VARCHAR(100) NULL DEFAULT NULL,
    `capabilities` LONGTEXT NULL DEFAULT NULL,
    -- confirmed values: InnovationValidator::AVAILABILITY
    `availability_status` ENUM('available','restricted','maintenance','unavailable','retired') NOT NULL DEFAULT 'available',
    `external_use_allowed` TINYINT(1) NOT NULL DEFAULT 0,
    `booking_information` LONGTEXT NULL DEFAULT NULL,
    `status` ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_equipment_unit` (`research_unit_id`),
    KEY `idx_equipment_facility` (`facility_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `projects` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `lead_department_id` BIGINT UNSIGNED NOT NULL,
    -- length <=300 per ProjectValidator
    `title` VARCHAR(300) NOT NULL,
    -- length <=120 per ProjectValidator
    `short_title` VARCHAR(120) NULL DEFAULT NULL,
    -- length <=320 per ProjectValidator
    `slug` VARCHAR(320) NOT NULL,
    -- length <=100000 per ProjectValidator -> LONGTEXT
    `summary` LONGTEXT NULL DEFAULT NULL,
    `objectives` LONGTEXT NULL DEFAULT NULL,
    `methodology` LONGTEXT NULL DEFAULT NULL,
    `expected_outputs` LONGTEXT NULL DEFAULT NULL,
    `outcomes` LONGTEXT NULL DEFAULT NULL,
    `impact` LONGTEXT NULL DEFAULT NULL,
    -- confirmed values: ProjectValidator::STATUSES; FIELD() ordering in ProjectRepository
    `project_status` ENUM('planned','ongoing','completed','suspended','cancelled') NOT NULL DEFAULT 'planned',
    `start_date` DATE NULL DEFAULT NULL,
    `end_date` DATE NULL DEFAULT NULL,
    `budget_amount` DECIMAL(14,2) NULL DEFAULT NULL,
    -- 3-letter ISO code pattern per ProjectValidator
    `currency_code` CHAR(3) NULL DEFAULT NULL,
    -- length <=150 per ProjectValidator
    `funding_reference` VARCHAR(150) NULL DEFAULT NULL,
    `project_url` VARCHAR(500) NULL DEFAULT NULL,
    `hero_media_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    -- column name confirmed: "publication_status" (distinct from project_status)
    -- throughout ProjectRepository
    `publication_status` ENUM('draft','under_review','approved','published','archived') NOT NULL DEFAULT 'draft',
    `published_at` DATETIME NULL DEFAULT NULL,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_projects_slug` (`slug`),
    KEY `idx_projects_publication_status` (`publication_status`, `published_at`, `deleted_at`),
    KEY `idx_projects_project_status` (`project_status`),
    -- inferred check: mirrors ProjectValidator's end>=start rule
    CONSTRAINT `chk_projects_date_order` CHECK (`end_date` IS NULL OR `start_date` IS NULL OR `end_date` >= `start_date`),
    -- inferred check: mirrors ProjectValidator's non-negative budget rule
    CONSTRAINT `chk_projects_budget_non_negative` CHECK (`budget_amount` IS NULL OR `budget_amount` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `project_members` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `project_id` BIGINT UNSIGNED NOT NULL,
    `staff_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    -- length -- assumed, consistent with staff.first_name+last_name budget
    `external_member_name` VARCHAR(200) NULL DEFAULT NULL,
    `external_affiliation` VARCHAR(255) NULL DEFAULT NULL,
    `external_email` VARCHAR(190) NULL DEFAULT NULL,
    -- confirmed values/ordering: ProjectRepository::members() FIELD() clause
    `project_role` ENUM('principal_investigator','co_investigator','project_manager','researcher','technical_staff','student','advisor','collaborator','other') NOT NULL,
    `role_title` VARCHAR(150) NULL DEFAULT NULL,
    `start_date` DATE NULL DEFAULT NULL,
    `end_date` DATE NULL DEFAULT NULL,
    `display_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_project_members_project` (`project_id`, `display_order`),
    KEY `idx_project_members_staff` (`staff_id`)
    -- The "at least one of staff_id / external_member_name" rule from
    -- docs/RESEARCH.md is enforced in ProjectService instead of as a DB
    -- CHECK constraint: staff_id also carries an ON DELETE SET NULL foreign
    -- key below, and MySQL disallows a CHECK constraint on a column that
    -- also has a SET NULL referential action (a cascading delete could
    -- otherwise violate the check) -- confirmed by ERROR 1901 on import.
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `project_research_units` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `project_id` BIGINT UNSIGNED NOT NULL,
    `research_unit_id` BIGINT UNSIGNED NOT NULL,
    `is_lead_unit` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_project_research_units_pair` (`project_id`, `research_unit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Added for the research-agenda content build (2026-09): decomposes each
-- project's multi-year "Research Playbook" entry into its numbered mini-
-- project/milestone rows (title, expected output, free-text timeline range,
-- target academic level, lead). timeline_text is intentionally free text
-- (source data uses ranges like "Aug-Dec 2026", not fixed dates).
CREATE TABLE `project_milestones` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `project_id` BIGINT UNSIGNED NOT NULL,
    `sequence_number` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `title` VARCHAR(300) NOT NULL,
    `expected_output` TEXT NULL DEFAULT NULL,
    `timeline_text` VARCHAR(100) NULL DEFAULT NULL,
    `target_level` SET('bachelors','masters','phd','staff') NOT NULL DEFAULT '',
    `lead_name_text` VARCHAR(200) NULL DEFAULT NULL,
    `lead_staff_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `student_names_text` VARCHAR(500) NULL DEFAULT NULL,
    `status` ENUM('planned','in_progress','completed','dropped') NOT NULL DEFAULT 'planned',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_project_milestones_project_seq` (`project_id`, `sequence_number`),
    KEY `idx_project_milestones_lead_staff` (`lead_staff_id`),
    CONSTRAINT `fk_project_milestones_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_project_milestones_lead_staff` FOREIGN KEY (`lead_staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `publication_types` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(150) NOT NULL,
    -- confirmed 9 seeded types per docs/RESEARCH.md: journal_article,
    -- conference_paper, book, book_chapter, technical_report, policy_brief,
    -- thesis_dissertation, dataset, other -- values inferred from prose
    `code` VARCHAR(50) NOT NULL,
    `display_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_publication_types_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `publications` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `publication_type_id` BIGINT UNSIGNED NOT NULL,
    -- PublicationValidator allows up to 5000 chars; stored as TEXT rather than
    -- VARCHAR(5000) to avoid InnoDB in-row size pressure from a very wide
    -- VARCHAR alongside this table's many other columns -- inferred type choice
    `title` TEXT NOT NULL,
    -- length <=320 per PublicationValidator
    `slug` VARCHAR(320) NOT NULL,
    `abstract` LONGTEXT NULL DEFAULT NULL,
    `journal_name` VARCHAR(255) NULL DEFAULT NULL,
    `publisher` VARCHAR(255) NULL DEFAULT NULL,
    `publication_year` SMALLINT UNSIGNED NULL DEFAULT NULL,
    `publication_date` DATE NULL DEFAULT NULL,
    -- length <=50 per PublicationValidator
    `volume` VARCHAR(50) NULL DEFAULT NULL,
    `issue` VARCHAR(50) NULL DEFAULT NULL,
    `page_range` VARCHAR(50) NULL DEFAULT NULL,
    -- length <=255, pattern ^10\.\d{4,9}/\S+$ per PublicationValidator
    `doi` VARCHAR(255) NULL DEFAULT NULL,
    `isbn` VARCHAR(50) NULL DEFAULT NULL,
    `external_url` VARCHAR(500) NULL DEFAULT NULL,
    -- confirmed values: PublicationValidator::ACCESS
    `access_type` ENUM('open_access','subscription','restricted','unknown') NOT NULL DEFAULT 'unknown',
    `citation_text` LONGTEXT NULL DEFAULT NULL,
    `status` ENUM('draft','under_review','approved','published','archived') NOT NULL DEFAULT 'draft',
    `published_at` DATETIME NULL DEFAULT NULL,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_publications_slug` (`slug`),
    UNIQUE KEY `uq_publications_doi` (`doi`),
    KEY `idx_publications_status_published` (`status`, `published_at`, `deleted_at`),
    -- inferred check: mirrors PublicationValidator's 1800..(current year + 2) range
    -- as a static, generous bound since CHECK cannot reference NOW() portably
    CONSTRAINT `chk_publications_year_range` CHECK (`publication_year` IS NULL OR (`publication_year` BETWEEN 1800 AND 2100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `publication_authors` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `publication_id` BIGINT UNSIGNED NOT NULL,
    `staff_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `external_author_name` VARCHAR(255) NULL DEFAULT NULL,
    `external_affiliation` VARCHAR(255) NULL DEFAULT NULL,
    -- length -- assumed, standard ORCID string length
    `external_orcid` VARCHAR(50) NULL DEFAULT NULL,
    `author_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `is_corresponding` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_publication_authors_publication_order` (`publication_id`, `author_order`),
    KEY `idx_publication_authors_staff` (`staff_id`)
    -- Same non-exclusive-identity pattern as project_members, and the same
    -- reason it's not a DB CHECK constraint here either -- see the comment
    -- on project_members above (ERROR 1901: CHECK + ON DELETE SET NULL on
    -- the same column is rejected by MySQL).
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `publication_projects` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `publication_id` BIGINT UNSIGNED NOT NULL,
    `project_id` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_publication_projects_pair` (`publication_id`, `project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `publication_research_units` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `publication_id` BIGINT UNSIGNED NOT NULL,
    `research_unit_id` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_publication_research_units_pair` (`publication_id`, `research_unit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SECTION 8: Research metadata (themes, SDGs, partners)
-- Confirmed by: ResearchMetadataRepository, ResearchMetadataValidator,
-- docs/RESEARCH.md, bin/metadata-check.php.
-- =============================================================================

CREATE TABLE `research_themes` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `parent_theme_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    -- length <=200 per ResearchMetadataValidator::theme
    `name` VARCHAR(200) NOT NULL,
    `slug` VARCHAR(220) NOT NULL,
    -- length <=20000 per ResearchMetadataValidator::theme
    `description` TEXT NULL DEFAULT NULL,
    -- length <=100 per ResearchMetadataValidator::theme
    `icon` VARCHAR(100) NULL DEFAULT NULL,
    `display_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    -- no content_revisions/content_approvals rows observed for themes;
    -- docs describe "draft creation, direct publication, ... archiving, and
    -- restoration" only -- inferred simplified 3-state workflow
    `status` ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    `published_at` DATETIME NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_research_themes_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `research_theme_departments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `research_theme_id` BIGINT UNSIGNED NOT NULL,
    `department_id` BIGINT UNSIGNED NOT NULL,
    `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_research_theme_departments_pair` (`research_theme_id`, `department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sdgs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    -- confirmed columns: ResearchMetadataRepository::sdgs() SELECT id, code, name, colour_code
    `code` VARCHAR(10) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `colour_code` VARCHAR(7) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_sdgs_code` (`code`)
    -- seed exact count: 17 (DatabaseVerifier + bin/metadata-check.php hard exit(1) if != 17)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Composite-key pivot tables below use (parent_id, child_id) as the primary
-- key rather than a surrogate id, because ResearchMetadataRepository issues
-- `INSERT ... ON DUPLICATE KEY UPDATE` against exactly that pair.
CREATE TABLE `project_themes` (
    `project_id` BIGINT UNSIGNED NOT NULL,
    `research_theme_id` BIGINT UNSIGNED NOT NULL,
    `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`project_id`, `research_theme_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `project_sdgs` (
    `project_id` BIGINT UNSIGNED NOT NULL,
    `sdg_id` BIGINT UNSIGNED NOT NULL,
    `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`project_id`, `sdg_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `publication_themes` (
    `publication_id` BIGINT UNSIGNED NOT NULL,
    `research_theme_id` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`publication_id`, `research_theme_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `partners` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    -- length <=255 per ResearchMetadataValidator::partner
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(280) NOT NULL,
    -- confirmed values: ResearchMetadataValidator::PARTNER_TYPES
    `partner_type` ENUM('university','government','industry','ngo','funder','professional_body','community','international_agency','other') NOT NULL DEFAULT 'other',
    -- length <=100 per ResearchMetadataValidator::partner
    `country` VARCHAR(100) NULL DEFAULT NULL,
    -- length <=500 per ResearchMetadataValidator::partner
    `website_url` VARCHAR(500) NULL DEFAULT NULL,
    `logo_media_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `description` LONGTEXT NULL DEFAULT NULL,
    -- ResearchMetadataRepository::changePartnerStatus never sets published_at,
    -- unlike every other workflow table -- inferred simplified 3-state
    -- workflow with no publish timestamp column
    `status` ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_partners_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `project_partners` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `project_id` BIGINT UNSIGNED NOT NULL,
    `partner_id` BIGINT UNSIGNED NOT NULL,
    -- confirmed values/ordering: ResearchMetadataRepository::projectPartners FIELD() clause
    `partner_role` ENUM('lead','funder','implementer','technical_partner','academic_partner','industry_partner','community_partner','other') NOT NULL,
    `contribution` TEXT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    -- confirmed by addProjectPartner()'s ON DUPLICATE KEY UPDATE against this pair
    UNIQUE KEY `uq_project_partners_pair` (`project_id`, `partner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SECTION 9: Engagement & innovation
-- Confirmed by: EngagementRepository, EngagementValidator, InnovationRepository,
-- InnovationValidator, docs/RESEARCH.md.
-- =============================================================================

CREATE TABLE `partnerships` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `partner_id` BIGINT UNSIGNED NOT NULL,
    `faculty_id` BIGINT UNSIGNED NOT NULL,
    -- length <=255 per EngagementValidator::partnership
    `title` VARCHAR(255) NOT NULL,
    -- confirmed values: EngagementValidator::partnership() in_array list
    `partnership_type` ENUM('mou','research','teaching','student_exchange','staff_exchange','industry','community','funding','other') NOT NULL DEFAULT 'other',
    `description` LONGTEXT NULL DEFAULT NULL,
    `start_date` DATE NULL DEFAULT NULL,
    `end_date` DATE NULL DEFAULT NULL,
    -- length <=120 per EngagementValidator::partnership
    `reference_number` VARCHAR(120) NULL DEFAULT NULL,
    `document_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `status` ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    `published_at` DATETIME NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_partnerships_status` (`status`, `published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `impact_stories` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `department_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `project_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    -- length <=280 per EngagementValidator::impact
    `title` VARCHAR(280) NOT NULL,
    -- length <=300 -- assumed, consistent with EngagementValidator::impact's slug fallback
    `slug` VARCHAR(300) NOT NULL,
    -- length <=10000 per EngagementValidator::impact
    `summary` TEXT NULL DEFAULT NULL,
    `body` LONGTEXT NULL DEFAULT NULL,
    -- length <=20000 per EngagementValidator::impact
    `beneficiaries` TEXT NULL DEFAULT NULL,
    -- length <=255 per EngagementValidator::impact
    `location_text` VARCHAR(255) NULL DEFAULT NULL,
    `impact_date` DATE NULL DEFAULT NULL,
    `featured_media_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `status` ENUM('draft','under_review','approved','published','archived') NOT NULL DEFAULT 'draft',
    `published_at` DATETIME NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_impact_stories_slug` (`slug`),
    KEY `idx_impact_stories_status_published` (`status`, `published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `innovations` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `project_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `lead_department_id` BIGINT UNSIGNED NOT NULL,
    -- length <=255 per InnovationValidator::innovation
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(280) NOT NULL,
    -- confirmed values: InnovationValidator::TYPES
    `innovation_type` ENUM('prototype','patent','software','device','process','product','service','other') NOT NULL DEFAULT 'other',
    `description` LONGTEXT NULL DEFAULT NULL,
    -- confirmed values: InnovationValidator::STAGES
    `development_stage` ENUM('concept','research','prototype','pilot','validated','commercialised','scaled','other') NULL DEFAULT NULL,
    -- length <=200 per InnovationValidator::innovation
    `intellectual_property_status` VARCHAR(200) NULL DEFAULT NULL,
    -- length <=500 per InnovationValidator::innovation
    `external_url` VARCHAR(500) NULL DEFAULT NULL,
    `hero_media_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `status` ENUM('draft','under_review','approved','published','archived') NOT NULL DEFAULT 'draft',
    `published_at` DATETIME NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_innovations_slug` (`slug`),
    KEY `idx_innovations_status_published` (`status`, `published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SECTION 10: Site content (news, events, pages)
-- Confirmed by: SiteContentRepository, SiteContentValidator, docs/UI_UX.md.
-- =============================================================================

CREATE TABLE `news_categories` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(150) NOT NULL,
    -- confirmed queried: c.slug AS category_slug in SiteContentRepository::news()
    `slug` VARCHAR(170) NULL DEFAULT NULL,
    `display_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_news_categories_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `news` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `category_id` BIGINT UNSIGNED NOT NULL,
    -- length <=300 per SiteContentValidator::news
    `title` VARCHAR(300) NOT NULL,
    `slug` VARCHAR(320) NOT NULL,
    -- length <=5000 per SiteContentValidator::news
    `summary` TEXT NULL DEFAULT NULL,
    -- length <=150000 per SiteContentValidator::news -> LONGTEXT
    `body` LONGTEXT NOT NULL,
    `featured_media_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `author_user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `article_date` DATE NOT NULL,
    `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
    `allow_sharing` TINYINT(1) NOT NULL DEFAULT 0,
    `meta_title` VARCHAR(255) NULL DEFAULT NULL,
    -- length <=320 per SiteContentValidator::news
    `meta_description` VARCHAR(320) NULL DEFAULT NULL,
    `status` ENUM('draft','under_review','approved','published','archived') NOT NULL DEFAULT 'draft',
    `published_at` DATETIME NULL DEFAULT NULL,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_news_slug` (`slug`),
    KEY `idx_news_status_published` (`status`, `published_at`, `deleted_at`),
    KEY `idx_news_featured_date` (`is_featured`, `article_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `news_media` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `news_id` BIGINT UNSIGNED NOT NULL,
    `media_id` BIGINT UNSIGNED NOT NULL,
    `display_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_news_media_pair` (`news_id`, `media_id`),
    KEY `idx_news_media_news_order` (`news_id`, `display_order`),
    CONSTRAINT `fk_news_media_news` FOREIGN KEY (`news_id`) REFERENCES `news` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_news_media_media` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `news_departments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `news_id` BIGINT UNSIGNED NOT NULL,
    `department_id` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_news_departments_pair` (`news_id`, `department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `event_categories` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(150) NOT NULL,
    `display_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `events` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `category_id` BIGINT UNSIGNED NOT NULL,
    -- length <=300 per SiteContentValidator::event
    `title` VARCHAR(300) NOT NULL,
    `slug` VARCHAR(320) NOT NULL,
    `summary` TEXT NULL DEFAULT NULL,
    -- length <=150000 per SiteContentValidator::event -> LONGTEXT
    `description` LONGTEXT NULL DEFAULT NULL,
    `starts_at` DATETIME NOT NULL,
    `ends_at` DATETIME NULL DEFAULT NULL,
    -- fixed literal value confirmed in SiteContentValidator::event
    `timezone_name` VARCHAR(64) NOT NULL DEFAULT 'Africa/Kampala',
    -- length <=255 per SiteContentValidator::event
    `venue_name` VARCHAR(255) NULL DEFAULT NULL,
    `location_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `contact_staff_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    -- length <=200 per SiteContentValidator::event
    `contact_name` VARCHAR(200) NULL DEFAULT NULL,
    -- length <=190 per SiteContentValidator::event
    `contact_email` VARCHAR(190) NULL DEFAULT NULL,
    `registration_url` VARCHAR(500) NULL DEFAULT NULL,
    `registration_deadline` DATETIME NULL DEFAULT NULL,
    `featured_media_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    -- confirmed values: SiteContentValidator::event in_array list
    `event_status` ENUM('scheduled','postponed','cancelled','completed') NOT NULL DEFAULT 'scheduled',
    `status` ENUM('draft','under_review','approved','published','archived') NOT NULL DEFAULT 'draft',
    `published_at` DATETIME NULL DEFAULT NULL,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_events_slug` (`slug`),
    KEY `idx_events_status_published` (`status`, `published_at`, `deleted_at`),
    KEY `idx_events_starts_at` (`starts_at`),
    -- inferred check: mirrors SiteContentValidator's end>=start rule
    CONSTRAINT `chk_events_date_order` CHECK (`ends_at` IS NULL OR `ends_at` >= `starts_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `event_departments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `event_id` BIGINT UNSIGNED NOT NULL,
    `department_id` BIGINT UNSIGNED NOT NULL,
    -- confirmed: ed.is_lead_organiser=1 filter in SiteContentRepository::findEvent
    `is_lead_organiser` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_event_departments_pair` (`event_id`, `department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `pages` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `parent_page_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    -- length <=255 per SiteContentValidator::page
    `title` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL,
    -- confirmed default: SiteContentValidator::page defaults to "standard"
    `page_template` VARCHAR(100) NOT NULL DEFAULT 'standard',
    `meta_title` VARCHAR(255) NULL DEFAULT NULL,
    `meta_description` VARCHAR(320) NULL DEFAULT NULL,
    -- confirmed literally added by database/seeds/page-hero-images.php:
    -- ALTER TABLE pages ADD COLUMN hero_media_id BIGINT UNSIGNED NULL AFTER meta_description
    -- + ALTER TABLE pages ADD INDEX idx_pages_hero_media_id (hero_media_id)
    `hero_media_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `display_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `show_in_navigation` TINYINT(1) NOT NULL DEFAULT 1,
    `status` ENUM('draft','under_review','approved','published','archived') NOT NULL DEFAULT 'draft',
    `published_at` DATETIME NULL DEFAULT NULL,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    -- confirmed: about-pages.php seed uses `ON DUPLICATE KEY UPDATE` keyed on slug
    UNIQUE KEY `uq_pages_slug` (`slug`),
    KEY `idx_pages_hero_media_id` (`hero_media_id`),
    KEY `idx_pages_status_published` (`status`, `published_at`, `deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `page_sections` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `page_id` BIGINT UNSIGNED NOT NULL,
    -- confirmed values: SiteContentValidator::section $types list
    `section_type` ENUM('rich_text','image_text','cards','statistics','call_to_action','quote','gallery','video','custom') NOT NULL DEFAULT 'rich_text',
    `heading` VARCHAR(255) NULL DEFAULT NULL,
    `subheading` VARCHAR(255) NULL DEFAULT NULL,
    -- length <=150000 per SiteContentValidator::section -> LONGTEXT
    `body` LONGTEXT NULL DEFAULT NULL,
    `media_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    -- length <=100 per SiteContentValidator::section
    `button_label` VARCHAR(100) NULL DEFAULT NULL,
    `button_url` VARCHAR(500) NULL DEFAULT NULL,
    -- confirmed: deans-office-content-2026.php stores a JSON provenance marker
    -- here, e.g. {"source":"deans-office-2026","key":"overview"}; this is one
    -- of the 4 JSON columns that trigger MariaDB's automatic JSON_VALID checks
    `settings_json` JSON NULL DEFAULT NULL,
    `display_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `is_visible` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_page_sections_page_order` (`page_id`, `display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SECTION 11: Homepage & site-wide settings
-- These three tables are reconstructed VERBATIM from literal `CREATE TABLE
-- IF NOT EXISTS` DDL embedded in database/seeds/site-settings.php and
-- database/seeds/homepage-sections.php, which ran this exact SQL against the
-- live database. This is the only section of this file that is not an
-- inference.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `site_settings` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(120) NOT NULL UNIQUE,
    `setting_value` LONGTEXT NULL,
    `value_type` ENUM('text', 'number', 'boolean', 'json', 'url', 'email') NOT NULL DEFAULT 'text',
    `is_public` TINYINT(1) NOT NULL DEFAULT 0,
    `updated_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `homepage_sections` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `section_key` VARCHAR(80) NOT NULL UNIQUE,
    `label` VARCHAR(120) NOT NULL,
    `heading` VARCHAR(255) NULL,
    `introduction` TEXT NULL,
    `display_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `is_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- `media_id` was added via a later idempotent ALTER TABLE in the same seed
-- file; folded directly into the CREATE TABLE here.
CREATE TABLE IF NOT EXISTS `homepage_quick_links` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `label` VARCHAR(120) NOT NULL,
    `description` VARCHAR(255) NULL,
    `link_url` VARCHAR(500) NOT NULL,
    `media_id` BIGINT UNSIGNED NULL,
    `display_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `hero_slides` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NULL DEFAULT NULL,
    `caption` VARCHAR(500) NULL DEFAULT NULL,
    `media_id` BIGINT UNSIGNED NOT NULL,
    -- confirmed: HomepageRepository::slides() LEFT JOINs a second, optional image
    `mobile_media_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    -- length <=100 -- assumed, consistent with other button_label columns
    `button_label` VARCHAR(100) NULL DEFAULT NULL,
    `button_url` VARCHAR(500) NULL DEFAULT NULL,
    -- confirmed values: homepage-hero.php seed inserts "left";
    -- deans-office-content-2026.php also inserts "left" -- other members inferred
    `text_alignment` ENUM('left','center','right') NOT NULL DEFAULT 'left',
    -- confirmed values in use: 55, 60 (0-100 opacity-style scale) -- inferred range
    `overlay_strength` TINYINT UNSIGNED NOT NULL DEFAULT 50,
    `display_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `starts_at` DATETIME NULL DEFAULT NULL,
    `ends_at` DATETIME NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_hero_slides_active_order` (`is_active`, `display_order`),
    -- inferred check: mirrors implicit scheduling rule (end after start)
    CONSTRAINT `chk_hero_slides_date_order` CHECK (`ends_at` IS NULL OR `starts_at` IS NULL OR `ends_at` >= `starts_at`),
    -- inferred check: overlay strength modelled as a 0-100 scale
    CONSTRAINT `chk_hero_slides_overlay_strength` CHECK (`overlay_strength` <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SECTION 12: Foreign key constraints
-- Added as a second pass (rather than inline) so that tables can be created
-- in a readable, domain-grouped order regardless of cross-section
-- dependencies (e.g. departments.hero_media_id -> media, where media is
-- defined earlier only by coincidence of grouping).
--
-- ON DELETE choice key: CASCADE = pure join/child-detail row that has no
-- meaning without its parent; SET NULL = optional/soft reference that should
-- survive parent removal; RESTRICT = required reference to a core,
-- rarely/never hard-deleted entity. Every choice below is a judgement call
-- (-- inferred ON DELETE) except where the repository code itself performs
-- cascading deletes/replacements (e.g. staff_departments, staff_positions,
-- which StaffRepository::replaceAssignment() deletes and re-inserts).
-- =============================================================================

-- Authentication & authorization
ALTER TABLE `user_roles`
    ADD CONSTRAINT `fk_user_roles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_user_roles_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_user_roles_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `role_permissions`
    ADD CONSTRAINT `fk_role_permissions_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_role_permissions_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

ALTER TABLE `user_departments`
    ADD CONSTRAINT `fk_user_departments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_user_departments_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE;

-- Organisational structure
ALTER TABLE `faculties`
    ADD CONSTRAINT `fk_faculties_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_faculties_logo_media` FOREIGN KEY (`logo_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL;

ALTER TABLE `departments`
    ADD CONSTRAINT `fk_departments_faculty` FOREIGN KEY (`faculty_id`) REFERENCES `faculties` (`id`) ON DELETE RESTRICT,
    ADD CONSTRAINT `fk_departments_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_departments_hero_media` FOREIGN KEY (`hero_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL;

ALTER TABLE `media`
    ADD CONSTRAINT `fk_media_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- Staff directory
ALTER TABLE `staff`
    ADD CONSTRAINT `fk_staff_faculty` FOREIGN KEY (`faculty_id`) REFERENCES `faculties` (`id`) ON DELETE RESTRICT,
    ADD CONSTRAINT `fk_staff_profile_media` FOREIGN KEY (`profile_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_staff_office_location` FOREIGN KEY (`office_location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL;

ALTER TABLE `staff_departments`
    ADD CONSTRAINT `fk_staff_departments_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_staff_departments_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE;

ALTER TABLE `staff_positions`
    ADD CONSTRAINT `fk_staff_positions_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_staff_positions_position` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON DELETE RESTRICT,
    ADD CONSTRAINT `fk_staff_positions_faculty` FOREIGN KEY (`faculty_id`) REFERENCES `faculties` (`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_staff_positions_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;

ALTER TABLE `staff_qualifications`
    ADD CONSTRAINT `fk_staff_qualifications_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

ALTER TABLE `staff_links`
    ADD CONSTRAINT `fk_staff_links_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

ALTER TABLE `staff_expertise`
    ADD CONSTRAINT `fk_staff_expertise_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_staff_expertise_expertise_area` FOREIGN KEY (`expertise_area_id`) REFERENCES `expertise_areas` (`id`) ON DELETE CASCADE;

-- Content workflow & audit
ALTER TABLE `content_revisions`
    ADD CONSTRAINT `fk_content_revisions_revised_by` FOREIGN KEY (`revised_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `content_approvals`
    ADD CONSTRAINT `fk_content_approvals_acted_by` FOREIGN KEY (`acted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `audit_logs`
    ADD CONSTRAINT `fk_audit_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- Documents
ALTER TABLE `documents`
    ADD CONSTRAINT `fk_documents_document_type` FOREIGN KEY (`document_type_id`) REFERENCES `document_types` (`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_documents_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

-- Programmes & curriculum
ALTER TABLE `programmes`
    ADD CONSTRAINT `fk_programmes_level` FOREIGN KEY (`programme_level_id`) REFERENCES `programme_levels` (`id`) ON DELETE RESTRICT,
    ADD CONSTRAINT `fk_programmes_hero_media` FOREIGN KEY (`hero_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL;

ALTER TABLE `programme_departments`
    ADD CONSTRAINT `fk_programme_departments_programme` FOREIGN KEY (`programme_id`) REFERENCES `programmes` (`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_programme_departments_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE;

ALTER TABLE `curriculum_versions`
    ADD CONSTRAINT `fk_curriculum_versions_programme` FOREIGN KEY (`programme_id`) REFERENCES `programmes` (`id`) ON DELETE CASCADE;

ALTER TABLE `curriculum_courses`
    ADD CONSTRAINT `fk_curriculum_courses_version` FOREIGN KEY (`curriculum_version_id`) REFERENCES `curriculum_versions` (`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_curriculum_courses_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE RESTRICT;

ALTER TABLE `course_prerequisites`
    ADD CONSTRAINT `fk_course_prerequisites_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_course_prerequisites_prerequisite` FOREIGN KEY (`prerequisite_course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

-- Research core
ALTER TABLE `research_units`
    ADD CONSTRAINT `fk_research_units_faculty` FOREIGN KEY (`faculty_id`) REFERENCES `faculties` (`id`) ON DELETE RESTRICT,
    ADD CONSTRAINT `fk_research_units_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_research_units_unit_type` FOREIGN KEY (`unit_type_id`) REFERENCES `research_unit_types` (`id`) ON DELETE RESTRICT,
    ADD CONSTRAINT `fk_research_units_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_research_units_hero_media` FOREIGN KEY (`hero_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL;

ALTER TABLE `research_unit_members`
    ADD CONSTRAINT `fk_research_unit_members_unit` FOREIGN KEY (`research_unit_id`) REFERENCES `research_units` (`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_research_unit_members_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE;

ALTER TABLE `facilities`
    ADD CONSTRAINT `fk_facilities_unit` FOREIGN KEY (`research_unit_id`) REFERENCES `research_units` (`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_facilities_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL;

ALTER TABLE `equipment`
    ADD CONSTRAINT `fk_equipment_unit` FOREIGN KEY (`research_unit_id`) REFERENCES `research_units` (`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_equipment_facility` FOREIGN KEY (`facility_id`) REFERENCES `facilities` (`id`) ON DELETE SET NULL;

ALTER TABLE `projects`
    ADD CONSTRAINT `fk_projects_lead_department` FOREIGN KEY (`lead_department_id`) REFERENCES `departments` (`id`) ON DELETE RESTRICT,
    ADD CONSTRAINT `fk_projects_hero_media` FOREIGN KEY (`hero_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL;

ALTER TABLE `project_members`
    ADD CONSTRAINT `fk_project_members_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_project_members_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL;

ALTER TABLE `project_research_units`
    ADD CONSTRAINT `fk_project_research_units_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_project_research_units_unit` FOREIGN KEY (`research_unit_id`) REFERENCES `research_units` (`id`) ON DELETE CASCADE;

ALTER TABLE `publications`
    ADD CONSTRAINT `fk_publications_type` FOREIGN KEY (`publication_type_id`) REFERENCES `publication_types` (`id`) ON DELETE RESTRICT;

ALTER TABLE `publication_authors`
    ADD CONSTRAINT `fk_publication_authors_publication` FOREIGN KEY (`publication_id`) REFERENCES `publications` (`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_publication_authors_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL;

ALTER TABLE `publication_projects`
    ADD CONSTRAINT `fk_publication_projects_publication` FOREIGN KEY (`publication_id`) REFERENCES `publications` (`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_publication_projects_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE;

ALTER TABLE `publication_research_units`
    ADD CONSTRAINT `fk_publication_research_units_publication` FOREIGN KEY (`publication_id`) REFERENCES `publications` (`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_publication_research_units_unit` FOREIGN KEY (`research_unit_id`) REFERENCES `research_units` (`id`) ON DELETE CASCADE;

-- Research metadata
ALTER TABLE `research_themes`
    ADD CONSTRAINT `fk_research_themes_parent` FOREIGN KEY (`parent_theme_id`) REFERENCES `research_themes` (`id`) ON DELETE SET NULL;

ALTER TABLE `research_theme_departments`
    ADD CONSTRAINT `fk_research_theme_departments_theme` FOREIGN KEY (`research_theme_id`) REFERENCES `research_themes` (`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_research_theme_departments_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE;

ALTER TABLE `project_themes`
    ADD CONSTRAINT `fk_project_themes_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_project_themes_theme` FOREIGN KEY (`research_theme_id`) REFERENCES `research_themes` (`id`) ON DELETE CASCADE;

ALTER TABLE `project_sdgs`
    ADD CONSTRAINT `fk_project_sdgs_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_project_sdgs_sdg` FOREIGN KEY (`sdg_id`) REFERENCES `sdgs` (`id`) ON DELETE CASCADE;

ALTER TABLE `publication_themes`
    ADD CONSTRAINT `fk_publication_themes_publication` FOREIGN KEY (`publication_id`) REFERENCES `publications` (`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_publication_themes_theme` FOREIGN KEY (`research_theme_id`) REFERENCES `research_themes` (`id`) ON DELETE CASCADE;

ALTER TABLE `partners`
    ADD CONSTRAINT `fk_partners_logo_media` FOREIGN KEY (`logo_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL;

ALTER TABLE `project_partners`
    ADD CONSTRAINT `fk_project_partners_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_project_partners_partner` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`id`) ON DELETE CASCADE;

-- Engagement & innovation
ALTER TABLE `partnerships`
    ADD CONSTRAINT `fk_partnerships_partner` FOREIGN KEY (`partner_id`) REFERENCES `partners` (`id`) ON DELETE RESTRICT,
    ADD CONSTRAINT `fk_partnerships_faculty` FOREIGN KEY (`faculty_id`) REFERENCES `faculties` (`id`) ON DELETE RESTRICT,
    ADD CONSTRAINT `fk_partnerships_document` FOREIGN KEY (`document_id`) REFERENCES `documents` (`id`) ON DELETE SET NULL;

ALTER TABLE `impact_stories`
    ADD CONSTRAINT `fk_impact_stories_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_impact_stories_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_impact_stories_featured_media` FOREIGN KEY (`featured_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL;

ALTER TABLE `innovations`
    ADD CONSTRAINT `fk_innovations_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_innovations_lead_department` FOREIGN KEY (`lead_department_id`) REFERENCES `departments` (`id`) ON DELETE RESTRICT,
    ADD CONSTRAINT `fk_innovations_hero_media` FOREIGN KEY (`hero_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL;

-- Site content
ALTER TABLE `news`
    ADD CONSTRAINT `fk_news_category` FOREIGN KEY (`category_id`) REFERENCES `news_categories` (`id`) ON DELETE RESTRICT,
    ADD CONSTRAINT `fk_news_featured_media` FOREIGN KEY (`featured_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_news_author` FOREIGN KEY (`author_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `news_departments`
    ADD CONSTRAINT `fk_news_departments_news` FOREIGN KEY (`news_id`) REFERENCES `news` (`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_news_departments_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE;

ALTER TABLE `events`
    ADD CONSTRAINT `fk_events_category` FOREIGN KEY (`category_id`) REFERENCES `event_categories` (`id`) ON DELETE RESTRICT,
    ADD CONSTRAINT `fk_events_location` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_events_contact_staff` FOREIGN KEY (`contact_staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_events_featured_media` FOREIGN KEY (`featured_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL;

ALTER TABLE `event_departments`
    ADD CONSTRAINT `fk_event_departments_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_event_departments_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE CASCADE;

ALTER TABLE `pages`
    ADD CONSTRAINT `fk_pages_parent` FOREIGN KEY (`parent_page_id`) REFERENCES `pages` (`id`) ON DELETE SET NULL,
    ADD CONSTRAINT `fk_pages_hero_media` FOREIGN KEY (`hero_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL;

ALTER TABLE `page_sections`
    ADD CONSTRAINT `fk_page_sections_page` FOREIGN KEY (`page_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE,
    ADD CONSTRAINT `fk_page_sections_media` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL;

-- Homepage & site settings
ALTER TABLE `site_settings`
    ADD CONSTRAINT `fk_site_settings_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

ALTER TABLE `homepage_quick_links`
    ADD CONSTRAINT `fk_homepage_quick_links_media` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL;

ALTER TABLE `hero_slides`
    ADD CONSTRAINT `fk_hero_slides_media` FOREIGN KEY (`media_id`) REFERENCES `media` (`id`) ON DELETE RESTRICT,
    ADD CONSTRAINT `fk_hero_slides_mobile_media` FOREIGN KEY (`mobile_media_id`) REFERENCES `media` (`id`) ON DELETE SET NULL;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- End of reconstructed schema.
--
-- NOT included here (see the accompanying summary for the full list and
-- reasoning): starter-data INSERTs for roles/permissions/faculties/
-- departments/positions/programme_levels/publication_types/
-- research_unit_types/sdgs/site_settings/homepage_sections/
-- homepage_quick_links. Some of those seed procedures already exist and are
-- idempotent (database/seeds/*.php), but the row-level VALUES for roles,
-- the 31 permissions, and positions were never observed verbatim in any
-- source file read during this reconstruction, so they are not fabricated
-- here. A human should re-derive or re-author those seed values before
-- running this schema against a fresh database.
-- =============================================================================
