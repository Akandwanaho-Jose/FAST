<?php

declare(strict_types=1);

namespace FastWebsite\Controllers;

use FastWebsite\Core\Controller;
use FastWebsite\Core\HttpException;
use FastWebsite\Core\Request;
use FastWebsite\Core\Response;
use FastWebsite\Repositories\StaffRepository;
use FastWebsite\Repositories\PublicationRepository;

final class StaffPublicController extends Controller
{
    public function __construct(
        \FastWebsite\Core\View $view,
        private readonly StaffRepository $staff,
        private readonly PublicationRepository $publications
    ) {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $departments = $this->staff->publicDepartments();
        $expertiseAreas = $this->staff->publicExpertiseAreas();
        $categories = $this->staff->publicCategories();
        $department = $this->validId(
            $request->query('department'),
            array_column($departments, 'id')
        );
        $expertise = $this->validId(
            $request->query('expertise'),
            array_column($expertiseAreas, 'id')
        );
        $category = mb_substr(trim((string) $request->query('category', '')), 0, 40);
        if (!in_array($category, array_column($categories, 'staff_category'), true)) {
            $category = '';
        }
        $page = ctype_digit((string) $request->query('page', '1'))
            ? max(1, (int) $request->query('page', '1'))
            : 1;
        return $this->view('public/staff/index', [
            'pageTitle' => 'Staff directory',
            'metaDescription' => 'Published FAST staff profiles.',
            'currentPath' => $request->path(),
            'baseUrl' => $request->baseUrl(),
            'search' => $search,
            'departmentFilter' => $department,
            'categoryFilter' => $category,
            'expertiseFilter' => $expertise,
            'departments' => $departments,
            'categories' => $categories,
            'expertiseAreas' => $expertiseAreas,
            'result' => $this->staff->paginatePublished(
                $search,
                $department,
                $category,
                $expertise,
                $page
            ),
        ]);
    }

    public function show(Request $request): Response
    {
        $slug = (string) $request->route('slug', '');
        if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            throw new HttpException(404, 'Staff profile not found.');
        }
        $profile = $this->staff->findPublishedBySlug($slug);
        if ($profile === null) {
            throw new HttpException(404, 'Staff profile not found.');
        }
        return $this->view('public/staff/show', [
            'pageTitle' => trim($profile['first_name'] . ' ' . $profile['last_name']),
            'metaDescription' => mb_substr((string) ($profile['short_biography'] ?? ''), 0, 300),
            'currentPath' => $request->path(),
            'baseUrl' => $request->baseUrl(),
            'profile' => $profile,
            'publications' => $this->publications->staffPublications((int) $profile['id']),
            'qualifications' => $this->staff->publicQualifications((int) $profile['id']),
            'expertise' => $this->staff->publicExpertise((int) $profile['id']),
            'links' => $this->staff->publicLinks((int) $profile['id']),
            'researchUnits' => $this->staff->publicResearchUnits((int) $profile['id']),
            'projects' => $this->staff->publicProjects((int) $profile['id']),
        ]);
    }

    /** @param list<mixed> $allowed */
    private function validId(mixed $value, array $allowed): ?int
    {
        $value = trim((string) $value);
        if (!ctype_digit($value) || (int) $value < 1) {
            return null;
        }

        $id = (int) $value;
        return in_array($id, array_map('intval', $allowed), true) ? $id : null;
    }
}
