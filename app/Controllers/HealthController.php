<?php

declare(strict_types=1);

namespace FastWebsite\Controllers;

use FastWebsite\Core\Controller;
use FastWebsite\Core\Database;
use FastWebsite\Core\DatabaseVerifier;
use FastWebsite\Core\Request;
use FastWebsite\Core\Response;
use Throwable;

final class HealthController extends Controller
{
    public function __construct(
        \FastWebsite\Core\View $view,
        private readonly Database $database
    ) {
        parent::__construct($view);
    }

    public function show(Request $request): Response
    {
        $health = [
            'connected' => false,
            'structureMatches' => false,
            'leastPrivilege' => false,
        ];

        try {
            $result = (new DatabaseVerifier($this->database->connection()))->verify();
            $health = [
                'connected' => (bool) $result['connected'],
                'structureMatches' => (bool) $result['tables']['matches']
                    && (bool) $result['foreign_keys']['matches']
                    && (bool) $result['checks']['matches']
                    && $result['required_tables_missing'] === [],
                'leastPrivilege' => (bool) $result['privileges']['least_privilege'],
            ];
        } catch (Throwable) {
            // Public health output intentionally exposes no connection details.
        }

        $healthy = !in_array(false, $health, true);

        return $this->view(
            'public/health',
            [
                'pageTitle' => 'System health',
                'metaDescription' => 'FAST website system health status.',
                'health' => $health,
                'healthy' => $healthy,
                'currentPath' => $request->path(),
                'baseUrl' => $request->baseUrl(),
            ],
            $health['connected'] ? 200 : 503
        )->withHeader('Cache-Control', 'no-store');
    }
}
