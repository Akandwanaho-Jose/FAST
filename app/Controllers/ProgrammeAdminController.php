<?php

declare(strict_types=1);

namespace FastWebsite\Controllers;

use FastWebsite\Core\Controller;
use FastWebsite\Core\Csrf;
use FastWebsite\Core\HttpException;
use FastWebsite\Core\Request;
use FastWebsite\Core\Response;
use FastWebsite\Core\Session;
use FastWebsite\Repositories\ProgrammeRepository;
use FastWebsite\Services\AdminPageContext;
use FastWebsite\Services\AuthService;
use FastWebsite\Services\AuthorizationService;
use FastWebsite\Services\MediaUploadService;
use FastWebsite\Services\ProgrammeService;
use FastWebsite\Validation\ProgrammeValidator;
use Throwable;

final class ProgrammeAdminController extends Controller
{
    private const STATUSES = ['draft', 'under_review', 'approved', 'published', 'archived'];
    private const FIELDS = [
        'programme_level_id', 'department_id', 'programme_code', 'name',
        'award_title', 'slug', 'overview', 'why_study', 'objectives',
        'learning_outcomes', 'entry_requirements', 'career_opportunities',
        'practical_training', 'duration_years', 'duration_text', 'study_mode',
        'delivery_mode', 'accreditation', 'application_url', 'hero_media_id',
        'hero_alt_text', 'display_order',
    ];

    public function __construct(
        \FastWebsite\Core\View $view,
        private readonly AuthService $auth,
        private readonly AuthorizationService $authorization,
        private readonly AdminPageContext $context,
        private readonly ProgrammeRepository $programmes,
        private readonly ProgrammeValidator $validator,
        private readonly ProgrammeService $service,
        private readonly MediaUploadService $uploads,
        private readonly Csrf $csrf,
        private readonly Session $session
    ) {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        $user = $this->user();
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $status = (string) $request->query('status', '');
        $status = in_array($status, self::STATUSES, true) ? $status : '';
        $page = ctype_digit((string) $request->query('page', '1')) ? max(1, (int) $request->query('page', '1')) : 1;
        return $this->adminView($request, $user, 'admin/programmes/index', [
            'pageTitle' => 'Programmes',
            'result' => $this->programmes->paginateAdmin((int) $user['id'], $search, $status, $page),
            'search' => $search,
            'statusFilter' => $status,
            'statuses' => self::STATUSES,
            'canCreate' => $this->service->canCreate((int) $user['id']),
        ]);
    }

    public function show(Request $request): Response
    {
        $user = $this->user();
        $id = $this->id($request);
        $programme = $this->programmes->findAdmin($id, (int) $user['id']);
        if ($programme === null) {
            throw new HttpException(404, 'Programme not found.');
        }
        $canPublish = $this->service->canDirectPublish((int) $user['id'], $id);
        return $this->adminView($request, $user, 'admin/programmes/show', [
            'pageTitle' => $programme['name'],
            'programme' => $programme,
            'history' => $this->programmes->approvalHistory($id),
            'actions' => $this->service->actions((int) $user['id'], $id, (string) $programme['status']),
            'canEdit' => ($programme['status'] === 'draft' && $this->authorization->can((int) $user['id'], 'programmes.edit'))
                || ($programme['status'] === 'published' && $canPublish),
            'canQuickPublish' => $programme['status'] === 'draft' && trim((string) ($programme['overview'] ?? '')) !== '' && $canPublish,
            'needsOverviewToPublish' => $programme['status'] === 'draft' && trim((string) ($programme['overview'] ?? '')) === '' && $canPublish,
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $this->user();
        $this->assert((int) $user['id'], 'programmes.create');
        return $this->form($request, $user);
    }

    public function store(Request $request): Response
    {
        $user = $this->user();
        $this->assert((int) $user['id'], 'programmes.create');
        return $this->save($request, $user);
    }

    public function edit(Request $request): Response
    {
        $user = $this->user();
        $this->assert((int) $user['id'], 'programmes.edit');
        $id = $this->id($request);
        $programme = $this->programmes->findAdmin($id, (int) $user['id']);
        if ($programme === null) {
            throw new HttpException(404, 'Programme not found.');
        }
        if ($programme['status'] !== 'draft' && !($programme['status'] === 'published' && $this->service->canDirectPublish((int) $user['id'], $id))) {
            throw new HttpException(409, 'This programme must be a draft or published with publishing access before editing.');
        }
        return $this->form($request, $user, $programme, [], $id, '', (string) $programme['status']);
    }

    public function update(Request $request): Response
    {
        $user = $this->user();
        $this->assert((int) $user['id'], 'programmes.edit');
        return $this->save($request, $user, $this->id($request));
    }

    public function workflow(Request $request): Response
    {
        $user = $this->user();
        $id = $this->id($request);
        $this->csrf($request);
        $action = mb_substr(trim((string) $request->input('action', '')), 0, 40);
        $comment = mb_substr(trim((string) $request->input('comment', '')), 0, 2000);
        if ($action === 'publish_now') {
            $this->service->publishDraft($id, (int) $user['id'], $comment !== '' ? $comment : 'Published directly.', $request->ipAddress(), $request->userAgent());
            $this->session->put('_flash_success', 'Programme published successfully.');
            return Response::redirect($request->baseUrl() . 'admin/programmes/' . $id);
        }
        $target = $this->service->transition($id, $action, (int) $user['id'], $comment, $request->ipAddress(), $request->userAgent());
        $this->session->put('_flash_success', 'Programme status changed to ' . str_replace('_', ' ', $target) . '.');
        return Response::redirect($request->baseUrl() . 'admin/programmes/' . $id);
    }

    /** @param array<string,mixed> $user */
    private function save(Request $request, array $user, ?int $id = null): Response
    {
        $this->csrf($request);
        $currentStatus = 'draft';
        if ($id !== null) {
            $existing = $this->programmes->findAdmin($id, (int) $user['id']);
            if ($existing === null) {
                throw new HttpException(404, 'Programme not found.');
            }
            $currentStatus = (string) $existing['status'];
        }
        $values = $this->values($request);
        $validation = $this->validator->validate($values, $id);
        $upload = $this->uploads->validate($request->file('hero_image'), (string) ($values['hero_alt_text'] ?? ''));
        $errors = [...$validation['errors'], ...$upload['errors']];
        $note = mb_substr(trim((string) $request->input('revision_note', '')), 0, 2000);
        if ($errors !== []) {
            return $this->form($request, $user, $values, $errors, $id, $note, $currentStatus);
        }
        $departmentId = (int) $validation['department_id'];
        $operation = function (array $data) use ($id, $departmentId, $user, $request, $note, $currentStatus): int {
            if ($id === null) {
                return $request->input('submit_action') === 'publish'
                    ? $this->service->createAndPublish($data, $departmentId, (int) $user['id'], $request->ipAddress(), $request->userAgent())
                    : $this->service->create($data, $departmentId, (int) $user['id'], $request->ipAddress(), $request->userAgent());
            }
            if ($currentStatus === 'published') {
                $this->service->updatePublished($id, $data, $departmentId, (int) $user['id'], $note, $request->ipAddress(), $request->userAgent());
            } elseif ($request->input('submit_action') === 'publish') {
                $this->service->updateAndPublish($id, $data, $departmentId, (int) $user['id'], $note, $request->ipAddress(), $request->userAgent());
            } else {
                $this->service->update($id, $data, $departmentId, (int) $user['id'], $note, $request->ipAddress(), $request->userAgent());
            }
            return $id;
        };
        $savedId = $this->withImage($validation['data'], $upload['upload'], (int) $user['id'], $operation);
        $message = $id === null
            ? ($request->input('submit_action') === 'publish' ? 'Programme created and published.' : 'Programme created as a draft.')
            : ($currentStatus === 'published' ? 'Published programme updated.' : ($request->input('submit_action') === 'publish' ? 'Programme saved and published.' : 'Programme updated.'));
        $this->session->put('_flash_success', $message);
        return Response::redirect($request->baseUrl() . 'admin/programmes/' . $savedId);
    }

    /** @param array<string,mixed> $user @param array<string,mixed> $values @param list<string> $errors */
    private function form(Request $request, array $user, array $values = [], array $errors = [], ?int $id = null, string $note = '', string $status = 'draft'): Response
    {
        if ($values === []) {
            $levels = $this->programmes->levels();
            $departments = $this->programmes->departments();
            $values = [
                'programme_level_id' => (string) ($levels[0]['id'] ?? ''),
                'department_id' => (string) ($departments[0]['id'] ?? ''),
                'study_mode' => 'full_time',
                'delivery_mode' => 'face_to_face',
                'display_order' => '0',
            ];
        }
        return $this->adminView($request, $user, 'admin/programmes/form', [
            'pageTitle' => $id === null ? 'Create programme' : 'Edit programme',
            'programmeId' => $id,
            'values' => $values,
            'errors' => $errors,
            'levels' => $this->programmes->levels(),
            'departments' => $this->programmes->departments(),
            'images' => $this->programmes->activeImages(),
            'revisionNote' => $note,
            'canPublishDirectly' => $id !== null
                ? $this->service->canDirectPublish((int) $user['id'], $id)
                : $this->authorization->can((int) $user['id'], 'programmes.publish') && $this->authorization->can((int) $user['id'], 'content.approve'),
            'currentStatus' => $status,
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

    private function id(Request $request): int
    {
        $value = (string) $request->route('id', '');
        if (!ctype_digit($value) || (int) $value < 1) {
            throw new HttpException(404, 'Programme not found.');
        }
        return (int) $value;
    }

    /** @return array<string,string|null> */
    private function values(Request $request): array
    {
        $values = [];
        foreach (self::FIELDS as $field) {
            $values[$field] = $request->input($field);
        }
        return $values;
    }

    private function assert(int $userId, string $permission): void
    {
        if (!$this->authorization->can($userId, $permission)) {
            throw new HttpException(403, 'This programme action is not allowed.');
        }
    }

    private function csrf(Request $request): void
    {
        if (!$this->csrf->validate($request->input('_token'))) {
            throw new HttpException(403, 'The secure form session expired.');
        }
    }

    /** @param array<string,mixed> $data @param array<string,mixed>|null $upload */
    private function withImage(array $data, ?array $upload, int $userId, callable $operation): mixed
    {
        if ($upload === null) {
            return $operation($data);
        }
        $connection = $this->programmes->connection();
        $owns = !$connection->inTransaction();
        $path = null;
        if ($owns) {
            $connection->beginTransaction();
        }
        try {
            $stored = $this->uploads->store($upload, $userId, 'programmes');
            $path = $stored['absolute_path'];
            $data['hero_media_id'] = $stored['id'];
            $result = $operation($data);
            if ($owns) {
                $connection->commit();
            }
            return $result;
        } catch (Throwable $exception) {
            if ($owns && $connection->inTransaction()) {
                $connection->rollBack();
            }
            $this->uploads->removeStoredFile($path);
            throw $exception;
        }
    }
}
