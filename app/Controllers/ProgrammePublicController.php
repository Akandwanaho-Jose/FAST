<?php

declare(strict_types=1);

namespace FastWebsite\Controllers;

use FastWebsite\Core\Controller;
use FastWebsite\Core\HttpException;
use FastWebsite\Core\Request;
use FastWebsite\Core\Response;
use FastWebsite\Repositories\ProgrammeRepository;
use FastWebsite\Repositories\CurriculumRepository;

final class ProgrammePublicController extends Controller
{
    public function __construct(
        \FastWebsite\Core\View $view,
        private readonly ProgrammeRepository $programmes,
        private readonly CurriculumRepository $curricula
    )
    {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $category = mb_substr(trim((string) $request->query('category', '')), 0, 30);
        $category = in_array($category, ['undergraduate', 'postgraduate'], true) ? $category : '';
        $departments = $this->programmes->publicDepartments();
        $department = mb_substr(trim((string) $request->query('department', '')), 0, 100);
        $validDepartments = array_column($departments, 'slug');
        $department = in_array($department, $validDepartments, true) ? $department : '';
        $page = ctype_digit((string) $request->query('page', '1')) ? max(1, (int) $request->query('page', '1')) : 1;
        return $this->view('public/programmes/index', [
            'pageTitle' => 'Academic programmes',
            'metaDescription' => 'Explore published FAST academic programmes.',
            'currentPath' => $request->path(),
            'baseUrl' => $request->baseUrl(),
            'search' => $search,
            'categoryFilter' => $category,
            'departmentFilter' => $department,
            'departments' => $departments,
            'categoryCounts' => $this->programmes->publicCategoryCounts(),
            'result' => $this->programmes->paginatePublished($search, $category, $department, $page),
        ]);
    }

    public function show(Request $request): Response
    {
        $slug = (string) $request->route('slug', '');
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            throw new HttpException(404, 'Programme not found.');
        }
        $programme = $this->programmes->findPublishedBySlug($slug);
        if ($programme === null) {
            throw new HttpException(404, 'Programme not found.');
        }
        $curriculum = $this->curricula->publishedVersion((int) $programme['id']);
        return $this->view('public/programmes/show', [
            'pageTitle' => $programme['name'],
            'metaDescription' => mb_substr((string) ($programme['overview'] ?? ''), 0, 300),
            'currentPath' => $request->path(),
            'baseUrl' => $request->baseUrl(),
            'programme' => $programme,
            'curriculum' => $curriculum,
            'curriculumCourses' => $curriculum === null ? [] : $this->curricula->placements((int) $curriculum['id'], true),
        ]);
    }
}
