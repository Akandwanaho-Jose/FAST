<?php

declare(strict_types=1);

namespace FastWebsite\Controllers;

use FastWebsite\Core\Controller;
use FastWebsite\Core\HttpException;
use FastWebsite\Core\Request;
use FastWebsite\Core\Response;
use FastWebsite\Repositories\DepartmentRepository;

final class DepartmentPublicController extends Controller
{
    public function __construct(
        \FastWebsite\Core\View $view,
        private readonly DepartmentRepository $departments
    ) {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $pageValue = (string) $request->query('page', '1');
        $page = ctype_digit($pageValue) ? max(1, (int) $pageValue) : 1;

        return $this->view(
            'public/departments/index',
            [
                'pageTitle' => 'Departments',
                'metaDescription' => 'Published departments in the Faculty of Applied Sciences and Technology.',
                'currentPath' => $request->path(),
                'baseUrl' => $request->baseUrl(),
                'search' => $search,
                'result' => $this->departments->paginatePublished(
                    $search,
                    $page
                ),
            ]
        );
    }

    public function show(Request $request): Response
    {
        $slug = (string) $request->route('slug', '');

        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            throw new HttpException(404, 'Department not found.');
        }

        $department = $this->departments->findPublishedBySlug($slug);

        if ($department === null) {
            throw new HttpException(404, 'Department not found.');
        }

        $description = trim((string) ($department['overview'] ?? ''));
        $description = $description !== ''
            ? mb_substr(strip_tags($description), 0, 300)
            : (string) $department['name'];

        return $this->view(
            'public/departments/show',
            [
                'pageTitle' => (string) $department['name'],
                'metaDescription' => $description,
                'currentPath' => $request->path(),
                'baseUrl' => $request->baseUrl(),
                'department' => $department,
                'programmes' => $this->departments->publishedProgrammes(
                    (int) $department['id']
                ),
            ]
        );
    }
}
