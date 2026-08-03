<?php

declare(strict_types=1);

namespace FastWebsite\Controllers;

use FastWebsite\Core\Controller;
use FastWebsite\Core\Csrf;
use FastWebsite\Core\HttpException;
use FastWebsite\Core\Request;
use FastWebsite\Core\Response;
use FastWebsite\Core\Session;
use FastWebsite\Repositories\DepartmentRepository;
use FastWebsite\Services\AdminPageContext;
use FastWebsite\Services\AuthService;
use FastWebsite\Services\AuthorizationService;
use FastWebsite\Services\ContentWorkflowService;
use FastWebsite\Services\DepartmentScopeService;
use FastWebsite\Services\DepartmentService;
use FastWebsite\Services\MediaUploadService;
use FastWebsite\Validation\DepartmentValidator;
use Throwable;

final class DepartmentAdminController extends Controller
{
    private const STATUSES = [
        'draft',
        'under_review',
        'approved',
        'published',
        'archived',
    ];

    private const FORM_FIELDS = [
        'faculty_id',
        'name',
        'short_name',
        'slug',
        'overview',
        'history',
        'vision',
        'mission',
        'strategic_direction',
        'hod_message',
        'email',
        'phone',
        'location_id',
        'hero_media_id',
        'hero_alt_text',
        'display_order',
    ];

    public function __construct(
        \FastWebsite\Core\View $view,
        private readonly AuthService $auth,
        private readonly AuthorizationService $authorization,
        private readonly DepartmentScopeService $scope,
        private readonly AdminPageContext $pageContext,
        private readonly DepartmentRepository $departments,
        private readonly DepartmentValidator $validator,
        private readonly DepartmentService $service,
        private readonly MediaUploadService $mediaUploads,
        private readonly ContentWorkflowService $workflow,
        private readonly Csrf $csrf,
        private readonly Session $session
    ) {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        $user = $this->requireUser();
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $status = (string) $request->query('status', '');
        $status = in_array($status, self::STATUSES, true) ? $status : '';
        $page = $this->positiveRouteInteger($request->query('page', '1'), 1);
        $result = $this->departments->paginateAdmin(
            (int) $user['id'],
            $search,
            $status,
            $page
        );
        $canEditDepartments = $this->authorization->can(
            (int) $user['id'],
            'departments.edit'
        );

        foreach ($result['items'] as &$department) {
            $department['can_edit'] = ($canEditDepartments
                    && $department['status'] === 'draft'
                    && $this->scope->canAccess(
                        (int) $user['id'],
                        (int) $department['id'],
                        'edit'
                    ))
                || ($department['status'] === 'published'
                    && $this->workflow->canPublishDraftDirectly(
                        (int) $user['id'],
                        (int) $department['id']
                    ));
        }
        unset($department);

        return $this->adminView(
            $request,
            $user,
            'admin/departments/index',
            [
                'pageTitle' => 'Departments',
                'result' => $result,
                'search' => $search,
                'statusFilter' => $status,
                'statuses' => self::STATUSES,
                'canCreate' => $this->authorization->can(
                    (int) $user['id'],
                    'departments.create'
                ) && $this->scope->hasGlobalScope((int) $user['id']),
            ]
        );
    }

    public function faculty(Request $request): Response
    {
        $user = $this->requireUser();
        $faculty = $this->departments->faculty();

        if ($faculty === null) {
            throw new HttpException(404, 'Faculty not found.');
        }

        return $this->adminView(
            $request,
            $user,
            'admin/departments/faculty',
            ['pageTitle' => 'Faculty settings', 'faculty' => $faculty]
        );
    }

    public function show(Request $request): Response
    {
        $user = $this->requireUser();
        $id = $this->routeId($request);
        $department = $this->departments->findAdmin($id, (int) $user['id']);

        if ($department === null) {
            throw new HttpException(404, 'Department not found.');
        }

        return $this->adminView(
            $request,
            $user,
            'admin/departments/show',
            [
                'pageTitle' => (string) $department['name'],
                'department' => $department,
                'history' => $this->departments->approvalHistory($id),
                'workflowActions' => $this->workflow->availableDepartmentActions(
                    (int) $user['id'],
                    $id,
                    (string) $department['status']
                ),
                'canEdit' => ($department['status'] === 'draft'
                        && $this->authorization->can(
                            (int) $user['id'],
                            'departments.edit'
                        )
                        && $this->scope->canAccess(
                            (int) $user['id'],
                            $id,
                            'edit'
                        ))
                    || ($department['status'] === 'published'
                        && $this->workflow->canPublishDraftDirectly(
                            (int) $user['id'],
                            $id
                        )),
                'canQuickPublish' => $department['status'] === 'draft'
                    && trim((string) ($department['overview'] ?? '')) !== ''
                    && $this->workflow->canPublishDraftDirectly(
                        (int) $user['id'],
                        $id
                    ),
                'needsOverviewToPublish' => $department['status'] === 'draft'
                    && trim((string) ($department['overview'] ?? '')) === ''
                    && $this->workflow->canPublishDraftDirectly(
                        (int) $user['id'],
                        $id
                    ),
            ]
        );
    }

    public function create(Request $request): Response
    {
        $user = $this->requireUser();
        $this->assertCanCreate((int) $user['id']);

        return $this->renderForm($request, $user);
    }

    public function store(Request $request): Response
    {
        $user = $this->requireUser();
        $this->assertCanCreate((int) $user['id']);

        if (!$this->csrf->validate($request->input('_token'))) {
            return $this->csrfFailure($request);
        }

        $values = $this->formValues($request);
        $validation = $this->validator->validate($values);
        $uploadValidation = $this->mediaUploads->validate(
            $request->file('hero_image'),
            (string) ($values['hero_alt_text'] ?? '')
        );
        $errors = [
            ...$validation['errors'],
            ...$uploadValidation['errors'],
        ];

        if ($errors !== []) {
            return $this->renderForm(
                $request,
                $user,
                $values,
                $errors
            );
        }

        $id = $this->withOptionalImage(
            $validation['data'],
            $uploadValidation['upload'],
            (int) $user['id'],
            fn (array $data): int => $this->service->create(
                $data,
                (int) $user['id'],
                $request->ipAddress(),
                $request->userAgent()
            )
        );
        $this->session->put(
            '_flash_success',
            'Department created as a draft.'
        );

        return Response::redirect(
            $request->baseUrl() . 'admin/departments/' . $id
        );
    }

    public function edit(Request $request): Response
    {
        $user = $this->requireUser();
        $id = $this->routeId($request);
        $this->assertCanEdit((int) $user['id'], $id);
        $department = $this->departments->findAdmin($id, (int) $user['id']);

        if ($department === null) {
            throw new HttpException(404, 'Department not found.');
        }

        if ($department['status'] !== 'draft'
            && !($department['status'] === 'published'
                && $this->workflow->canPublishDraftDirectly(
                    (int) $user['id'],
                    $id
                ))
        ) {
            throw new HttpException(
                409,
                'This department must be a draft or published with publishing access before editing.'
            );
        }

        return $this->renderForm(
            $request,
            $user,
            $department,
            [],
            $id,
            '',
            (string) $department['status']
        );
    }

    public function update(Request $request): Response
    {
        $user = $this->requireUser();
        $id = $this->routeId($request);
        $this->assertCanEdit((int) $user['id'], $id);
        $department = $this->departments->findAdmin($id, (int) $user['id']);

        if ($department === null) {
            throw new HttpException(404, 'Department not found.');
        }

        $currentStatus = (string) $department['status'];

        if ($currentStatus !== 'draft'
            && !($currentStatus === 'published'
                && $this->workflow->canPublishDraftDirectly(
                    (int) $user['id'],
                    $id
                ))
        ) {
            throw new HttpException(
                409,
                'This department cannot be edited in its current status.'
            );
        }

        if (!$this->csrf->validate($request->input('_token'))) {
            return $this->csrfFailure($request);
        }

        $values = $this->formValues($request);
        $validation = $this->validator->validate($values, $id);
        $uploadValidation = $this->mediaUploads->validate(
            $request->file('hero_image'),
            (string) ($values['hero_alt_text'] ?? '')
        );
        $note = mb_substr(
            trim((string) $request->input('revision_note', '')),
            0,
            2000
        );

        $errors = [
            ...$validation['errors'],
            ...$uploadValidation['errors'],
        ];

        if ($errors !== []) {
            return $this->renderForm(
                $request,
                $user,
                $values,
                $errors,
                $id,
                $note,
                $currentStatus
            );
        }

        $publish = $request->input('submit_action') === 'publish';
        $this->withOptionalImage(
            $validation['data'],
            $uploadValidation['upload'],
            (int) $user['id'],
            function (array $data) use (
                $id,
                $user,
                $note,
                $request,
                $publish,
                $currentStatus
            ): void {
                if ($currentStatus === 'published') {
                    $this->service->updatePublished(
                        $id,
                        $data,
                        (int) $user['id'],
                        $note,
                        $request->ipAddress(),
                        $request->userAgent()
                    );

                    return;
                }

                if ($publish) {
                    $this->service->updateAndPublish(
                        $id,
                        $data,
                        (int) $user['id'],
                        $note,
                        'Saved and published directly.',
                        $request->ipAddress(),
                        $request->userAgent()
                    );

                    return;
                }

                $this->service->update(
                    $id,
                    $data,
                    (int) $user['id'],
                    $note,
                    $request->ipAddress(),
                    $request->userAgent()
                );
            }
        );
        $this->session->put(
            '_flash_success',
            $currentStatus === 'published'
                ? 'Published department updated.'
                : ($publish
                ? 'Department details saved and published.'
                : 'Department details updated.')
        );

        return Response::redirect(
            $request->baseUrl() . 'admin/departments/' . $id
        );
    }

    public function workflow(Request $request): Response
    {
        $user = $this->requireUser();
        $id = $this->routeId($request);

        if (!$this->csrf->validate($request->input('_token'))) {
            return $this->csrfFailure($request);
        }

        $action = mb_substr(
            trim((string) $request->input('action', '')),
            0,
            40
        );
        $comment = mb_substr(
            trim((string) $request->input('comment', '')),
            0,
            2000
        );

        if ($action === 'publish_now') {
            $this->service->publishDraft(
                $id,
                (int) $user['id'],
                $comment !== '' ? $comment : 'Published directly.',
                $request->ipAddress(),
                $request->userAgent()
            );
            $this->session->put(
                '_flash_success',
                'Department published successfully.'
            );

            return Response::redirect(
                $request->baseUrl() . 'admin/departments/' . $id
            );
        }

        $target = $this->service->transition(
            $id,
            $action,
            (int) $user['id'],
            $comment,
            $request->ipAddress(),
            $request->userAgent()
        );
        $this->session->put(
            '_flash_success',
            'Workflow status changed to '
            . str_replace('_', ' ', $target)
            . '.'
        );

        return Response::redirect(
            $request->baseUrl() . 'admin/departments/' . $id
        );
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $values
     * @param list<string> $errors
     */
    private function renderForm(
        Request $request,
        array $user,
        array $values = [],
        array $errors = [],
        ?int $id = null,
        string $revisionNote = '',
        string $status = 'draft'
    ): Response {
        $faculty = $this->departments->faculty();

        if ($faculty === null) {
            throw new HttpException(404, 'Faculty not found.');
        }

        if ($values === []) {
            $values = [
                'faculty_id' => (string) $faculty['id'],
                'display_order' => '0',
            ];
        }

        return $this->adminView(
            $request,
            $user,
            'admin/departments/form',
            [
                'pageTitle' => $id === null
                    ? 'Create department'
                    : 'Edit department',
                'departmentId' => $id,
                'values' => $values,
                'errors' => $errors,
                'faculty' => $faculty,
                'locations' => $this->departments->locations(),
                'images' => $this->departments->activeImages(),
                'revisionNote' => $revisionNote,
                'currentStatus' => $status,
                'canPublishDirectly' => $id !== null
                    && $this->workflow->canPublishDraftDirectly(
                        (int) $user['id'],
                        $id
                    ),
            ]
        );
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $data
     */
    private function adminView(
        Request $request,
        array $user,
        string $template,
        array $data
    ): Response {
        return $this->view(
            $template,
            array_merge($this->pageContext->data($request, $user), $data),
            200,
            'layouts/admin'
        )->withHeader('Cache-Control', 'no-store');
    }

    /**
     * @return array<string, mixed>
     */
    private function requireUser(): array
    {
        $user = $this->auth->user();

        if ($user === null) {
            throw new HttpException(403, 'Authentication required.');
        }

        return $user;
    }

    private function routeId(Request $request): int
    {
        $id = $this->positiveRouteInteger($request->route('id'), 0);

        if ($id < 1) {
            throw new HttpException(404, 'Department not found.');
        }

        return $id;
    }

    private function positiveRouteInteger(?string $value, int $default): int
    {
        if ($value === null || ctype_digit($value) === false) {
            return $default;
        }

        return max(0, (int) $value);
    }

    private function assertCanCreate(int $userId): void
    {
        if (!$this->authorization->can($userId, 'departments.create')
            || !$this->scope->hasGlobalScope($userId)
        ) {
            throw new HttpException(403, 'You cannot create departments.');
        }
    }

    private function assertCanEdit(int $userId, int $departmentId): void
    {
        if (!$this->authorization->can($userId, 'departments.edit')
            || !$this->scope->canAccess($userId, $departmentId, 'edit')
        ) {
            throw new HttpException(403, 'You cannot edit this department.');
        }
    }

    /**
     * @return array<string, string|null>
     */
    private function formValues(Request $request): array
    {
        $values = [];

        foreach (self::FORM_FIELDS as $field) {
            $values[$field] = $request->input($field);
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $upload
     */
    private function withOptionalImage(
        array $data,
        ?array $upload,
        int $userId,
        callable $operation
    ): mixed {
        if ($upload === null) {
            return $operation($data);
        }

        $connection = $this->departments->connection();
        $ownsTransaction = !$connection->inTransaction();
        $storedPath = null;

        if ($ownsTransaction) {
            $connection->beginTransaction();
        }

        try {
            $stored = $this->mediaUploads->store($upload, $userId);
            $storedPath = $stored['absolute_path'];
            $data['hero_media_id'] = $stored['id'];
            $result = $operation($data);

            if ($ownsTransaction) {
                $connection->commit();
            }

            return $result;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $connection->inTransaction()) {
                $connection->rollBack();
            }

            $this->mediaUploads->removeStoredFile($storedPath);
            throw $exception;
        }
    }

    private function csrfFailure(Request $request): Response
    {
        return $this->view(
            'errors/403',
            [
                'pageTitle' => 'Session expired',
                'metaDescription' => 'The secure form session expired.',
                'baseUrl' => $request->baseUrl(),
            ],
            403
        )->withHeader('Cache-Control', 'no-store');
    }
}
