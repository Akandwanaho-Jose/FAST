<?php

declare(strict_types=1);

namespace FastWebsite\Controllers;

use FastWebsite\Core\Controller;
use FastWebsite\Core\Csrf;
use FastWebsite\Core\HttpException;
use FastWebsite\Core\Request;
use FastWebsite\Core\Response;
use FastWebsite\Core\Session;
use FastWebsite\Repositories\StaffRepository;
use FastWebsite\Services\AdminPageContext;
use FastWebsite\Services\AuthService;
use FastWebsite\Services\AuthorizationService;
use FastWebsite\Services\DepartmentScopeService;
use FastWebsite\Services\MediaUploadService;
use FastWebsite\Services\StaffService;
use FastWebsite\Validation\StaffValidator;
use Throwable;

final class StaffAdminController extends Controller
{
    private const STATUSES = ['draft', 'under_review', 'approved', 'published', 'archived'];
    private const FIELDS = [
        'faculty_id', 'department_id', 'position_id', 'title_override',
        'staff_number', 'honorific_title', 'first_name', 'middle_name',
        'last_name', 'post_nominals', 'slug', 'staff_category',
        'short_biography', 'biography', 'research_summary', 'teaching_summary',
        'supervision_interests', 'institutional_email', 'alternative_email',
        'public_phone', 'profile_media_id', 'profile_alt_text',
        'office_location_id', 'office_room', 'consultation_hours', 'supervision_available',
        'display_order',
    ];

    public function __construct(
        \FastWebsite\Core\View $view,
        private readonly AuthService $auth,
        private readonly AuthorizationService $authorization,
        private readonly DepartmentScopeService $scope,
        private readonly AdminPageContext $context,
        private readonly StaffRepository $staff,
        private readonly StaffValidator $validator,
        private readonly StaffService $service,
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
        $page = ctype_digit((string) $request->query('page', '1'))
            ? max(1, (int) $request->query('page', '1'))
            : 1;
        return $this->adminView($request, $user, 'admin/staff/index', [
            'pageTitle' => 'Staff',
            'result' => $this->staff->paginateAdmin((int) $user['id'], $search, $status, $page),
            'search' => $search,
            'statusFilter' => $status,
            'statuses' => self::STATUSES,
            'canCreate' => $this->service->canDirectPublish((int) $user['id'])
                || $this->can((int) $user['id'], 'staff.create'),
        ]);
    }

    public function show(Request $request): Response
    {
        $user = $this->user();
        $id = $this->id($request);
        $profile = $this->staff->findAdmin($id, (int) $user['id']);
        if ($profile === null) {
            throw new HttpException(404, 'Staff profile not found.');
        }
        return $this->adminView($request, $user, 'admin/staff/show', [
            'pageTitle' => $this->name($profile),
            'profile' => $profile,
            'history' => $this->staff->approvalHistory($id),
            'actions' => $this->service->actions((int) $user['id'], (string) $profile['status']),
            'canEdit' => ($profile['status'] === 'draft'
                    && $this->can((int) $user['id'], 'staff.edit'))
                || ($profile['status'] === 'published'
                    && $this->service->canDirectPublish((int) $user['id'])),
            'canQuickPublish' => $profile['status'] === 'draft'
                && trim((string) ($profile['short_biography'] ?? '')) !== ''
                && $this->service->canDirectPublish((int) $user['id']),
            'needsBiographyToPublish' => $profile['status'] === 'draft'
                && trim((string) ($profile['short_biography'] ?? '')) === ''
                && $this->service->canDirectPublish((int) $user['id']),
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $this->user();
        $this->assert((int) $user['id'], 'staff.create');
        return $this->form($request, $user);
    }

    public function store(Request $request): Response
    {
        $user = $this->user();
        $this->assert((int) $user['id'], 'staff.create');
        return $this->save($request, $user);
    }

    public function edit(Request $request): Response
    {
        $user = $this->user();
        $this->assert((int) $user['id'], 'staff.edit');
        $id = $this->id($request);
        $profile = $this->staff->findAdmin($id, (int) $user['id']);
        if ($profile === null) {
            throw new HttpException(404, 'Staff profile not found.');
        }
        if ($profile['status'] !== 'draft'
            && !($profile['status'] === 'published'
                && $this->service->canDirectPublish((int) $user['id']))
        ) {
            throw new HttpException(
                409,
                'This profile must be a draft or published with publishing access before editing.'
            );
        }
        return $this->form(
            $request,
            $user,
            $profile,
            [],
            $id,
            '',
            (string) $profile['status']
        );
    }

    public function update(Request $request): Response
    {
        $user = $this->user();
        $this->assert((int) $user['id'], 'staff.edit');
        return $this->save($request, $user, $this->id($request));
    }

    public function workflow(Request $request): Response
    {
        $user = $this->user();
        $id = $this->id($request);
        $this->csrf($request);
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
                'Staff profile published successfully.'
            );
            return Response::redirect(
                $request->baseUrl() . 'admin/staff/' . $id
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
        $this->session->put('_flash_success', 'Staff status changed to ' . str_replace('_', ' ', $target) . '.');
        return Response::redirect($request->baseUrl() . 'admin/staff/' . $id);
    }

    /** @param array<string,mixed> $user */
    private function save(Request $request, array $user, ?int $id = null): Response
    {
        $this->csrf($request);
        $currentStatus = 'draft';

        if ($id !== null) {
            $existing = $this->staff->findAdmin($id, (int) $user['id']);
            if ($existing === null) {
                throw new HttpException(404, 'Staff profile not found.');
            }
            $currentStatus = (string) $existing['status'];
        }

        $values = $this->values($request);
        $validation = $this->validator->validate($values, $id);
        $upload = $this->uploads->validate(
            $request->file('profile_image'),
            (string) ($values['profile_alt_text'] ?? '')
        );
        $errors = [...$validation['errors'], ...$upload['errors']];
        $note = mb_substr(trim((string) $request->input('revision_note', '')), 0, 2000);
        if ($errors !== []) {
            return $this->form(
                $request,
                $user,
                $values,
                $errors,
                $id,
                $note,
                $currentStatus
            );
        }

        $operation = function (array $data) use (
            $id,
            $validation,
            $user,
            $request,
            $note,
            $currentStatus
        ): int {
            if ($id === null) {
                return $request->input('submit_action') === 'publish'
                    ? $this->service->createAndPublish(
                        $data,
                        $validation['assignment'],
                        (int) $user['id'],
                        $request->ipAddress(),
                        $request->userAgent(),
                        $validation['relations']
                    )
                    : $this->service->create(
                        $data,
                        $validation['assignment'],
                        (int) $user['id'],
                        $request->ipAddress(),
                        $request->userAgent(),
                        $validation['relations']
                    );
            }
            if ($currentStatus === 'published') {
                $this->service->updatePublished(
                    $id,
                    $data,
                    $validation['assignment'],
                    (int) $user['id'],
                    $note,
                    $request->ipAddress(),
                    $request->userAgent(),
                    $validation['relations']
                );
                return $id;
            }
            if ($request->input('submit_action') === 'publish') {
                $this->service->updateAndPublish(
                    $id, $data, $validation['assignment'], (int) $user['id'],
                    $note, $request->ipAddress(), $request->userAgent(),
                    $validation['relations']
                );
            } else {
                $this->service->update(
                    $id, $data, $validation['assignment'], (int) $user['id'],
                    $note, $request->ipAddress(), $request->userAgent(),
                    $validation['relations']
                );
            }
            return $id;
        };
        $savedId = $this->withImage(
            $validation['data'],
            $upload['upload'],
            (int) $user['id'],
            $operation
        );
        $this->session->put(
            '_flash_success',
            $id === null
                ? ($request->input('submit_action') === 'publish'
                    ? 'Staff profile created and published.'
                    : 'Staff profile created as a draft.')
                : ($currentStatus === 'published'
                    ? 'Published staff profile updated.'
                    : ($request->input('submit_action') === 'publish'
                    ? 'Staff profile saved and published.'
                    : 'Staff profile updated.'))
        );
        return Response::redirect($request->baseUrl() . 'admin/staff/' . $savedId);
    }

    /** @param array<string,mixed> $user @param array<string,mixed> $values @param list<string> $errors */
    private function form(
        Request $request,
        array $user,
        array $values = [],
        array $errors = [],
        ?int $id = null,
        string $note = '',
        string $status = 'draft'
    ): Response {
        if ($values === []) {
            $faculty = $this->staff->connection()->query(
                'SELECT id FROM faculties WHERE deleted_at IS NULL ORDER BY id LIMIT 1'
            )->fetchColumn();
            $values = ['faculty_id' => (string) $faculty, 'staff_category' => 'academic', 'display_order' => '0'];
        }
        if ($id !== null && !array_key_exists('qualifications', $values)) {
            $values['qualifications'] = $this->staff->adminQualifications($id);
            $values['links'] = $this->staff->adminLinks($id);
            $values['expertise_ids'] = $this->staff->adminExpertiseIds($id);
        }
        return $this->adminView($request, $user, 'admin/staff/form', [
            'pageTitle' => $id === null ? 'Create staff profile' : 'Edit staff profile',
            'staffId' => $id,
            'values' => $values,
            'errors' => $errors,
            'departments' => $this->staff->departments(),
            'positions' => $this->staff->positions(),
            'locations' => $this->staff->locations(),
            'images' => $this->staff->activeImages(),
            'expertiseAreas' => $this->staff->expertiseAreas(),
            'revisionNote' => $note,
            'canPublishDirectly' => $this->service->canDirectPublish((int) $user['id']),
            'currentStatus' => $status,
        ]);
    }

    /** @param array<string,mixed> $user @param array<string,mixed> $data */
    private function adminView(Request $request, array $user, string $template, array $data): Response
    {
        return $this->view(
            $template,
            array_merge($this->context->data($request, $user), $data),
            200,
            'layouts/admin'
        )->withHeader('Cache-Control', 'no-store');
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
            throw new HttpException(404, 'Staff profile not found.');
        }
        return (int) $value;
    }

    /** @return array<string,mixed> */
    private function values(Request $request): array
    {
        $values = [];
        foreach (self::FIELDS as $field) {
            $values[$field] = $request->input($field);
        }
        $values['qualifications'] = $request->arrayInput('qualifications');
        $values['links'] = $request->arrayInput('links');
        $values['expertise_ids'] = $request->arrayInput('expertise_ids');
        return $values;
    }

    private function assert(int $userId, string $permission): void
    {
        if (!$this->can($userId, $permission)) {
            throw new HttpException(403, 'This staff action is not allowed.');
        }
    }

    private function can(int $userId, string $permission): bool
    {
        return $this->authorization->can($userId, $permission)
            && $this->scope->hasGlobalScope($userId);
    }

    private function csrf(Request $request): void
    {
        if (!$this->csrf->validate($request->input('_token'))) {
            throw new HttpException(403, 'The secure form session expired.');
        }
    }

    /** @param array<string,mixed> $profile */
    private function name(array $profile): string
    {
        return trim(implode(' ', array_filter([
            $profile['honorific_title'], $profile['first_name'],
            $profile['middle_name'], $profile['last_name'],
        ])));
    }

    /** @param array<string,mixed> $data @param array<string,mixed>|null $upload */
    private function withImage(array $data, ?array $upload, int $userId, callable $operation): mixed
    {
        if ($upload === null) {
            return $operation($data);
        }
        $connection = $this->staff->connection();
        $owns = !$connection->inTransaction();
        $path = null;
        if ($owns) {
            $connection->beginTransaction();
        }
        try {
            $stored = $this->uploads->store($upload, $userId, 'staff');
            $path = $stored['absolute_path'];
            $data['profile_media_id'] = $stored['id'];
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
