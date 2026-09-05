<?php

declare(strict_types=1);

namespace FastWebsite\Controllers;

use FastWebsite\Core\Controller;
use FastWebsite\Core\Csrf;
use FastWebsite\Core\HttpException;
use FastWebsite\Core\Request;
use FastWebsite\Core\Response;
use FastWebsite\Core\Session;
use FastWebsite\Repositories\PublicationRepository;
use FastWebsite\Repositories\StaffRepository;
use FastWebsite\Services\AdminPageContext;
use FastWebsite\Services\AuthService;
use FastWebsite\Services\MediaUploadService;
use FastWebsite\Services\PublicationService;
use FastWebsite\Services\StaffService;
use FastWebsite\Validation\PublicationValidator;
use FastWebsite\Validation\StaffValidator;
use Throwable;

/**
 * Staff self-service: lets a logged-in staff member edit their own profile
 * and manage their own publications. Authorization here is ownership
 * (staff.user_id = the logged-in user), not RBAC permissions - this is
 * deliberately a separate, narrower controller from StaffAdminController /
 * PublicationAdminController rather than those with extra branching, so a
 * staff account can never reach an admin-only capability by a missed check.
 */
final class StaffSelfServiceController extends Controller
{
    private const PROFILE_FIELDS = [
        'short_biography', 'biography', 'research_summary', 'teaching_summary',
        'supervision_interests', 'alternative_email', 'public_phone',
        'office_room', 'consultation_hours', 'supervision_available',
    ];

    private const PUBLICATION_FIELDS = [
        'publication_type_id', 'title', 'slug', 'abstract', 'journal_name',
        'publisher', 'publication_year', 'publication_date', 'volume',
        'issue', 'page_range', 'doi', 'isbn', 'external_url', 'access_type',
        'citation_text', 'project_id', 'research_unit_id',
    ];

    public function __construct(
        \FastWebsite\Core\View $view,
        private readonly AuthService $auth,
        private readonly AdminPageContext $context,
        private readonly StaffRepository $staff,
        private readonly StaffValidator $staffValidator,
        private readonly StaffService $staffService,
        private readonly MediaUploadService $uploads,
        private readonly PublicationRepository $publications,
        private readonly PublicationValidator $publicationValidator,
        private readonly PublicationService $publicationService,
        private readonly Csrf $csrf,
        private readonly Session $session
    ) {
        parent::__construct($view);
    }

    public function profile(Request $request): Response
    {
        $profile = $this->ownProfile($request);
        return $this->form($request, $profile, [], '');
    }

    public function updateProfile(Request $request): Response
    {
        $this->csrf($request);
        $profile = $this->ownProfile($request);

        $values = [];
        foreach (self::PROFILE_FIELDS as $field) {
            $values[$field] = $request->input($field);
        }
        $values['profile_alt_text'] = $request->input('profile_alt_text');

        $errors = [];
        $relations = $this->staffValidator->relations(
            [
                'qualifications' => $request->arrayInput('qualifications'),
                'links' => $request->arrayInput('links'),
                'expertise_ids' => $request->arrayInput('expertise_ids'),
            ],
            $errors
        );
        $errors = [...$errors, ...$this->validateProfileValues($values)];

        $upload = $this->uploads->validate(
            $request->file('profile_image'),
            (string) ($values['profile_alt_text'] ?? '')
        );
        $errors = [...$errors, ...$upload['errors']];

        if ($errors !== []) {
            return $this->form($request, [...$profile, ...$values, ...$relations], $errors, '');
        }

        $data = $this->buildData($values);
        $user = $this->user();
        $note = mb_substr(trim((string) $request->input('revision_note', '')), 0, 2000);

        $operation = function (array $data) use ($profile, $user, $note, $request, $relations): void {
            $this->staffService->updateOwnProfile(
                (int) $profile['id'],
                $data,
                (int) $user['id'],
                $note,
                $request->ipAddress(),
                $request->userAgent(),
                $relations
            );
        };

        try {
            $this->withImage($data, $upload['file'] ?? null, (int) $user['id'], $operation);
        } catch (Throwable $exception) {
            $message = $exception instanceof HttpException
                ? $exception->getMessage()
                : 'Your profile could not be saved.';
            return $this->form($request, [...$profile, ...$values, ...$relations], [$message], '');
        }

        $this->session->put('_flash_success', 'Your profile has been updated.');
        return Response::redirect($request->baseUrl() . 'my-profile');
    }

    public function publications(Request $request): Response
    {
        $staffId = $this->ownStaffId();
        return $this->selfView($request, 'staff-self-service/publications', [
            'pageTitle' => 'My publications',
            'items' => $this->publications->myPublications($staffId),
        ]);
    }

    public function createPublicationForm(Request $request): Response
    {
        return $this->publicationForm($request, [], [], '');
    }

    public function storePublication(Request $request): Response
    {
        $this->csrf($request);
        $staffId = $this->ownStaffId();
        $user = $this->user();

        $values = $this->publicationValues($request);
        $validation = $this->publicationValidator->validate($values);
        if ($validation['errors'] !== []) {
            return $this->publicationForm($request, $values, $validation['errors'], '');
        }

        $id = $this->publicationService->createOwn(
            $validation['data'],
            $validation['project_id'],
            $validation['research_unit_id'],
            $staffId,
            (int) $user['id'],
            $request->ipAddress(),
            $request->userAgent()
        );

        $this->session->put('_flash_success', 'Publication added.');
        return Response::redirect($request->baseUrl() . 'my-publications/' . $id . '/edit');
    }

    public function editPublicationForm(Request $request): Response
    {
        $staffId = $this->ownStaffId();
        $id = $this->publicationId($request);
        $publication = $this->publications->findOwn($id, $staffId);
        if ($publication === null) {
            throw new HttpException(404, 'Publication not found.');
        }
        $values = $publication;
        $values['project_id'] = $this->publications->linkedProjects($id)[0]['id'] ?? '';
        $values['research_unit_id'] = $this->publications->linkedUnits($id)[0]['id'] ?? '';
        return $this->publicationForm($request, $values, [], '', $id);
    }

    public function updatePublication(Request $request): Response
    {
        $this->csrf($request);
        $staffId = $this->ownStaffId();
        $user = $this->user();
        $id = $this->publicationId($request);

        $values = $this->publicationValues($request);
        $validation = $this->publicationValidator->validate($values, $id);
        if ($validation['errors'] !== []) {
            return $this->publicationForm($request, $values, $validation['errors'], '', $id);
        }

        $note = mb_substr(trim((string) $request->input('revision_note', '')), 0, 2000);

        try {
            $this->publicationService->updateOwn(
                $id,
                $validation['data'],
                $validation['project_id'],
                $validation['research_unit_id'],
                $staffId,
                (int) $user['id'],
                $note,
                $request->ipAddress(),
                $request->userAgent()
            );
        } catch (Throwable $exception) {
            $message = $exception instanceof HttpException
                ? $exception->getMessage()
                : 'This publication could not be saved.';
            return $this->publicationForm($request, $values, [$message], '', $id);
        }

        $this->session->put('_flash_success', 'Publication updated.');
        return Response::redirect($request->baseUrl() . 'my-publications/' . $id . '/edit');
    }

    public function addAuthor(Request $request): Response
    {
        $this->csrf($request);
        $staffId = $this->ownStaffId();
        $user = $this->user();
        $id = $this->publicationId($request);

        $coAuthor = trim((string) $request->input('staff_id', ''));
        $coAuthorId = ctype_digit($coAuthor) && (int) $coAuthor > 0 ? (int) $coAuthor : null;

        $this->publicationService->addOwnAuthor(
            $id,
            $staffId,
            $coAuthorId,
            (string) $request->input('external_author_name', ''),
            (string) $request->input('external_affiliation', ''),
            (string) $request->input('external_orcid', ''),
            (int) $request->input('author_order', '1'),
            $request->input('is_corresponding') === '1',
            (int) $user['id'],
            $request->ipAddress(),
            $request->userAgent()
        );

        $this->session->put('_flash_success', 'Author added.');
        return Response::redirect($request->baseUrl() . 'my-publications/' . $id . '/edit#authors');
    }

    public function removeAuthor(Request $request): Response
    {
        $this->csrf($request);
        $staffId = $this->ownStaffId();
        $user = $this->user();
        $id = $this->publicationId($request);
        $authorId = (string) $request->route('authorId', '');
        if (!ctype_digit($authorId) || (int) $authorId < 1) {
            throw new HttpException(404, 'Publication author not found.');
        }

        $this->publicationService->removeOwnAuthor(
            $id,
            $staffId,
            (int) $authorId,
            (int) $user['id'],
            $request->ipAddress(),
            $request->userAgent()
        );

        $this->session->put('_flash_success', 'Author removed.');
        return Response::redirect($request->baseUrl() . 'my-publications/' . $id . '/edit#authors');
    }

    /** @return array<string,mixed> */
    private function publicationValues(Request $request): array
    {
        $values = [];
        foreach (self::PUBLICATION_FIELDS as $field) {
            $values[$field] = $request->input($field);
        }
        return $values;
    }

    /** @return array<string,mixed> */
    private function ownProfile(Request $request): array
    {
        $staffId = $this->ownStaffId();
        $profile = $this->staff->findOwn($staffId, (int) $this->user()['id']);
        if ($profile === null) {
            throw new HttpException(404, 'Staff profile not found.');
        }
        $profile['qualifications'] = $this->staff->adminQualifications($staffId);
        $profile['links'] = $this->staff->adminLinks($staffId);
        $profile['expertise_ids'] = $this->staff->adminExpertiseIds($staffId);
        return $profile;
    }

    private function ownStaffId(): int
    {
        $staffId = $this->staff->staffIdForUser((int) $this->user()['id']);
        if ($staffId === null) {
            throw new HttpException(403, 'This account is not linked to a staff profile.');
        }
        return $staffId;
    }

    private function publicationId(Request $request): int
    {
        $value = (string) $request->route('id', '');
        if (!ctype_digit($value) || (int) $value < 1) {
            throw new HttpException(404, 'Publication not found.');
        }
        return (int) $value;
    }

    /** @param array<string,mixed> $profile @param list<string> $errors */
    private function form(Request $request, array $profile, array $errors, string $note): Response
    {
        return $this->selfView($request, 'staff-self-service/profile', [
            'pageTitle' => 'My profile',
            'profile' => $profile,
            'errors' => $errors,
            'note' => $note,
            'expertiseAreas' => $this->staff->expertiseAreas(),
        ]);
    }

    /** @param array<string,mixed> $values @param list<string> $errors */
    private function publicationForm(
        Request $request,
        array $values,
        array $errors,
        string $note,
        ?int $id = null
    ): Response {
        $data = [
            'pageTitle' => $id === null ? 'Add publication' : 'Edit publication',
            'values' => $values,
            'errors' => $errors,
            'note' => $note,
            'id' => $id,
            'types' => $this->publications->types(),
            'staffOptions' => $this->publications->allPublishedStaff(),
        ];
        if ($id !== null) {
            $data['authors'] = $this->publications->authors($id);
        }
        return $this->selfView($request, 'staff-self-service/publication-form', $data);
    }

    /** @param array<string,mixed> $data */
    private function selfView(Request $request, string $template, array $data): Response
    {
        $user = $this->user();
        return $this->view(
            $template,
            array_merge(
                $this->context->data($request, $user),
                [
                    'adminNavigation' => $this->selfServiceNav($request),
                    'scopeLabel' => 'Your account',
                    'roles' => [['name' => 'Staff']],
                ],
                $data
            ),
            200,
            'layouts/admin'
        )->withHeader('Cache-Control', 'no-store');
    }

    /** @return list<array{key:string,label:string,route:string,active:bool}> */
    private function selfServiceNav(Request $request): array
    {
        $path = $request->path();
        return [
            ['key' => 'my-profile', 'label' => 'My profile', 'route' => 'my-profile', 'active' => $path === '/my-profile'],
            ['key' => 'my-publications', 'label' => 'My publications', 'route' => 'my-publications', 'active' => str_starts_with($path, '/my-publications')],
        ];
    }

    /** @param array<string,mixed> $values @return list<string> */
    private function validateProfileValues(array $values): array
    {
        $errors = [];
        $limits = [
            'short_biography' => 65535,
            'public_phone' => 50,
            'office_room' => 120,
            'consultation_hours' => 255,
        ];
        foreach ($limits as $field => $max) {
            $value = trim((string) ($values[$field] ?? ''));
            if (mb_strlen($value) > $max) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is too long.';
            }
        }
        $altEmail = trim((string) ($values['alternative_email'] ?? ''));
        if ($altEmail !== '' && (strlen($altEmail) > 190 || filter_var($altEmail, FILTER_VALIDATE_EMAIL) === false)) {
            $errors[] = 'Enter a valid alternative email address.';
        }
        return $errors;
    }

    /** @param array<string,mixed> $values @return array<string,mixed> */
    private function buildData(array $values): array
    {
        $data = [];
        foreach (self::PROFILE_FIELDS as $field) {
            $value = $values[$field] ?? null;
            if ($field === 'supervision_available') {
                $data[$field] = $value === '1' || $value === 1 ? 1 : 0;
                continue;
            }
            $trimmed = is_string($value) ? trim($value) : $value;
            $data[$field] = $trimmed === '' ? null : $trimmed;
        }
        return $data;
    }

    /** @return array<string,mixed> */
    private function user(): array
    {
        return $this->auth->user() ?? throw new HttpException(403, 'Authentication required.');
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
