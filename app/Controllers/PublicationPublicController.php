<?php

declare(strict_types=1);

namespace FastWebsite\Controllers;

use FastWebsite\Core\Controller;
use FastWebsite\Core\HttpException;
use FastWebsite\Core\Request;
use FastWebsite\Core\Response;
use FastWebsite\Repositories\PublicationRepository;
use FastWebsite\Repositories\ResearchMetadataRepository;

final class PublicationPublicController extends Controller
{
    private const ACCESS = ['open_access', 'subscription', 'restricted', 'unknown'];

    public function __construct(\FastWebsite\Core\View $view, private readonly PublicationRepository $publications, private readonly ResearchMetadataRepository $metadata)
    {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $type = mb_substr(trim((string) $request->query('type', '')), 0, 50);
        $types = $this->publications->types();
        $type = in_array($type, array_column($types, 'code'), true) ? $type : '';
        $access = (string) $request->query('access', '');
        $access = in_array($access, self::ACCESS, true) ? $access : '';
        $year = trim((string) $request->query('year', ''));
        $year = ctype_digit($year) && (int) $year >= 1800 && (int) $year <= (int) date('Y') + 2 ? $year : '';
        $page = ctype_digit((string) $request->query('page', '1')) ? max(1, (int) $request->query('page', '1')) : 1;
        return $this->view('public/publications/index', [
            'pageTitle'=>'Research publications',
            'metaDescription'=>'Explore FAST research publications.',
            'currentPath'=>$request->path(),
            'baseUrl'=>$request->baseUrl(),
            'search'=>$search,
            'typeFilter'=>$type,
            'accessFilter'=>$access,
            'yearFilter'=>$year,
            'types'=>$types,
            'result'=>$this->publications->paginatePublished($search, $type, $access, $year, $page),
        ]);
    }

    public function show(Request $request): Response
    {
        $slug = (string) $request->route('slug', '');
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            throw new HttpException(404, 'Publication not found.');
        }
        $publication = $this->publications->findPublishedBySlug($slug);
        if ($publication === null) {
            throw new HttpException(404, 'Publication not found.');
        }
        return $this->view('public/publications/show-metadata', [
            'pageTitle'=>$publication['title'],
            'metaDescription'=>mb_substr((string) ($publication['abstract'] ?? ''), 0, 300),
            'currentPath'=>$request->path(),
            'baseUrl'=>$request->baseUrl(),
            'publication'=>$publication,
            'authors'=>$this->publications->authors((int) $publication['id'], true),
            'projects'=>$this->publications->linkedProjects((int) $publication['id'], true),
            'units'=>$this->publications->linkedUnits((int) $publication['id'], true),
            'themes'=>$this->metadata->publicationThemes((int) $publication['id'], true),
        ]);
    }
}
