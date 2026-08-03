<?php

declare(strict_types=1);

namespace FastWebsite\Controllers;

use FastWebsite\Core\Controller;
use FastWebsite\Core\Csrf;
use FastWebsite\Core\HttpException;
use FastWebsite\Core\Request;
use FastWebsite\Core\Response;
use FastWebsite\Core\Session;
use FastWebsite\Repositories\CurriculumRepository;
use FastWebsite\Repositories\ProgrammeRepository;
use FastWebsite\Services\AdminPageContext;
use FastWebsite\Services\AuthService;
use FastWebsite\Services\CurriculumService;
use FastWebsite\Validation\CurriculumValidator;

final class CurriculumAdminController extends Controller
{
    public function __construct(
        \FastWebsite\Core\View $view,
        private readonly AuthService $auth,
        private readonly AdminPageContext $context,
        private readonly ProgrammeRepository $programmes,
        private readonly CurriculumRepository $curricula,
        private readonly CurriculumValidator $validator,
        private readonly CurriculumService $service,
        private readonly Csrf $csrf,
        private readonly Session $session
    ) {
        parent::__construct($view);
    }

    public function show(Request $request): Response
    {
        return $this->page($request, []);
    }

    public function save(Request $request): Response
    {
        $this->csrf($request);
        $input = [];
        foreach ([
            'version_id', 'placement_id', 'course_id', 'version_name',
            'effective_year', 'approval_reference', 'approval_date',
            'course_code', 'title', 'description', 'credit_units',
            'study_year', 'semester', 'requirement_type', 'display_order',
        ] as $field) {
            $input[$field] = $request->input($field);
        }
        $validation = $this->validator->validate($input);
        if ($validation['errors'] !== []) {
            return $this->page($request, $validation['errors'], $input);
        }
        $programmeId = $this->id($request, 'id', 'Programme');
        $this->service->savePlacement(
            $programmeId,
            $this->optionalId($input['version_id'] ?? null),
            $this->optionalId($input['placement_id'] ?? null),
            $this->optionalId($input['course_id'] ?? null),
            $validation['version'],
            $validation['course'],
            $validation['placement'],
            (int) $this->user()['id'],
            $request->ipAddress(),
            $request->userAgent()
        );
        $this->session->put('_flash_success', ($input['placement_id'] ?? '') !== '' ? 'Curriculum course updated.' : 'Course added to the curriculum.');
        return Response::redirect($request->baseUrl() . 'admin/programmes/' . $programmeId . '/curriculum');
    }

    public function delete(Request $request): Response
    {
        $this->csrf($request);
        $programmeId = $this->id($request, 'id', 'Programme');
        $versionId = $this->id($request, 'versionId', 'Curriculum version');
        $placementId = $this->id($request, 'placementId', 'Course placement');
        $this->service->deletePlacement($programmeId, $versionId, $placementId, (int) $this->user()['id'], $request->ipAddress(), $request->userAgent());
        $this->session->put('_flash_success', 'Course removed from the curriculum.');
        return Response::redirect($request->baseUrl() . 'admin/programmes/' . $programmeId . '/curriculum');
    }

    public function publish(Request $request): Response
    {
        $this->csrf($request);
        $programmeId = $this->id($request, 'id', 'Programme');
        $versionId = $this->id($request, 'versionId', 'Curriculum version');
        $this->service->publish($programmeId, $versionId, (int) $this->user()['id'], $request->ipAddress(), $request->userAgent());
        $this->session->put('_flash_success', 'Curriculum published and visible on the programme page.');
        return Response::redirect($request->baseUrl() . 'admin/programmes/' . $programmeId . '/curriculum');
    }

    /** @param list<string> $errors @param array<string,mixed> $values */
    private function page(Request $request, array $errors, array $values = []): Response
    {
        $user = $this->user();
        $programmeId = $this->id($request, 'id', 'Programme');
        $programme = $this->programmes->findAdmin($programmeId, (int) $user['id']);
        if ($programme === null) {
            throw new HttpException(404, 'Programme not found.');
        }
        $version = $this->curricula->versionForAdmin($programmeId);
        $placements = $version === null ? [] : $this->curricula->placements((int) $version['id']);
        $editId = $this->optionalId($request->query('edit'));
        $editing = $editId === null ? null : current(array_filter(
            $placements,
            static fn (array $row): bool => (int) $row['placement_id'] === $editId
        ));
        if ($editing === false) {
            $editing = null;
        }
        return $this->view('admin/programmes/curriculum', array_merge($this->context->data($request, $user), [
            'pageTitle' => 'Curriculum · ' . $programme['name'],
            'programme' => $programme,
            'version' => $version,
            'placements' => $placements,
            'errors' => $errors,
            'values' => $values,
            'editing' => $editing,
            'canPublish' => $this->service->canPublish($programmeId, (int) $user['id']),
        ]), 200, 'layouts/admin')->withHeader('Cache-Control', 'no-store');
    }

    /** @return array<string,mixed> */
    private function user(): array
    {
        return $this->auth->user() ?? throw new HttpException(403, 'Authentication required.');
    }

    private function id(Request $request, string $key, string $label): int
    {
        $value = (string) $request->route($key, '');
        if (!ctype_digit($value) || (int) $value < 1) {
            throw new HttpException(404, $label . ' not found.');
        }
        return (int) $value;
    }

    private function optionalId(mixed $value): ?int
    {
        $value = trim((string) $value);
        return ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function csrf(Request $request): void
    {
        if (!$this->csrf->validate($request->input('_token'))) {
            throw new HttpException(403, 'The secure form session expired.');
        }
    }
}
