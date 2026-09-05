<?php

declare(strict_types=1);

use FastWebsite\Controllers\HealthController;
use FastWebsite\Controllers\HomeController;
use FastWebsite\Controllers\HomepageAdminController;
use FastWebsite\Controllers\SiteSettingsAdminController;
use FastWebsite\Controllers\AdminController;
use FastWebsite\Controllers\AuthController;
use FastWebsite\Controllers\PasswordResetController;
use FastWebsite\Controllers\DepartmentAdminController;
use FastWebsite\Controllers\DepartmentPublicController;
use FastWebsite\Controllers\StaffAdminController;
use FastWebsite\Controllers\StaffPublicController;
use FastWebsite\Controllers\StaffSelfServiceController;
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
use FastWebsite\Controllers\UserAdminController;
use FastWebsite\Core\Database;
use FastWebsite\Core\Request;
use FastWebsite\Core\Response;
use FastWebsite\Core\Router;
use FastWebsite\Core\View;
use FastWebsite\Middleware\RequireAuth;
use FastWebsite\Middleware\RequirePasswordChange;
use FastWebsite\Middleware\RequirePermission;
use FastWebsite\Services\AdminNavigation;

return static function (
    Router $router,
    View $view,
    Database $database,
    AuthController $authController,
    PasswordResetController $passwordResetController,
    AdminController $adminController,
    DepartmentAdminController $departmentAdminController,
    DepartmentPublicController $departmentPublicController,
    StaffAdminController $staffAdminController,
    StaffSelfServiceController $staffSelfServiceController,
    StaffPublicController $staffPublicController,
    ProgrammeAdminController $programmeAdminController,
    ProgrammePublicController $programmePublicController,
    CurriculumAdminController $curriculumAdminController,
    ResearchAdminController $researchAdminController,
    ResearchPublicController $researchPublicController,
    ProjectAdminController $projectAdminController,
    ProjectPublicController $projectPublicController,
    PublicationAdminController $publicationAdminController,
    PublicationPublicController $publicationPublicController,
    ResearchMetadataAdminController $researchMetadataAdminController,
    InnovationAdminController $innovationAdminController,
    InnovationPublicController $innovationPublicController,
    EngagementAdminController $engagementAdminController,
    EngagementPublicController $engagementPublicController,
    SiteContentAdminController $siteContentAdminController,
    SiteContentPublicController $siteContentPublicController,
    AssetAdminController $assetAdminController,
    DocumentPublicController $documentPublicController,
    UserAdminController $userAdminController,
    HomepageAdminController $homepageAdminController,
    SiteSettingsAdminController $siteSettingsAdminController,
    HomeController $homeController,
    RequireAuth $requireAuth,
    RequirePasswordChange $requirePasswordChange,
    RequirePermission $requirePermission,
    AdminNavigation $adminNavigation,
    bool $development
): void {
    $protect = static function (
        string $permission,
        callable $handler
    ) use (
        $requireAuth,
        $requirePasswordChange,
        $requirePermission
    ): callable {
        return static fn (Request $request): Response => $requireAuth->handle(
            $request,
            static fn (Request $request): Response => $requirePasswordChange->handle(
                $request,
                static fn (Request $request): Response => $requirePermission->handle(
                    $request,
                    $permission,
                    $handler
                )
            )
        );
    };

    $router->get('/', [$homeController, 'index']);
    $router->get('/about', [$siteContentPublicController, 'aboutIndex']);
    $router->get('/about/{slug}', [$siteContentPublicController, 'aboutPage']);
    $router->get('/health', [new HealthController($view, $database), 'show']);
    $router->get('/departments', [$departmentPublicController, 'index']);
    $router->get('/departments/{slug}', [$departmentPublicController, 'show']);
    $router->get('/staff', [$staffPublicController, 'index']);
    $router->get('/staff/{slug}', [$staffPublicController, 'show']);
    $router->get('/programmes', [$programmePublicController, 'index']);
    $router->get('/programmes/{slug}', [$programmePublicController, 'show']);
    $router->get('/research', [$researchPublicController, 'index']);
    $router->get('/research/projects', [$projectPublicController, 'index']);
    $router->get('/research/projects/{slug}', [$projectPublicController, 'show']);
    $router->get('/research/publications', [$publicationPublicController, 'index']);
    $router->get('/research/publications/{slug}', [$publicationPublicController, 'show']);
    $router->get('/research/{slug}', [$researchPublicController, 'show']);
    $router->get('/innovations', [$innovationPublicController, 'innovations']);
    $router->get('/innovations/{slug}', [$innovationPublicController, 'innovation']);
    $router->get('/facilities', [$innovationPublicController, 'facilities']);
    $router->get('/engagement', [$engagementPublicController, 'index']);
    $router->get('/impact/{slug}', [$engagementPublicController, 'impact']);
    $router->get('/news', [$siteContentPublicController, 'news']);
    $router->get('/news/{slug}', [$siteContentPublicController, 'newsItem']);
    $router->get('/events', [$siteContentPublicController, 'events']);
    $router->get('/events/{slug}', [$siteContentPublicController, 'event']);
    $router->get('/documents', [$documentPublicController, 'index']);
    $router->get('/login', [$authController, 'loginForm']);
    $router->post('/login', [$authController, 'login']);
    $router->get('/password/forgot', [$passwordResetController, 'forgotForm']);
    $router->post('/password/forgot', [$passwordResetController, 'forgotSubmit']);
    $router->get('/password/reset/{token}', [$passwordResetController, 'resetForm']);
    $router->post('/password/reset/{token}', [$passwordResetController, 'resetSubmit']);
    $router->get(
        '/password/change',
        static fn (Request $request): Response => $requireAuth->handle(
            $request,
            [$authController, 'passwordForm']
        )
    );
    $router->post(
        '/password/change',
        static fn (Request $request): Response => $requireAuth->handle(
            $request,
            [$authController, 'changePassword']
        )
    );

    // Staff self-service: auth-only (ownership-gated inside the controller,
    // not RBAC), deliberately not wrapped in $protect()/RequirePermission -
    // this isn't a permission-gated area, see StaffSelfServiceController.
    $selfServiceProtect = static function (callable $handler) use (
        $requireAuth,
        $requirePasswordChange
    ): callable {
        return static fn (Request $request): Response => $requireAuth->handle(
            $request,
            static fn (Request $request): Response => $requirePasswordChange->handle(
                $request,
                $handler
            )
        );
    };
    $router->get('/my-profile', $selfServiceProtect([$staffSelfServiceController, 'profile']));
    $router->post('/my-profile', $selfServiceProtect([$staffSelfServiceController, 'updateProfile']));
    $router->get('/my-publications', $selfServiceProtect([$staffSelfServiceController, 'publications']));
    $router->get('/my-publications/create', $selfServiceProtect([$staffSelfServiceController, 'createPublicationForm']));
    $router->post('/my-publications', $selfServiceProtect([$staffSelfServiceController, 'storePublication']));
    $router->get('/my-publications/{id}/edit', $selfServiceProtect([$staffSelfServiceController, 'editPublicationForm']));
    $router->post('/my-publications/{id}', $selfServiceProtect([$staffSelfServiceController, 'updatePublication']));
    $router->post('/my-publications/{id}/authors', $selfServiceProtect([$staffSelfServiceController, 'addAuthor']));
    $router->post('/my-publications/{id}/authors/{authorId}/remove', $selfServiceProtect([$staffSelfServiceController, 'removeAuthor']));

    $router->get(
        '/admin',
        $protect('dashboard.view', [$adminController, 'index'])
    );

    foreach ($adminNavigation->definitions() as $module) {
        if (in_array($module['key'], ['dashboard', 'homepage', 'site-content', 'departments', 'staff', 'programmes', 'research', 'innovations', 'facilities', 'engagement', 'news', 'events', 'pages', 'media', 'documents', 'users'], true)) {
            continue;
        }

        $router->get(
            '/' . $module['route'],
            $protect(
                $module['permission'],
                static fn (Request $request): Response => $adminController->module(
                    $request,
                    $module
                )
            )
        );
    }
    $router->get(
        '/admin/faculty',
        $protect('departments.view', [$departmentAdminController, 'faculty'])
    );
    $router->get('/admin/homepage', $protect('pages.manage', [$homepageAdminController, 'index']));
    $router->get('/admin/homepage/create', $protect('pages.manage', [$homepageAdminController, 'create']));
    $router->post('/admin/homepage', $protect('pages.manage', [$homepageAdminController, 'store']));
    $router->get('/admin/homepage/{id}/edit', $protect('pages.manage', [$homepageAdminController, 'edit']));
    $router->post('/admin/homepage/{id}', $protect('pages.manage', [$homepageAdminController, 'update']));
    $router->post('/admin/homepage/sections/{key}', $protect('pages.manage', [$homepageAdminController, 'updateSection']));
    $router->post('/admin/homepage/quick-links/{id}', $protect('pages.manage', [$homepageAdminController, 'updateQuickLink']));
    $router->get('/admin/site-content', $protect('pages.manage', [$siteSettingsAdminController, 'index']));
    $router->post('/admin/site-content', $protect('pages.manage', [$siteSettingsAdminController, 'update']));
    $router->get(
        '/admin/departments',
        $protect('departments.view', [$departmentAdminController, 'index'])
    );
    $router->get(
        '/admin/departments/create',
        $protect('departments.create', [$departmentAdminController, 'create'])
    );
    $router->post(
        '/admin/departments',
        $protect('departments.create', [$departmentAdminController, 'store'])
    );
    $router->get(
        '/admin/departments/{id}',
        $protect('departments.view', [$departmentAdminController, 'show'])
    );
    $router->get(
        '/admin/departments/{id}/edit',
        $protect('departments.edit', [$departmentAdminController, 'edit'])
    );
    $router->post(
        '/admin/departments/{id}',
        $protect('departments.edit', [$departmentAdminController, 'update'])
    );
    $router->post(
        '/admin/departments/{id}/workflow',
        $protect('departments.view', [$departmentAdminController, 'workflow'])
    );
    $router->get(
        '/admin/staff',
        $protect('staff.view', [$staffAdminController, 'index'])
    );
    $router->get(
        '/admin/staff/create',
        $protect('staff.create', [$staffAdminController, 'create'])
    );
    $router->post(
        '/admin/staff',
        $protect('staff.create', [$staffAdminController, 'store'])
    );
    $router->get(
        '/admin/staff/{id}',
        $protect('staff.view', [$staffAdminController, 'show'])
    );
    $router->get(
        '/admin/staff/{id}/edit',
        $protect('staff.edit', [$staffAdminController, 'edit'])
    );
    $router->post(
        '/admin/staff/{id}',
        $protect('staff.edit', [$staffAdminController, 'update'])
    );
    $router->post(
        '/admin/staff/{id}/workflow',
        $protect('staff.view', [$staffAdminController, 'workflow'])
    );
    $router->get(
        '/admin/programmes',
        $protect('programmes.view', [$programmeAdminController, 'index'])
    );
    $router->get(
        '/admin/programmes/create',
        $protect('programmes.create', [$programmeAdminController, 'create'])
    );
    $router->post(
        '/admin/programmes',
        $protect('programmes.create', [$programmeAdminController, 'store'])
    );
    $router->get(
        '/admin/programmes/{id}',
        $protect('programmes.view', [$programmeAdminController, 'show'])
    );
    $router->get(
        '/admin/programmes/{id}/edit',
        $protect('programmes.edit', [$programmeAdminController, 'edit'])
    );
    $router->post(
        '/admin/programmes/{id}',
        $protect('programmes.edit', [$programmeAdminController, 'update'])
    );
    $router->post(
        '/admin/programmes/{id}/workflow',
        $protect('programmes.view', [$programmeAdminController, 'workflow'])
    );
    $router->get(
        '/admin/programmes/{id}/curriculum',
        $protect('programmes.view', [$curriculumAdminController, 'show'])
    );
    $router->post(
        '/admin/programmes/{id}/curriculum/courses',
        $protect('programmes.edit', [$curriculumAdminController, 'save'])
    );
    $router->post(
        '/admin/programmes/{id}/curriculum/{versionId}/placements/{placementId}/delete',
        $protect('programmes.edit', [$curriculumAdminController, 'delete'])
    );
    $router->post(
        '/admin/programmes/{id}/curriculum/{versionId}/publish',
        $protect('programmes.publish', [$curriculumAdminController, 'publish'])
    );
    $router->get('/admin/research', $protect('research.view', [$researchAdminController, 'index']));
    $router->get('/admin/research/create', $protect('research.create', [$researchAdminController, 'create']));
    $router->post('/admin/research', $protect('research.create', [$researchAdminController, 'store']));
    $router->get('/admin/research/projects', $protect('research.view', [$projectAdminController, 'index']));
    $router->get('/admin/research/projects/create', $protect('research.create', [$projectAdminController, 'create']));
    $router->post('/admin/research/projects', $protect('research.create', [$projectAdminController, 'store']));
    $router->get('/admin/research/projects/{id}', $protect('research.view', [$projectAdminController, 'show']));
    $router->get('/admin/research/projects/{id}/edit', $protect('research.edit', [$projectAdminController, 'edit']));
    $router->post('/admin/research/projects/{id}', $protect('research.edit', [$projectAdminController, 'update']));
    $router->post('/admin/research/projects/{id}/workflow', $protect('research.view', [$projectAdminController, 'workflow']));
    $router->post('/admin/research/projects/{id}/members', $protect('research.edit', [$projectAdminController, 'addMember']));
    $router->post('/admin/research/projects/{id}/members/{memberId}/remove', $protect('research.edit', [$projectAdminController, 'removeMember']));
    $router->post('/admin/research/projects/{id}/milestones', $protect('research.edit', [$projectAdminController, 'addMilestone']));
    $router->get('/admin/research/projects/{id}/milestones/{milestoneId}/edit', $protect('research.edit', [$projectAdminController, 'editMilestone']));
    $router->post('/admin/research/projects/{id}/milestones/{milestoneId}', $protect('research.edit', [$projectAdminController, 'updateMilestone']));
    $router->post('/admin/research/projects/{id}/milestones/{milestoneId}/remove', $protect('research.edit', [$projectAdminController, 'removeMilestone']));
    $router->get('/admin/research/publications', $protect('research.view', [$publicationAdminController, 'index']));
    $router->get('/admin/research/publications/create', $protect('research.create', [$publicationAdminController, 'create']));
    $router->post('/admin/research/publications', $protect('research.create', [$publicationAdminController, 'store']));
    $router->get('/admin/research/publications/{id}', $protect('research.view', [$publicationAdminController, 'show']));
    $router->get('/admin/research/publications/{id}/edit', $protect('research.edit', [$publicationAdminController, 'edit']));
    $router->post('/admin/research/publications/{id}', $protect('research.edit', [$publicationAdminController, 'update']));
    $router->post('/admin/research/publications/{id}/workflow', $protect('research.view', [$publicationAdminController, 'workflow']));
    $router->post('/admin/research/publications/{id}/authors', $protect('research.edit', [$publicationAdminController, 'addAuthor']));
    $router->post('/admin/research/publications/{id}/authors/{authorId}/remove', $protect('research.edit', [$publicationAdminController, 'removeAuthor']));
    $router->post('/admin/research/publications/{id}/themes', $protect('research.edit', [$researchMetadataAdminController, 'addPublicationTheme']));
    $router->post('/admin/research/publications/{id}/themes/{themeId}/remove', $protect('research.edit', [$researchMetadataAdminController, 'removePublicationTheme']));
    $router->get('/admin/research/metadata', $protect('research.view', [$researchMetadataAdminController, 'index']));
    $router->get('/admin/research/metadata/themes/create', $protect('research.create', [$researchMetadataAdminController, 'createTheme']));
    $router->post('/admin/research/metadata/themes', $protect('research.create', [$researchMetadataAdminController, 'storeTheme']));
    $router->get('/admin/research/metadata/themes/{themeId}/edit', $protect('research.edit', [$researchMetadataAdminController, 'editTheme']));
    $router->post('/admin/research/metadata/themes/{themeId}', $protect('research.edit', [$researchMetadataAdminController, 'updateTheme']));
    $router->post('/admin/research/metadata/themes/{themeId}/workflow', $protect('research.edit', [$researchMetadataAdminController, 'themeWorkflow']));
    $router->get('/admin/research/metadata/partners/create', $protect('research.create', [$researchMetadataAdminController, 'createPartner']));
    $router->post('/admin/research/metadata/partners', $protect('research.create', [$researchMetadataAdminController, 'storePartner']));
    $router->get('/admin/research/metadata/partners/{partnerId}/edit', $protect('research.edit', [$researchMetadataAdminController, 'editPartner']));
    $router->post('/admin/research/metadata/partners/{partnerId}', $protect('research.edit', [$researchMetadataAdminController, 'updatePartner']));
    $router->post('/admin/research/metadata/partners/{partnerId}/workflow', $protect('research.edit', [$researchMetadataAdminController, 'partnerWorkflow']));
    $router->post('/admin/research/projects/{id}/themes', $protect('research.edit', [$researchMetadataAdminController, 'addProjectTheme']));
    $router->post('/admin/research/projects/{id}/themes/{themeId}/remove', $protect('research.edit', [$researchMetadataAdminController, 'removeProjectTheme']));
    $router->post('/admin/research/projects/{id}/sdgs', $protect('research.edit', [$researchMetadataAdminController, 'addProjectSdg']));
    $router->post('/admin/research/projects/{id}/sdgs/{sdgId}/remove', $protect('research.edit', [$researchMetadataAdminController, 'removeProjectSdg']));
    $router->post('/admin/research/projects/{id}/partners', $protect('research.edit', [$researchMetadataAdminController, 'addProjectPartner']));
    $router->post('/admin/research/projects/{id}/partners/{linkId}/remove', $protect('research.edit', [$researchMetadataAdminController, 'removeProjectPartner']));
    $router->get('/admin/innovations', $protect('research.view', [$innovationAdminController, 'innovations']));
    $router->get('/admin/innovations/create', $protect('research.create', [$innovationAdminController, 'createInnovation']));
    $router->post('/admin/innovations', $protect('research.create', [$innovationAdminController, 'storeInnovation']));
    $router->get('/admin/innovations/{id}/edit', $protect('research.edit', [$innovationAdminController, 'editInnovation']));
    $router->post('/admin/innovations/{id}', $protect('research.edit', [$innovationAdminController, 'updateInnovation']));
    $router->post('/admin/innovations/{id}/workflow', $protect('research.edit', [$innovationAdminController, 'innovationWorkflow']));
    $router->get('/admin/facilities', $protect('research.view', [$innovationAdminController, 'facilities']));
    $router->get('/admin/facilities/create', $protect('research.create', [$innovationAdminController, 'createFacility']));
    $router->post('/admin/facilities', $protect('research.create', [$innovationAdminController, 'storeFacility']));
    $router->get('/admin/facilities/{id}/edit', $protect('research.edit', [$innovationAdminController, 'editFacility']));
    $router->post('/admin/facilities/{id}', $protect('research.edit', [$innovationAdminController, 'updateFacility']));
    $router->get('/admin/facilities/equipment/create', $protect('research.create', [$innovationAdminController, 'createEquipment']));
    $router->post('/admin/facilities/equipment', $protect('research.create', [$innovationAdminController, 'storeEquipment']));
    $router->get('/admin/facilities/equipment/{id}/edit', $protect('research.edit', [$innovationAdminController, 'editEquipment']));
    $router->post('/admin/facilities/equipment/{id}', $protect('research.edit', [$innovationAdminController, 'updateEquipment']));
    $router->get('/admin/engagement', $protect('research.view', [$engagementAdminController, 'index']));
    $router->get('/admin/engagement/partnerships/create', $protect('research.create', [$engagementAdminController, 'createPartnership']));
    $router->post('/admin/engagement/partnerships', $protect('research.create', [$engagementAdminController, 'storePartnership']));
    $router->get('/admin/engagement/partnerships/{id}/edit', $protect('research.edit', [$engagementAdminController, 'editPartnership']));
    $router->post('/admin/engagement/partnerships/{id}', $protect('research.edit', [$engagementAdminController, 'updatePartnership']));
    $router->post('/admin/engagement/partnerships/{id}/workflow', $protect('research.edit', [$engagementAdminController, 'partnershipWorkflow']));
    $router->get('/admin/engagement/impact/create', $protect('research.create', [$engagementAdminController, 'createImpact']));
    $router->post('/admin/engagement/impact', $protect('research.create', [$engagementAdminController, 'storeImpact']));
    $router->get('/admin/engagement/impact/{id}/edit', $protect('research.edit', [$engagementAdminController, 'editImpact']));
    $router->post('/admin/engagement/impact/{id}', $protect('research.edit', [$engagementAdminController, 'updateImpact']));
    $router->post('/admin/engagement/impact/{id}/workflow', $protect('research.edit', [$engagementAdminController, 'impactWorkflow']));
    $router->get('/admin/news', $protect('news.view', [$siteContentAdminController, 'news']));
    $router->get('/admin/news/create', $protect('news.create', [$siteContentAdminController, 'createNews']));
    $router->post('/admin/news', $protect('news.create', [$siteContentAdminController, 'storeNews']));
    $router->get('/admin/news/{id}/edit', $protect('news.edit', [$siteContentAdminController, 'editNews']));
    $router->post('/admin/news/{id}', $protect('news.edit', [$siteContentAdminController, 'updateNews']));
    $router->post('/admin/news/{id}/workflow', $protect('news.view', [$siteContentAdminController, 'newsWorkflow']));
    $router->post('/admin/news/{id}/gallery', $protect('news.edit', [$siteContentAdminController, 'addNewsGalleryPhoto']));
    $router->post('/admin/news/{id}/gallery/{mediaId}/remove', $protect('news.edit', [$siteContentAdminController, 'removeNewsGalleryPhoto']));
    $router->get('/admin/events', $protect('events.manage', [$siteContentAdminController, 'events']));
    $router->get('/admin/events/create', $protect('events.manage', [$siteContentAdminController, 'createEvent']));
    $router->post('/admin/events', $protect('events.manage', [$siteContentAdminController, 'storeEvent']));
    $router->get('/admin/events/{id}/edit', $protect('events.manage', [$siteContentAdminController, 'editEvent']));
    $router->post('/admin/events/{id}', $protect('events.manage', [$siteContentAdminController, 'updateEvent']));
    $router->post('/admin/events/{id}/workflow', $protect('events.manage', [$siteContentAdminController, 'eventWorkflow']));
    $router->get('/admin/pages', $protect('pages.manage', [$siteContentAdminController, 'pages']));
    $router->get('/admin/pages/create', $protect('pages.manage', [$siteContentAdminController, 'createPage']));
    $router->post('/admin/pages', $protect('pages.manage', [$siteContentAdminController, 'storePage']));
    $router->get('/admin/pages/{id}/edit', $protect('pages.manage', [$siteContentAdminController, 'editPage']));
    $router->post('/admin/pages/{id}', $protect('pages.manage', [$siteContentAdminController, 'updatePage']));
    $router->post('/admin/pages/{id}/workflow', $protect('pages.manage', [$siteContentAdminController, 'pageWorkflow']));
    $router->post('/admin/pages/{id}/sections', $protect('pages.manage', [$siteContentAdminController, 'addSection']));
    $router->post('/admin/pages/{id}/sections/{sectionId}', $protect('pages.manage', [$siteContentAdminController, 'updateSection']));
    $router->post('/admin/pages/{id}/sections/{sectionId}/remove', $protect('pages.manage', [$siteContentAdminController, 'removeSection']));
    $router->get('/admin/media', $protect('media.manage', [$assetAdminController, 'media']));
    $router->post('/admin/media', $protect('media.manage', [$assetAdminController, 'uploadMedia']));
    $router->get('/admin/media/{id}/edit', $protect('media.manage', [$assetAdminController, 'editMedia']));
    $router->post('/admin/media/{id}', $protect('media.manage', [$assetAdminController, 'updateMedia']));
    $router->post('/admin/media/{id}/workflow', $protect('media.manage', [$assetAdminController, 'mediaWorkflow']));
    $router->get('/admin/documents', $protect('documents.manage', [$assetAdminController, 'docs']));
    $router->get('/admin/documents/create', $protect('documents.manage', [$assetAdminController, 'createDoc']));
    $router->post('/admin/documents', $protect('documents.manage', [$assetAdminController, 'storeDoc']));
    $router->get('/admin/documents/{id}/edit', $protect('documents.manage', [$assetAdminController, 'editDoc']));
    $router->post('/admin/documents/{id}', $protect('documents.manage', [$assetAdminController, 'updateDoc']));
    $router->post('/admin/documents/{id}/workflow', $protect('documents.manage', [$assetAdminController, 'docWorkflow']));
    $router->get('/admin/users', $protect('users.manage', [$userAdminController, 'index']));
    $router->get('/admin/users/create', $protect('users.manage', [$userAdminController, 'create']));
    $router->post('/admin/users', $protect('users.manage', [$userAdminController, 'store']));
    $router->get('/admin/users/{id}/edit', $protect('users.manage', [$userAdminController, 'edit']));
    $router->post('/admin/users/{id}', $protect('users.manage', [$userAdminController, 'update']));
    $router->get('/admin/research/{id}', $protect('research.view', [$researchAdminController, 'show']));
    $router->get('/admin/research/{id}/edit', $protect('research.edit', [$researchAdminController, 'edit']));
    $router->post('/admin/research/{id}', $protect('research.edit', [$researchAdminController, 'update']));
    $router->post('/admin/research/{id}/workflow', $protect('research.view', [$researchAdminController, 'workflow']));
    $router->post('/admin/research/{id}/members', $protect('research.edit', [$researchAdminController, 'addMember']));
    $router->post('/admin/research/{id}/members/{memberId}/remove', $protect('research.edit', [$researchAdminController, 'removeMember']));
    $router->post(
        '/logout',
        static fn (Request $request): Response => $requireAuth->handle(
            $request,
            [$authController, 'logout']
        )
    );

    if ($development) {
        $router->get(
            '/__debug/error',
            static function (Request $request): Response {
                throw new RuntimeException('Controlled Phase 1 error test.');
            }
        );
    }
    $router->get('/{slug}', [$siteContentPublicController, 'page']);
};
