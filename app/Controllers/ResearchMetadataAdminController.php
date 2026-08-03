<?php

declare(strict_types=1);

namespace FastWebsite\Controllers;

use FastWebsite\Core\Controller;
use FastWebsite\Core\Csrf;
use FastWebsite\Core\HttpException;
use FastWebsite\Core\Request;
use FastWebsite\Core\Response;
use FastWebsite\Core\Session;
use FastWebsite\Repositories\ResearchMetadataRepository;
use FastWebsite\Services\AdminPageContext;
use FastWebsite\Services\AuthService;
use FastWebsite\Services\AuthorizationService;
use FastWebsite\Services\ResearchMetadataService;
use FastWebsite\Services\MediaUploadService;
use FastWebsite\Validation\ResearchMetadataValidator;
use Throwable;

final class ResearchMetadataAdminController extends Controller
{
    public function __construct(
        \FastWebsite\Core\View $view,
        private readonly AuthService $auth,
        private readonly AuthorizationService $authorization,
        private readonly AdminPageContext $context,
        private readonly ResearchMetadataRepository $metadata,
        private readonly ResearchMetadataValidator $validator,
        private readonly ResearchMetadataService $service,
        private readonly MediaUploadService $uploads,
        private readonly Csrf $csrf,
        private readonly Session $session
    ) {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        $user = $this->user();
        return $this->adminView($request, $user, 'admin/research-metadata/index', [
            'pageTitle'=>'Research metadata',
            'themes'=>$this->metadata->themes((int) $user['id']),
            'partners'=>$this->metadata->partners(),
            'sdgs'=>$this->metadata->sdgs(),
            'canCreate'=>$this->authorization->can((int) $user['id'], 'research.create'),
            'canEdit'=>$this->authorization->can((int) $user['id'], 'research.edit'),
        ]);
    }

    public function createTheme(Request $request): Response
    {
        $user = $this->user();
        $this->assert((int) $user['id'], 'research.create');
        return $this->themeForm($request, $user);
    }

    public function storeTheme(Request $request): Response
    {
        $user = $this->user();
        $this->assert((int) $user['id'], 'research.create');
        return $this->saveTheme($request, $user);
    }

    public function editTheme(Request $request): Response
    {
        $user = $this->user();
        $this->assert((int) $user['id'], 'research.edit');
        $id = $this->routeId($request, 'themeId', 'Research theme');
        $theme = $this->metadata->findTheme($id, (int) $user['id']);
        if ($theme === null) {
            throw new HttpException(404, 'Research theme not found.');
        }
        return $this->themeForm($request, $user, $theme, [], $id);
    }

    public function updateTheme(Request $request): Response
    {
        $user = $this->user();
        $this->assert((int) $user['id'], 'research.edit');
        return $this->saveTheme($request, $user, $this->routeId($request, 'themeId', 'Research theme'));
    }

    public function themeWorkflow(Request $request): Response
    {
        $this->csrf($request);
        $user = $this->user();
        $id = $this->routeId($request, 'themeId', 'Research theme');
        $target = $this->service->themeStatus(
            $id,
            (string) $request->input('action', ''),
            (int) $user['id'],
            $request->ipAddress(),
            $request->userAgent()
        );
        $this->session->put('_flash_success', 'Research theme status changed to ' . $target . '.');
        return Response::redirect($request->baseUrl() . 'admin/research/metadata');
    }

    public function createPartner(Request $request): Response
    {
        $user = $this->user();
        $this->assert((int) $user['id'], 'research.create');
        return $this->partnerForm($request, $user);
    }

    public function storePartner(Request $request): Response
    {
        $user = $this->user();
        $this->assert((int) $user['id'], 'research.create');
        return $this->savePartner($request, $user);
    }

    public function editPartner(Request $request): Response
    {
        $user = $this->user();
        $this->assert((int) $user['id'], 'research.edit');
        $id = $this->routeId($request, 'partnerId', 'Partner');
        $partner = $this->metadata->findPartner($id);
        if ($partner === null) {
            throw new HttpException(404, 'Partner not found.');
        }
        return $this->partnerForm($request, $user, $partner, [], $id);
    }

    public function updatePartner(Request $request): Response
    {
        $user = $this->user();
        $this->assert((int) $user['id'], 'research.edit');
        return $this->savePartner($request, $user, $this->routeId($request, 'partnerId', 'Partner'));
    }

    public function partnerWorkflow(Request $request): Response
    {
        $this->csrf($request);
        $user = $this->user();
        $id = $this->routeId($request, 'partnerId', 'Partner');
        $target = $this->service->partnerStatus(
            $id,
            (string) $request->input('action', ''),
            (int) $user['id'],
            $request->ipAddress(),
            $request->userAgent()
        );
        $this->session->put('_flash_success', 'Partner status changed to ' . $target . '.');
        return Response::redirect($request->baseUrl() . 'admin/research/metadata');
    }

    public function addProjectTheme(Request $request): Response
    {
        $this->csrf($request);
        $user = $this->user();
        $projectId = $this->routeId($request, 'id', 'Project');
        $themeId = $this->inputId($request, 'theme_id', 'Choose a research theme.');
        $this->service->addProjectTheme($projectId, $themeId, $request->input('is_primary') === '1', (int) $user['id'], $request->ipAddress(), $request->userAgent());
        $this->session->put('_flash_success', 'Research theme assigned.');
        return Response::redirect($request->baseUrl() . 'admin/research/projects/' . $projectId . '#metadata');
    }

    public function removeProjectTheme(Request $request): Response
    {
        $this->csrf($request);
        $user = $this->user();
        $projectId = $this->routeId($request, 'id', 'Project');
        $themeId = $this->routeId($request, 'themeId', 'Research theme');
        $this->service->removeProjectTheme($projectId, $themeId, (int) $user['id'], $request->ipAddress(), $request->userAgent());
        $this->session->put('_flash_success', 'Research theme removed.');
        return Response::redirect($request->baseUrl() . 'admin/research/projects/' . $projectId . '#metadata');
    }

    public function addProjectSdg(Request $request): Response
    {
        $this->csrf($request);
        $user = $this->user();
        $projectId = $this->routeId($request, 'id', 'Project');
        $sdgId = $this->inputId($request, 'sdg_id', 'Choose a Sustainable Development Goal.');
        $this->service->addProjectSdg($projectId, $sdgId, $request->input('is_primary') === '1', (int) $user['id'], $request->ipAddress(), $request->userAgent());
        $this->session->put('_flash_success', 'Sustainable Development Goal assigned.');
        return Response::redirect($request->baseUrl() . 'admin/research/projects/' . $projectId . '#metadata');
    }

    public function removeProjectSdg(Request $request): Response
    {
        $this->csrf($request);
        $user = $this->user();
        $projectId = $this->routeId($request, 'id', 'Project');
        $sdgId = $this->routeId($request, 'sdgId', 'Sustainable Development Goal');
        $this->service->removeProjectSdg($projectId, $sdgId, (int) $user['id'], $request->ipAddress(), $request->userAgent());
        $this->session->put('_flash_success', 'Sustainable Development Goal removed.');
        return Response::redirect($request->baseUrl() . 'admin/research/projects/' . $projectId . '#metadata');
    }

    public function addProjectPartner(Request $request): Response
    {
        $this->csrf($request);
        $user = $this->user();
        $projectId = $this->routeId($request, 'id', 'Project');
        $partnerId = $this->inputId($request, 'partner_id', 'Choose a partner organization.');
        $this->service->addProjectPartner($projectId, $partnerId, (string) $request->input('partner_role', 'other'), (string) $request->input('contribution', ''), (int) $user['id'], $request->ipAddress(), $request->userAgent());
        $this->session->put('_flash_success', 'Project partner assigned.');
        return Response::redirect($request->baseUrl() . 'admin/research/projects/' . $projectId . '#metadata');
    }

    public function removeProjectPartner(Request $request): Response
    {
        $this->csrf($request);
        $user = $this->user();
        $projectId = $this->routeId($request, 'id', 'Project');
        $linkId = $this->routeId($request, 'linkId', 'Project partner');
        $this->service->removeProjectPartner($projectId, $linkId, (int) $user['id'], $request->ipAddress(), $request->userAgent());
        $this->session->put('_flash_success', 'Project partner removed.');
        return Response::redirect($request->baseUrl() . 'admin/research/projects/' . $projectId . '#metadata');
    }

    public function addPublicationTheme(Request $request): Response
    {
        $this->csrf($request);
        $user = $this->user();
        $publicationId = $this->routeId($request, 'id', 'Publication');
        $themeId = $this->inputId($request, 'theme_id', 'Choose a research theme.');
        $this->service->addPublicationTheme($publicationId, $themeId, (int) $user['id'], $request->ipAddress(), $request->userAgent());
        $this->session->put('_flash_success', 'Research theme assigned.');
        return Response::redirect($request->baseUrl() . 'admin/research/publications/' . $publicationId . '#themes');
    }

    public function removePublicationTheme(Request $request): Response
    {
        $this->csrf($request);
        $user = $this->user();
        $publicationId = $this->routeId($request, 'id', 'Publication');
        $themeId = $this->routeId($request, 'themeId', 'Research theme');
        $this->service->removePublicationTheme($publicationId, $themeId, (int) $user['id'], $request->ipAddress(), $request->userAgent());
        $this->session->put('_flash_success', 'Research theme removed.');
        return Response::redirect($request->baseUrl() . 'admin/research/publications/' . $publicationId . '#themes');
    }

    /** @param array<string,mixed> $user */
    private function saveTheme(Request $request, array $user, ?int $id = null): Response
    {
        $this->csrf($request);
        if ($id !== null && $this->metadata->findTheme($id, (int) $user['id']) === null) {
            throw new HttpException(404, 'Research theme not found.');
        }
        $existingStatus = $id !== null ? (string) ($this->metadata->findTheme($id, (int) $user['id'])['status'] ?? 'draft') : 'draft';
        $values = [
            'name'=>$request->input('name'), 'slug'=>$request->input('slug'),
            'description'=>$request->input('description'), 'icon'=>$request->input('icon'),
            'display_order'=>$request->input('display_order'), 'department_id'=>$request->input('department_id'),
            'parent_theme_id'=>$request->input('parent_theme_id'),
        ];
        $validation = $this->validator->theme($values, (int) $user['id'], $id);
        if ($validation['errors'] !== []) {
            return $this->themeForm($request, $user, $values, $validation['errors'], $id);
        }
        $publish = $request->input('submit_action') === 'publish';
        $saved = $this->service->saveTheme($id, $validation['data'], $validation['department_id'], (int) $user['id'], $publish, $request->ipAddress(), $request->userAgent());
        $this->session->put('_flash_success', $publish ? 'Research theme saved and published.' : ($existingStatus === 'published' ? 'Published research theme updated.' : 'Research theme saved as a draft.'));
        return Response::redirect($request->baseUrl() . 'admin/research/metadata#themes');
    }

    /** @param array<string,mixed> $user */
    private function savePartner(Request $request, array $user, ?int $id = null): Response
    {
        $this->csrf($request);
        if ($id !== null && $this->metadata->findPartner($id) === null) {
            throw new HttpException(404, 'Partner not found.');
        }
        $values = [
            'name'=>$request->input('name'), 'slug'=>$request->input('slug'),
            'partner_type'=>$request->input('partner_type'), 'country'=>$request->input('country'),
            'website_url'=>$request->input('website_url'), 'description'=>$request->input('description'),
            'logo_media_id'=>$request->input('logo_media_id'),
        ];
        $validation = $this->validator->partner($values, $id);
        $upload = $this->uploads->validate($request->file('partner_logo'), (string) $request->input('logo_alt_text', ''));
        $errors = [...$validation['errors'], ...$upload['errors']];
        if ($errors !== []) {
            return $this->partnerForm($request, $user, $values, $errors, $id);
        }
        $publish = $request->input('submit_action') === 'publish';
        $data = $validation['data'];
        $this->withPartnerLogo($data, $upload['upload'], (int) $user['id'], function (array $prepared) use ($id, $user, $publish, $request): void {
            $this->service->savePartner($id, $prepared, (int) $user['id'], $publish, $request->ipAddress(), $request->userAgent());
        });
        $existingStatus = $id !== null ? (string) ($this->metadata->findPartner($id)['status'] ?? 'draft') : 'draft';
        $this->session->put('_flash_success', $publish ? 'Partner saved and published.' : ($existingStatus === 'published' ? 'Published partner updated.' : 'Partner saved as a draft.'));
        return Response::redirect($request->baseUrl() . 'admin/research/metadata#partners');
    }

    /** @param array<string,mixed> $user @param array<string,mixed> $values @param list<string> $errors */
    private function themeForm(Request $request, array $user, array $values = [], array $errors = [], ?int $id = null): Response
    {
        if ($values === []) {
            $departments = $this->metadata->departments((int) $user['id']);
            $values = ['department_id'=>(string) ($departments[0]['id'] ?? ''), 'display_order'=>'0'];
        }
        return $this->adminView($request, $user, 'admin/research-metadata/theme-form', [
            'pageTitle'=>$id === null ? 'Create research theme' : 'Edit research theme',
            'themeId'=>$id, 'values'=>$values, 'errors'=>$errors,
            'departments'=>$this->metadata->departments((int) $user['id']),
            'parentThemes'=>array_values(array_filter($this->metadata->themes((int) $user['id']), static fn (array $theme): bool => (int) $theme['id'] !== $id)),
            'currentStatus'=>(string) ($values['status'] ?? 'draft'),
            'canPublish'=>$this->authorization->can((int) $user['id'], 'research.publish') && $this->authorization->can((int) $user['id'], 'content.approve'),
        ]);
    }

    /** @param array<string,mixed> $user @param array<string,mixed> $values @param list<string> $errors */
    private function partnerForm(Request $request, array $user, array $values = [], array $errors = [], ?int $id = null): Response
    {
        if ($values === []) {
            $values = ['partner_type'=>'other'];
        }
        return $this->adminView($request, $user, 'admin/research-metadata/partner-form', [
            'pageTitle'=>$id === null ? 'Create partner' : 'Edit partner',
            'partnerId'=>$id, 'values'=>$values, 'errors'=>$errors,
            'partnerTypes'=>ResearchMetadataValidator::PARTNER_TYPES,
            'images'=>$this->metadata->activeImages(),
            'currentStatus'=>(string) ($values['status'] ?? 'draft'),
            'canPublish'=>$this->authorization->can((int) $user['id'], 'research.publish') && $this->authorization->can((int) $user['id'], 'content.approve'),
        ]);
    }

    /** @param array<string,mixed> $user @param array<string,mixed> $data */
    private function adminView(Request $request, array $user, string $template, array $data): Response
    {
        return $this->view($template, array_merge($this->context->data($request, $user), $data), 200, 'layouts/admin')->withHeader('Cache-Control', 'no-store');
    }

    /** @return array<string,mixed> */
    private function user(): array
    {
        return $this->auth->user() ?? throw new HttpException(403, 'Authentication required.');
    }

    private function routeId(Request $request, string $field, string $label): int
    {
        $value = (string) $request->route($field, '');
        if (!ctype_digit($value) || (int) $value < 1) {
            throw new HttpException(404, $label . ' not found.');
        }
        return (int) $value;
    }

    private function inputId(Request $request, string $field, string $message): int
    {
        $value = trim((string) $request->input($field, ''));
        if (!ctype_digit($value) || (int) $value < 1) {
            throw new HttpException(422, $message);
        }
        return (int) $value;
    }

    private function assert(int $userId, string $permission): void
    {
        if (!$this->authorization->can($userId, $permission)) {
            throw new HttpException(403, 'This research metadata action is not allowed.');
        }
    }

    private function csrf(Request $request): void
    {
        if (!$this->csrf->validate($request->input('_token'))) {
            throw new HttpException(403, 'The secure form session expired.');
        }
    }

    /** @param array<string,mixed> $data @param array<string,mixed>|null $upload */
    private function withPartnerLogo(array $data, ?array $upload, int $userId, callable $operation): mixed
    {
        if ($upload === null) {
            return $operation($data);
        }
        $connection = $this->metadata->connection();
        $owns = !$connection->inTransaction();
        $path = null;
        if ($owns) $connection->beginTransaction();
        try {
            $stored = $this->uploads->store($upload, $userId, 'partners');
            $path = $stored['absolute_path'];
            $data['logo_media_id'] = $stored['id'];
            $result = $operation($data);
            if ($owns) $connection->commit();
            return $result;
        } catch (Throwable $exception) {
            if ($owns && $connection->inTransaction()) $connection->rollBack();
            $this->uploads->removeStoredFile($path);
            throw $exception;
        }
    }
}
