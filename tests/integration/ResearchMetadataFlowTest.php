<?php

declare(strict_types=1);

use FastWebsite\Core\Database;
use FastWebsite\Repositories\ProjectRepository;
use FastWebsite\Repositories\PublicationRepository;
use FastWebsite\Repositories\ResearchMetadataRepository;
use FastWebsite\Repositories\UserRepository;
use FastWebsite\Services\AuditService;
use FastWebsite\Services\AuthorizationService;
use FastWebsite\Services\DepartmentScopeService;
use FastWebsite\Services\ProjectService;
use FastWebsite\Services\PublicationService;
use FastWebsite\Services\ResearchMetadataService;
use FastWebsite\Validation\ResearchMetadataValidator;

return static function (): void {
    /** @var Database $database */
    $database = require dirname(__DIR__, 2) . '/bootstrap/database.php';
    $connection = $database->connection();
    $connection->beginTransaction();

    try {
        $suffix = bin2hex(random_bytes(5));
        $userId = (int) $connection->query('SELECT id FROM users WHERE is_active=1 AND deleted_at IS NULL ORDER BY id LIMIT 1')->fetchColumn();
        $departmentId = (int) $connection->query('SELECT id FROM departments WHERE deleted_at IS NULL ORDER BY id LIMIT 1')->fetchColumn();
        $staffId = (int) $connection->query('SELECT id FROM staff WHERE status="published" AND deleted_at IS NULL ORDER BY id LIMIT 1')->fetchColumn();
        $publicationTypeId = (int) $connection->query('SELECT id FROM publication_types ORDER BY id LIMIT 1')->fetchColumn();
        if (min($userId, $departmentId, $staffId, $publicationTypeId) < 1) {
            throw new RuntimeException('Research metadata prerequisites are missing.');
        }

        $users = new UserRepository($database);
        $authorization = new AuthorizationService($users);
        $scope = new DepartmentScopeService($users, $authorization);
        $audit = new AuditService($database);
        $projects = new ProjectRepository($database, $scope);
        $publications = new PublicationRepository($database, $scope);
        $metadata = new ResearchMetadataRepository($database, $scope);
        $service = new ResearchMetadataService($metadata, $projects, $publications, $authorization, $audit);
        $validator = new ResearchMetadataValidator($metadata);
        $ip = '127.0.0.17';
        $agent = 'FAST metadata integration test';

        $themeInput = ['name'=>'Transactional Theme '.$suffix, 'slug'=>'transactional-theme-'.$suffix, 'description'=>'A rollback-only theme.', 'icon'=>'', 'display_order'=>'1', 'department_id'=>(string) $departmentId, 'parent_theme_id'=>''];
        $themeValidation = $validator->theme($themeInput, $userId);
        if ($themeValidation['errors'] !== []) throw new RuntimeException('Valid research theme rejected: ' . json_encode($themeValidation['errors']));
        $themeId = $service->saveTheme(null, $themeValidation['data'], $themeValidation['department_id'], $userId, true, $ip, $agent);

        $partnerInput = ['name'=>'Transactional Partner '.$suffix, 'slug'=>'transactional-partner-'.$suffix, 'partner_type'=>'university', 'country'=>'Uganda', 'website_url'=>'https://example.invalid/partner', 'description'=>'A rollback-only partner.'];
        $partnerValidation = $validator->partner($partnerInput);
        if ($partnerValidation['errors'] !== []) throw new RuntimeException('Valid partner rejected: ' . json_encode($partnerValidation['errors']));
        $partnerId = $service->savePartner(null, $partnerValidation['data'], $userId, true, $ip, $agent);

        $projectService = new ProjectService($projects, $authorization, $audit);
        $projectId = $projectService->createAndPublish([
            'lead_department_id'=>$departmentId, 'title'=>'Metadata Project '.$suffix,
            'short_title'=>'META', 'slug'=>'metadata-project-'.$suffix,
            'summary'=>'Published metadata verification project.', 'objectives'=>null,
            'methodology'=>null, 'expected_outputs'=>null, 'outcomes'=>null, 'impact'=>null,
            'project_status'=>'ongoing', 'start_date'=>'2026-01-01', 'end_date'=>null,
            'budget_amount'=>null, 'currency_code'=>'UGX', 'funding_reference'=>null,
            'project_url'=>null, 'hero_media_id'=>null,
        ], null, $userId, $ip, $agent);

        $publicationService = new PublicationService($publications, $authorization, $audit);
        $publicationId = $publicationService->create([
            'publication_type_id'=>$publicationTypeId, 'title'=>'Metadata Publication '.$suffix,
            'slug'=>'metadata-publication-'.$suffix, 'abstract'=>'Published metadata verification publication.',
            'journal_name'=>null, 'publisher'=>null, 'publication_year'=>2026,
            'publication_date'=>null, 'volume'=>null, 'issue'=>null, 'page_range'=>null,
            'doi'=>null, 'isbn'=>null, 'external_url'=>null, 'access_type'=>'open_access',
            'citation_text'=>null,
        ], $projectId, null, $userId, $ip, $agent);
        $publicationService->addAuthor($publicationId, $staffId, '', '', '', 1, true, $userId, $ip, $agent);
        $publicationService->publishDraft($publicationId, $userId, 'Metadata verification.', $ip, $agent);

        $service->addProjectTheme($projectId, $themeId, true, $userId, $ip, $agent);
        $service->addProjectSdg($projectId, 9, true, $userId, $ip, $agent);
        $service->addProjectPartner($projectId, $partnerId, 'academic_partner', 'Joint research delivery.', $userId, $ip, $agent);
        $service->addPublicationTheme($publicationId, $themeId, $userId, $ip, $agent);

        if (count($metadata->projectThemes($projectId, true)) !== 1) throw new RuntimeException('Published project theme was not returned.');
        if (count($metadata->projectSdgs($projectId)) !== 1) throw new RuntimeException('Project SDG was not returned.');
        if (count($metadata->projectPartners($projectId, true)) !== 1) throw new RuntimeException('Published project partner was not returned.');
        if (count($metadata->publicationThemes($publicationId, true)) !== 1) throw new RuntimeException('Published publication theme was not returned.');

        $service->themeStatus($themeId, 'archive', $userId, $ip, $agent);
        $service->partnerStatus($partnerId, 'archive', $userId, $ip, $agent);
        if ($metadata->projectThemes($projectId, true) !== [] || $metadata->publicationThemes($publicationId, true) !== []) throw new RuntimeException('Archived theme remained public.');
        if ($metadata->projectPartners($projectId, true) !== []) throw new RuntimeException('Archived partner remained public.');

        $service->themeStatus($themeId, 'restore', $userId, $ip, $agent);
        $service->partnerStatus($partnerId, 'restore', $userId, $ip, $agent);
        if ($metadata->findTheme($themeId, $userId)['status'] !== 'draft' || $metadata->findPartner($partnerId)['status'] !== 'draft') throw new RuntimeException('Archived metadata did not restore as draft.');
    } finally {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
    }
};
