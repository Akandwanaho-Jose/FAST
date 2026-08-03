<?php

declare(strict_types=1);

use FastWebsite\Core\Application;
use FastWebsite\Core\Csrf;
use FastWebsite\Core\Database;
use FastWebsite\Core\Env;
use FastWebsite\Core\ExceptionHandler;
use FastWebsite\Core\Logger;
use FastWebsite\Core\Router;
use FastWebsite\Core\Session;
use FastWebsite\Core\View;
use FastWebsite\Controllers\AdminController;
use FastWebsite\Controllers\AuthController;
use FastWebsite\Controllers\DepartmentAdminController;
use FastWebsite\Controllers\DepartmentPublicController;
use FastWebsite\Controllers\StaffAdminController;
use FastWebsite\Controllers\StaffPublicController;
use FastWebsite\Controllers\ProgrammeAdminController;
use FastWebsite\Controllers\ProgrammePublicController;
use FastWebsite\Controllers\CurriculumAdminController;
use FastWebsite\Controllers\ResearchAdminController;
use FastWebsite\Controllers\ResearchPublicController;
use FastWebsite\Controllers\ProjectAdminController;
use FastWebsite\Controllers\ProjectPublicController;
use FastWebsite\Controllers\PublicationAdminController;
use FastWebsite\Controllers\PublicationPublicController;
use FastWebsite\Controllers\ResearchMetadataAdminController;
use FastWebsite\Controllers\InnovationAdminController;
use FastWebsite\Controllers\InnovationPublicController;
use FastWebsite\Controllers\EngagementAdminController;
use FastWebsite\Controllers\EngagementPublicController;
use FastWebsite\Controllers\SiteContentAdminController;
use FastWebsite\Controllers\SiteContentPublicController;
use FastWebsite\Controllers\AssetAdminController;
use FastWebsite\Controllers\DocumentPublicController;
use FastWebsite\Controllers\HomeController;
use FastWebsite\Controllers\HomepageAdminController;
use FastWebsite\Controllers\SiteSettingsAdminController;
use FastWebsite\Middleware\RequireAuth;
use FastWebsite\Middleware\RequirePasswordChange;
use FastWebsite\Middleware\RequirePermission;
use FastWebsite\Repositories\DashboardRepository;
use FastWebsite\Repositories\DepartmentRepository;
use FastWebsite\Repositories\MediaRepository;
use FastWebsite\Repositories\StaffRepository;
use FastWebsite\Repositories\ProgrammeRepository;
use FastWebsite\Repositories\CurriculumRepository;
use FastWebsite\Repositories\ResearchRepository;
use FastWebsite\Repositories\ProjectRepository;
use FastWebsite\Repositories\PublicationRepository;
use FastWebsite\Repositories\ResearchMetadataRepository;
use FastWebsite\Repositories\InnovationRepository;
use FastWebsite\Repositories\EngagementRepository;
use FastWebsite\Repositories\SiteContentRepository;
use FastWebsite\Repositories\AssetRepository;
use FastWebsite\Repositories\HomepageRepository;
use FastWebsite\Repositories\SiteSettingsRepository;
use FastWebsite\Repositories\UserRepository;
use FastWebsite\Services\AdminNavigation;
use FastWebsite\Services\AdminPageContext;
use FastWebsite\Services\AuditService;
use FastWebsite\Services\AuthService;
use FastWebsite\Services\AuthorizationService;
use FastWebsite\Services\ContentWorkflowService;
use FastWebsite\Services\DepartmentScopeService;
use FastWebsite\Services\DepartmentService;
use FastWebsite\Services\LoginThrottle;
use FastWebsite\Services\MediaUploadService;
use FastWebsite\Services\StaffService;
use FastWebsite\Services\ProgrammeService;
use FastWebsite\Services\CurriculumService;
use FastWebsite\Services\ResearchService;
use FastWebsite\Services\ProjectService;
use FastWebsite\Services\PublicationService;
use FastWebsite\Services\ResearchMetadataService;
use FastWebsite\Services\InnovationService;
use FastWebsite\Services\EngagementService;
use FastWebsite\Services\SiteContentService;
use FastWebsite\Services\AssetService;
use FastWebsite\Services\DocumentUploadService;
use FastWebsite\Validation\DepartmentValidator;
use FastWebsite\Validation\PasswordPolicy;
use FastWebsite\Validation\StaffValidator;
use FastWebsite\Validation\ProgrammeValidator;
use FastWebsite\Validation\CurriculumValidator;
use FastWebsite\Validation\ResearchUnitValidator;
use FastWebsite\Validation\ProjectValidator;
use FastWebsite\Validation\PublicationValidator;
use FastWebsite\Validation\ResearchMetadataValidator;
use FastWebsite\Validation\InnovationValidator;
use FastWebsite\Validation\EngagementValidator;
use FastWebsite\Validation\SiteContentValidator;

require __DIR__ . '/autoload.php';

Env::load(dirname(__DIR__) . '/.env');

/** @var array<string, mixed> $appConfiguration */
$appConfiguration = require dirname(__DIR__) . '/config/app.php';
/** @var array<string, mixed> $databaseConfiguration */
$databaseConfiguration = require dirname(__DIR__) . '/config/database.php';

date_default_timezone_set((string) $appConfiguration['timezone']);
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', (string) $appConfiguration['php_error_log_path']);

$view = new View((string) $appConfiguration['views_path']);
$router = new Router();
$database = new Database($databaseConfiguration);
$siteSettingsRepository = new SiteSettingsRepository($database);
$view->share([
    'siteContent' => $siteSettingsRepository->publicMap(),
    'siteNavigation' => $siteSettingsRepository->navigation(),
]);
$logger = new Logger((string) $appConfiguration['log_path']);
$exceptions = new ExceptionHandler($view, $logger);
$sessionConfiguration = $appConfiguration['session'];
$authenticationConfiguration = $appConfiguration['authentication'];
$session = new Session(
    (string) $sessionConfiguration['name'],
    (int) $sessionConfiguration['lifetime_minutes']
);
$session->start();
$csrf = new Csrf($session);
$users = new UserRepository($database);
$audit = new AuditService($database);
$throttle = new LoginThrottle(
    (string) $authenticationConfiguration['throttle_path'],
    (int) $authenticationConfiguration['max_attempts'],
    (int) $authenticationConfiguration['window_minutes'] * 60,
    (int) $authenticationConfiguration['lock_minutes'] * 60
);
$auth = new AuthService(
    $users,
    $audit,
    $throttle,
    $session,
    (int) $authenticationConfiguration['max_attempts'],
    (int) $authenticationConfiguration['lock_minutes'] * 60
);
$authorization = new AuthorizationService($users);
$departmentScope = new DepartmentScopeService($users, $authorization);
$adminNavigation = new AdminNavigation();
$adminPageContext = new AdminPageContext(
    $csrf,
    $session,
    $authorization,
    $departmentScope,
    $adminNavigation
);
$dashboard = new DashboardRepository(
    $database,
    $authorization,
    $departmentScope
);
$passwordPolicy = new PasswordPolicy();
$authController = new AuthController(
    $view,
    $auth,
    $csrf,
    $session,
    $passwordPolicy
);
$adminController = new AdminController(
    $view,
    $auth,
    $authorization,
    $adminPageContext,
    $dashboard
);
$departmentRepository = new DepartmentRepository($database, $departmentScope);
$mediaRepository = new MediaRepository($database);
$uploadConfiguration = $appConfiguration['uploads'];
$mediaUploads = new MediaUploadService(
    $mediaRepository,
    (string) $uploadConfiguration['public_path'],
    (int) $uploadConfiguration['maximum_image_bytes']
);
$departmentValidator = new DepartmentValidator($departmentRepository);
$contentWorkflow = new ContentWorkflowService(
    $authorization,
    $departmentScope
);
$departmentService = new DepartmentService(
    $departmentRepository,
    $authorization,
    $departmentScope,
    $contentWorkflow,
    $audit
);
$departmentAdminController = new DepartmentAdminController(
    $view,
    $auth,
    $authorization,
    $departmentScope,
    $adminPageContext,
    $departmentRepository,
    $departmentValidator,
    $departmentService,
    $mediaUploads,
    $contentWorkflow,
    $csrf,
    $session
);
$departmentPublicController = new DepartmentPublicController(
    $view,
    $departmentRepository
);
$staffRepository = new StaffRepository($database, $departmentScope);
$staffValidator = new StaffValidator($staffRepository);
$staffService = new StaffService(
    $staffRepository,
    $authorization,
    $departmentScope,
    $audit
);
$staffAdminController = new StaffAdminController(
    $view,
    $auth,
    $authorization,
    $departmentScope,
    $adminPageContext,
    $staffRepository,
    $staffValidator,
    $staffService,
    $mediaUploads,
    $csrf,
    $session
);
$publicationRepository = new PublicationRepository($database, $departmentScope);
$staffPublicController = new StaffPublicController($view, $staffRepository, $publicationRepository);
$programmeRepository = new ProgrammeRepository($database, $departmentScope);
$curriculumRepository = new CurriculumRepository($database);
$programmeValidator = new ProgrammeValidator($programmeRepository);
$programmeService = new ProgrammeService(
    $programmeRepository,
    $authorization,
    $audit
);
$programmeAdminController = new ProgrammeAdminController(
    $view,
    $auth,
    $authorization,
    $adminPageContext,
    $programmeRepository,
    $programmeValidator,
    $programmeService,
    $mediaUploads,
    $csrf,
    $session
);
$curriculumService = new CurriculumService(
    $curriculumRepository,
    $programmeRepository,
    $authorization,
    $audit
);
$curriculumAdminController = new CurriculumAdminController(
    $view,
    $auth,
    $adminPageContext,
    $programmeRepository,
    $curriculumRepository,
    new CurriculumValidator(),
    $curriculumService,
    $csrf,
    $session
);
$programmePublicController = new ProgrammePublicController($view, $programmeRepository, $curriculumRepository);
$researchRepository = new ResearchRepository($database, $departmentScope);
$researchService = new ResearchService($researchRepository, $authorization, $audit);
$researchAdminController = new ResearchAdminController(
    $view,
    $auth,
    $authorization,
    $adminPageContext,
    $researchRepository,
    new ResearchUnitValidator($researchRepository),
    $researchService,
    $mediaUploads,
    $csrf,
    $session
);
$researchPublicController = new ResearchPublicController($view, $researchRepository);
$projectRepository = new ProjectRepository($database, $departmentScope);
$researchMetadataRepository = new ResearchMetadataRepository($database, $departmentScope);
$projectService = new ProjectService($projectRepository, $authorization, $audit);
$projectAdminController = new ProjectAdminController(
    $view,
    $auth,
    $authorization,
    $adminPageContext,
    $projectRepository,
    $researchMetadataRepository,
    new ProjectValidator($projectRepository),
    $projectService,
    $mediaUploads,
    $csrf,
    $session
);
$projectPublicController = new ProjectPublicController($view, $projectRepository, $researchMetadataRepository);
$publicationService = new PublicationService($publicationRepository, $authorization, $audit);
$publicationAdminController = new PublicationAdminController(
    $view,
    $auth,
    $authorization,
    $adminPageContext,
    $publicationRepository,
    $researchMetadataRepository,
    new PublicationValidator($publicationRepository),
    $publicationService,
    $csrf,
    $session
);
$publicationPublicController = new PublicationPublicController($view, $publicationRepository, $researchMetadataRepository);
$researchMetadataService = new ResearchMetadataService(
    $researchMetadataRepository,
    $projectRepository,
    $publicationRepository,
    $authorization,
    $audit
);
$researchMetadataAdminController = new ResearchMetadataAdminController(
    $view,
    $auth,
    $authorization,
    $adminPageContext,
    $researchMetadataRepository,
    new ResearchMetadataValidator($researchMetadataRepository),
    $researchMetadataService,
    $mediaUploads,
    $csrf,
    $session
);
$innovationRepository = new InnovationRepository($database, $departmentScope);
$innovationService = new InnovationService($innovationRepository, $authorization, $audit);
$innovationAdminController = new InnovationAdminController(
    $view,
    $auth,
    $authorization,
    $adminPageContext,
    $innovationRepository,
    new InnovationValidator($innovationRepository),
    $innovationService,
    $mediaUploads,
    $csrf,
    $session
);
$innovationPublicController = new InnovationPublicController($view, $innovationRepository);
$engagementRepository = new EngagementRepository($database, $departmentScope);
$engagementService = new EngagementService($engagementRepository, $authorization, $audit);
$engagementAdminController = new EngagementAdminController(
    $view, $auth, $authorization, $adminPageContext, $engagementRepository,
    new EngagementValidator($engagementRepository), $engagementService,
    $mediaUploads, $csrf, $session
);
$engagementPublicController = new EngagementPublicController($view, $engagementRepository);
$siteContentRepository = new SiteContentRepository($database, $departmentScope);
$siteContentService = new SiteContentService($siteContentRepository, $authorization, $audit);
$siteContentAdminController = new SiteContentAdminController(
    $view, $auth, $authorization, $adminPageContext, $siteContentRepository,
    new SiteContentValidator($siteContentRepository), $siteContentService,
    $mediaUploads, $csrf, $session
);
$siteContentPublicController = new SiteContentPublicController($view, $siteContentRepository);
$assetRepository = new AssetRepository($database);
$homepageRepository = new HomepageRepository($database);
$homepageAdminController = new HomepageAdminController(
    $view, $auth, $adminPageContext, $homepageRepository,
    $mediaUploads, $csrf, $session
);
$siteSettingsAdminController = new SiteSettingsAdminController(
    $view, $auth, $adminPageContext, $siteSettingsRepository,
    $audit, $csrf, $session
);
$homeController = new HomeController(
    $view, $homepageRepository, $siteContentRepository,
    $innovationRepository, $engagementRepository
);
$assetService = new AssetService($assetRepository, $authorization, $audit);
$documentUploads = new DocumentUploadService(
    (string) $uploadConfiguration['public_path'],
    (int) $uploadConfiguration['maximum_document_bytes']
);
$assetAdminController = new AssetAdminController(
    $view, $auth, $adminPageContext, $assetRepository, $assetService,
    $mediaUploads, $documentUploads, $csrf, $session
);
$documentPublicController = new DocumentPublicController($view, $assetRepository);
$requireAuth = new RequireAuth($auth);
$requirePasswordChange = new RequirePasswordChange($auth);
$requirePermission = new RequirePermission($auth, $authorization, $view);

/** @var callable $registerRoutes */
$registerRoutes = require dirname(__DIR__) . '/routes/web.php';
$registerRoutes(
    $router,
    $view,
    $database,
    $authController,
    $adminController,
    $departmentAdminController,
    $departmentPublicController,
    $staffAdminController,
    $staffPublicController,
    $programmeAdminController,
    $programmePublicController,
    $curriculumAdminController,
    $researchAdminController,
    $researchPublicController,
    $projectAdminController,
    $projectPublicController,
    $publicationAdminController,
    $publicationPublicController,
    $researchMetadataAdminController,
    $innovationAdminController,
    $innovationPublicController,
    $engagementAdminController,
    $engagementPublicController,
    $siteContentAdminController,
    $siteContentPublicController,
    $assetAdminController,
    $documentPublicController,
    $homepageAdminController,
    $siteSettingsAdminController,
    $homeController,
    $requireAuth,
    $requirePasswordChange,
    $requirePermission,
    $adminNavigation,
    $appConfiguration['environment'] === 'development'
);

return new Application($router, $exceptions);
