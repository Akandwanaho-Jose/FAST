<?php

declare(strict_types=1);

namespace FastWebsite\Controllers;

use FastWebsite\Core\Controller;
use FastWebsite\Core\Request;
use FastWebsite\Core\Response;
use FastWebsite\Repositories\DashboardRepository;
use FastWebsite\Services\AdminPageContext;
use FastWebsite\Services\AuthService;
use FastWebsite\Services\AuthorizationService;

final class AdminController extends Controller
{
    public function __construct(
        \FastWebsite\Core\View $view,
        private readonly AuthService $auth,
        private readonly AuthorizationService $authorization,
        private readonly AdminPageContext $pageContext,
        private readonly DashboardRepository $dashboard
    ) {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        $user = $this->auth->user();

        if ($user === null) {
            return Response::redirect($request->baseUrl() . 'login');
        }

        return $this->view(
            'admin/dashboard',
            array_merge(
                $this->pageContext->data($request, $user),
                [
                    'pageTitle' => 'Dashboard',
                    'counts' => $this->dashboard->counts((int) $user['id']),
                    'recentContent' => $this->dashboard->recentContent((int) $user['id']),
                    'draftQueue' => $this->dashboard->workflowQueue(
                        (int) $user['id'],
                        'draft'
                    ),
                    'reviewQueue' => $this->dashboard->workflowQueue(
                        (int) $user['id'],
                        'under_review'
                    ),
                    'canReview' => $this->authorization->can(
                        (int) $user['id'],
                        'content.review'
                    ) || $this->authorization->can(
                        (int) $user['id'],
                        'content.approve'
                    ),
                ]
            ),
            200,
            'layouts/admin'
        )->withHeader('Cache-Control', 'no-store');
    }

    /**
     * @param array{
     *   key: string,
     *   label: string,
     *   route: string,
     *   permission: string,
     *   phase: string
     * } $module
     */
    public function module(Request $request, array $module): Response
    {
        $user = $this->auth->user();

        if ($user === null) {
            return Response::redirect($request->baseUrl() . 'login');
        }

        return $this->view(
            'admin/module-placeholder',
            array_merge(
                $this->pageContext->data($request, $user),
                [
                    'pageTitle' => $module['label'],
                    'module' => $module,
                ]
            ),
            200,
            'layouts/admin'
        )->withHeader('Cache-Control', 'no-store');
    }

}
