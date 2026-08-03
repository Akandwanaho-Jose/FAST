<?php

declare(strict_types=1);

namespace FastWebsite\Controllers;

use FastWebsite\Core\Controller;
use FastWebsite\Core\HttpException;
use FastWebsite\Core\Request;
use FastWebsite\Core\Response;
use FastWebsite\Repositories\ProjectRepository;
use FastWebsite\Repositories\ResearchMetadataRepository;

final class ProjectPublicController extends Controller
{
    private const STATUSES = ['planned', 'ongoing', 'completed', 'suspended', 'cancelled'];

    public function __construct(\FastWebsite\Core\View $view, private readonly ProjectRepository $projects, private readonly ResearchMetadataRepository $metadata)
    {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $status = (string) $request->query('status', '');
        $status = in_array($status, self::STATUSES, true) ? $status : '';
        $page = ctype_digit((string) $request->query('page', '1')) ? max(1, (int) $request->query('page', '1')) : 1;
        return $this->view('public/projects/index', [
            'pageTitle'=>'Research projects',
            'metaDescription'=>'Explore FAST research and innovation projects.',
            'currentPath'=>$request->path(),
            'baseUrl'=>$request->baseUrl(),
            'search'=>$search,
            'statusFilter'=>$status,
            'statuses'=>self::STATUSES,
            'result'=>$this->projects->paginatePublished($search, $status, $page),
        ]);
    }

    public function show(Request $request): Response
    {
        $slug = (string) $request->route('slug', '');
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            throw new HttpException(404, 'Project not found.');
        }
        $project = $this->projects->findPublishedBySlug($slug);
        if ($project === null) {
            throw new HttpException(404, 'Project not found.');
        }
        return $this->view('public/projects/show-metadata', [
            'pageTitle'=>$project['title'],
            'metaDescription'=>mb_substr((string) ($project['summary'] ?? ''), 0, 300),
            'currentPath'=>$request->path(),
            'baseUrl'=>$request->baseUrl(),
            'project'=>$project,
            'units'=>$this->projects->linkedUnits((int) $project['id'], true),
            'members'=>$this->projects->members((int) $project['id'], true),
            'themes'=>$this->metadata->projectThemes((int) $project['id'], true),
            'sdgs'=>$this->metadata->projectSdgs((int) $project['id']),
            'partners'=>$this->metadata->projectPartners((int) $project['id'], true),
        ]);
    }
}
