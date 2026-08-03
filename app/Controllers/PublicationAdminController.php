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
use FastWebsite\Repositories\ResearchMetadataRepository;
use FastWebsite\Services\AdminPageContext;
use FastWebsite\Services\AuthService;
use FastWebsite\Services\AuthorizationService;
use FastWebsite\Services\PublicationService;
use FastWebsite\Validation\PublicationValidator;

final class PublicationAdminController extends Controller
{
    private const STATUSES = ['draft', 'under_review', 'approved', 'published', 'archived'];
    private const FIELDS = ['publication_type_id', 'title', 'slug', 'abstract', 'journal_name', 'publisher', 'publication_year', 'publication_date', 'volume', 'issue', 'page_range', 'doi', 'isbn', 'external_url', 'access_type', 'citation_text', 'project_id', 'research_unit_id'];

    public function __construct(
        \FastWebsite\Core\View $view,
        private readonly AuthService $auth,
        private readonly AuthorizationService $authorization,
        private readonly AdminPageContext $context,
        private readonly PublicationRepository $publications,
        private readonly ResearchMetadataRepository $metadata,
        private readonly PublicationValidator $validator,
        private readonly PublicationService $service,
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
        $type = mb_substr(trim((string) $request->query('type', '')), 0, 50);
        $types = $this->publications->types();
        $type = in_array($type, array_column($types, 'code'), true) ? $type : '';
        $page = ctype_digit((string) $request->query('page', '1')) ? max(1, (int) $request->query('page', '1')) : 1;
        return $this->adminView($request, $user, 'admin/publications/index', [
            'pageTitle'=>'Research publications',
            'result'=>$this->publications->paginateAdmin((int) $user['id'], $search, $status, $type, $page),
            'search'=>$search, 'statusFilter'=>$status, 'typeFilter'=>$type,
            'statuses'=>self::STATUSES, 'types'=>$types,
            'canCreate'=>$this->service->canCreate((int) $user['id']),
        ]);
    }

    public function show(Request $request): Response
    {
        $user = $this->user();
        $id = $this->id($request);
        $publication = $this->publications->findAdmin($id, (int) $user['id']);
        if ($publication === null) {
            throw new HttpException(404, 'Publication not found.');
        }
        $authors = $this->publications->authors($id);
        $canPublish = $this->service->canDirectPublish((int) $user['id'], $id);
        return $this->adminView($request, $user, 'admin/publications/show-metadata', [
            'pageTitle'=>$publication['title'], 'publication'=>$publication, 'authors'=>$authors,
            'projects'=>$this->publications->linkedProjects($id), 'units'=>$this->publications->linkedUnits($id),
            'staffOptions'=>$this->publications->availableStaff((int) $user['id']),
            'history'=>$this->publications->approvalHistory($id),
            'actions'=>$this->service->actions((int) $user['id'], $id, (string) $publication['status']),
            'canEdit'=>($publication['status'] === 'draft' && $this->authorization->can((int) $user['id'], 'research.edit')) || ($publication['status'] === 'published' && $canPublish),
            'canManageAuthors'=>$this->authorization->can((int) $user['id'], 'research.edit') && in_array($publication['status'], ['draft', 'published'], true),
            'canManageThemes'=>$this->authorization->can((int) $user['id'], 'research.edit'),
            'themes'=>$this->metadata->publicationThemes($id),
            'themeOptions'=>$this->metadata->themes((int) $user['id'], true),
            'canQuickPublish'=>$publication['status'] === 'draft' && $authors !== [] && $canPublish,
            'needsAuthor'=>$publication['status'] === 'draft' && $authors === [] && $canPublish,
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $this->user(); $this->assert((int) $user['id'], 'research.create');
        return $this->form($request, $user);
    }

    public function store(Request $request): Response
    {
        $user = $this->user(); $this->assert((int) $user['id'], 'research.create');
        return $this->save($request, $user);
    }

    public function edit(Request $request): Response
    {
        $user = $this->user(); $this->assert((int) $user['id'], 'research.edit');
        $id = $this->id($request);
        $publication = $this->publications->findAdmin($id, (int) $user['id']);
        if ($publication === null) throw new HttpException(404, 'Publication not found.');
        if ($publication['status'] !== 'draft' && !($publication['status'] === 'published' && $this->service->canDirectPublish((int) $user['id'], $id))) {
            throw new HttpException(409, 'This publication must be draft or published with publishing access before editing.');
        }
        $values = $publication;
        $values['project_id'] = $this->publications->linkedProjects($id)[0]['id'] ?? '';
        $values['research_unit_id'] = $this->publications->linkedUnits($id)[0]['id'] ?? '';
        return $this->form($request, $user, $values, [], $id, '', (string) $publication['status']);
    }

    public function update(Request $request): Response
    {
        $user = $this->user(); $this->assert((int) $user['id'], 'research.edit');
        return $this->save($request, $user, $this->id($request));
    }

    public function workflow(Request $request): Response
    {
        $user = $this->user(); $id = $this->id($request); $this->csrf($request);
        $action = mb_substr(trim((string) $request->input('action', '')), 0, 40);
        $comment = mb_substr(trim((string) $request->input('comment', '')), 0, 2000);
        if ($action === 'publish_now') {
            $this->service->publishDraft($id, (int) $user['id'], $comment !== '' ? $comment : 'Published directly.', $request->ipAddress(), $request->userAgent());
            $this->session->put('_flash_success', 'Publication published successfully.');
        } else {
            $target = $this->service->transition($id, $action, (int) $user['id'], $comment, $request->ipAddress(), $request->userAgent());
            $this->session->put('_flash_success', 'Publication status changed to ' . str_replace('_', ' ', $target) . '.');
        }
        return Response::redirect($request->baseUrl() . 'admin/research/publications/' . $id);
    }

    public function addAuthor(Request $request): Response
    {
        $this->csrf($request); $user = $this->user(); $id = $this->id($request);
        $staff = trim((string) $request->input('staff_id', ''));
        $staffId = ctype_digit($staff) && (int) $staff > 0 ? (int) $staff : null;
        $this->service->addAuthor($id, $staffId, (string) $request->input('external_author_name', ''), (string) $request->input('external_affiliation', ''), (string) $request->input('external_orcid', ''), (int) $request->input('author_order', '1'), $request->input('is_corresponding') === '1', (int) $user['id'], $request->ipAddress(), $request->userAgent());
        $this->session->put('_flash_success', 'Publication author added.');
        return Response::redirect($request->baseUrl() . 'admin/research/publications/' . $id . '#authors');
    }

    public function removeAuthor(Request $request): Response
    {
        $this->csrf($request); $user = $this->user(); $id = $this->id($request);
        $author = (string) $request->route('authorId', '');
        if (!ctype_digit($author) || (int) $author < 1) throw new HttpException(404, 'Publication author not found.');
        $this->service->removeAuthor($id, (int) $author, (int) $user['id'], $request->ipAddress(), $request->userAgent());
        $this->session->put('_flash_success', 'Publication author removed.');
        return Response::redirect($request->baseUrl() . 'admin/research/publications/' . $id . '#authors');
    }

    /** @param array<string,mixed> $user */
    private function save(Request $request, array $user, ?int $id = null): Response
    {
        $this->csrf($request); $status = 'draft';
        if ($id !== null) {
            $existing = $this->publications->findAdmin($id, (int) $user['id']);
            if ($existing === null) throw new HttpException(404, 'Publication not found.');
            $status = (string) $existing['status'];
        }
        $values = [];
        foreach (self::FIELDS as $field) $values[$field] = $request->input($field);
        $validation = $this->validator->validate($values, $id);
        $note = mb_substr(trim((string) $request->input('revision_note', '')), 0, 2000);
        if ($validation['errors'] !== []) return $this->form($request, $user, $values, $validation['errors'], $id, $note, $status);
        if ($id === null) {
            $saved = $this->service->create($validation['data'], $validation['project_id'], $validation['research_unit_id'], (int) $user['id'], $request->ipAddress(), $request->userAgent());
            $message = 'Publication created as a draft. Add its authors, then publish.';
        } else {
            if ($status === 'published') $this->service->updatePublished($id, $validation['data'], $validation['project_id'], $validation['research_unit_id'], (int) $user['id'], $note, $request->ipAddress(), $request->userAgent());
            elseif ($request->input('submit_action') === 'publish') $this->service->updateAndPublish($id, $validation['data'], $validation['project_id'], $validation['research_unit_id'], (int) $user['id'], $note, $request->ipAddress(), $request->userAgent());
            else $this->service->update($id, $validation['data'], $validation['project_id'], $validation['research_unit_id'], (int) $user['id'], $note, $request->ipAddress(), $request->userAgent());
            $saved = $id;
            $message = $status === 'published' ? 'Published publication updated.' : ($request->input('submit_action') === 'publish' ? 'Publication saved and published.' : 'Publication updated.');
        }
        $this->session->put('_flash_success', $message);
        return Response::redirect($request->baseUrl() . 'admin/research/publications/' . $saved);
    }

    /** @param array<string,mixed> $user @param array<string,mixed> $values @param list<string> $errors */
    private function form(Request $request, array $user, array $values = [], array $errors = [], ?int $id = null, string $note = '', string $status = 'draft'): Response
    {
        if ($values === []) $values = ['publication_type_id'=>(string) ($this->publications->types()[0]['id'] ?? ''), 'access_type'=>'unknown', 'publication_year'=>(string) date('Y')];
        return $this->adminView($request, $user, 'admin/publications/form', [
            'pageTitle'=>$id === null ? 'Create publication' : 'Edit publication', 'publicationId'=>$id,
            'values'=>$values, 'errors'=>$errors, 'types'=>$this->publications->types(),
            'projects'=>$this->publications->projects((int) $user['id']), 'researchUnits'=>$this->publications->researchUnits((int) $user['id']),
            'revisionNote'=>$note, 'currentStatus'=>$status,
            'canPublishDirectly'=>$id !== null && $this->service->canDirectPublish((int) $user['id'], $id) && $this->publications->authors($id) !== [],
        ]);
    }

    /** @param array<string,mixed> $user @param array<string,mixed> $data */
    private function adminView(Request $request, array $user, string $template, array $data): Response
    {
        return $this->view($template, array_merge($this->context->data($request, $user), $data), 200, 'layouts/admin')->withHeader('Cache-Control', 'no-store');
    }

    private function user(): array { return $this->auth->user() ?? throw new HttpException(403, 'Authentication required.'); }
    private function id(Request $request): int { $value=(string)$request->route('id',''); if(!ctype_digit($value)||(int)$value<1)throw new HttpException(404,'Publication not found.'); return (int)$value; }
    private function assert(int $userId, string $permission): void { if(!$this->authorization->can($userId,$permission))throw new HttpException(403,'This publication action is not allowed.'); }
    private function csrf(Request $request): void { if(!$this->csrf->validate($request->input('_token')))throw new HttpException(403,'The secure form session expired.'); }
}
