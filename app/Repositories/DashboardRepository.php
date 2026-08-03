<?php

declare(strict_types=1);

namespace FastWebsite\Repositories;

use FastWebsite\Core\Database;
use FastWebsite\Services\AuthorizationService;
use FastWebsite\Services\DepartmentScopeService;
use PDO;

final class DashboardRepository
{
    public function __construct(
        private readonly Database $database,
        private readonly AuthorizationService $authorization,
        private readonly DepartmentScopeService $scope
    ) {
    }

    /**
     * @return list<array{label: string, value: int, route: string}>
     */
    public function counts(int $userId): array
    {
        $definitions = [
            ['Departments', 'departments', 'departments.view', 'admin/departments', 'd.deleted_at IS NULL', 'd.id'],
            ['Staff profiles', 'staff', 'staff.view', 'admin/staff', 'd.deleted_at IS NULL', 'd.id'],
            ['Programmes', 'programmes', 'programmes.view', 'admin/programmes', 'd.deleted_at IS NULL', 'd.id'],
            ['Research units', 'research_units', 'research.view', 'admin/research', 'd.deleted_at IS NULL', 'd.id'],
            ['Projects', 'projects', 'research.view', 'admin/research', 'd.deleted_at IS NULL', 'd.id'],
            ['Publications', 'publications', 'research.view', 'admin/research', 'd.deleted_at IS NULL', 'd.id'],
            ['News stories', 'news', 'news.view', 'admin/news', 'd.deleted_at IS NULL', 'd.id'],
            ['Events', 'events', 'events.manage', 'admin/events', '1 = 1', 'd.id'],
            ['Pages', 'pages', 'pages.manage', 'admin/pages', 'd.deleted_at IS NULL', 'd.id'],
            ['Documents', 'documents', 'documents.manage', 'admin/documents', 'd.deleted_at IS NULL', 'd.id'],
        ];
        $counts = [];

        foreach ($definitions as [$label, $table, $permission, $route, $baseWhere, $identifier]) {
            if (!$this->authorization->can($userId, $permission)) {
                continue;
            }

            $scopeWhere = $this->scopeCondition($table, $userId, 'd');
            $sql = sprintf(
                'SELECT COUNT(DISTINCT %s) FROM %s d WHERE %s AND %s',
                $identifier,
                $table,
                $baseWhere,
                $scopeWhere
            );
            $counts[] = [
                'label' => $label,
                'value' => (int) $this->connection()->query($sql)->fetchColumn(),
                'route' => $route,
            ];
        }

        return $counts;
    }

    /**
     * @return list<array{
     *   type: string,
     *   title: string,
     *   status: string,
     *   updated_at: string
     * }>
     */
    public function recentContent(int $userId, int $limit = 8): array
    {
        return $this->contentFeed($userId, null, $limit);
    }

    /**
     * @return list<array{
     *   type: string,
     *   title: string,
     *   status: string,
     *   updated_at: string
     * }>
     */
    public function workflowQueue(
        int $userId,
        string $status,
        int $limit = 6
    ): array {
        if (!in_array($status, ['draft', 'under_review'], true)) {
            return [];
        }

        if ($status === 'under_review'
            && !$this->authorization->can($userId, 'content.review')
            && !$this->authorization->can($userId, 'content.approve')
        ) {
            return [];
        }

        return $this->contentFeed($userId, $status, $limit);
    }

    /**
     * @return list<array{
     *   type: string,
     *   title: string,
     *   status: string,
     *   updated_at: string
     * }>
     */
    private function contentFeed(
        int $userId,
        ?string $status,
        int $limit
    ): array {
        $sources = [
            ['Department', 'departments', 'name', 'status', 'departments.view', 'deleted_at IS NULL'],
            ['Staff', 'staff', "CONCAT_WS(' ', first_name, NULLIF(middle_name, ''), last_name)", 'status', 'staff.view', 'deleted_at IS NULL'],
            ['Programme', 'programmes', 'name', 'status', 'programmes.view', 'deleted_at IS NULL'],
            ['Research unit', 'research_units', 'name', 'status', 'research.view', 'deleted_at IS NULL'],
            ['Project', 'projects', 'title', 'publication_status', 'research.view', 'deleted_at IS NULL'],
            ['Publication', 'publications', 'title', 'status', 'research.view', 'deleted_at IS NULL'],
            ['News', 'news', 'title', 'status', 'news.view', 'deleted_at IS NULL'],
            ['Event', 'events', 'title', 'status', 'events.manage', '1 = 1'],
            ['Page', 'pages', 'title', 'status', 'pages.manage', 'deleted_at IS NULL'],
            ['Document', 'documents', 'title', 'status', 'documents.manage', 'deleted_at IS NULL'],
        ];
        $queries = [];

        foreach ($sources as [$type, $table, $title, $statusColumn, $permission, $baseWhere]) {
            if (!$this->authorization->can($userId, $permission)) {
                continue;
            }

            $where = sprintf(
                '%s AND %s',
                $baseWhere,
                $this->scopeCondition($table, $userId)
            );

            if ($status !== null) {
                $where .= sprintf(
                    " AND %s = %s",
                    $statusColumn,
                    $this->connection()->quote($status)
                );
            }

            $queries[] = sprintf(
                "SELECT %s AS type, %s AS title, %s AS status, updated_at
                 FROM %s
                 WHERE %s",
                $this->connection()->quote($type),
                $title,
                $statusColumn,
                $table,
                $where
            );
        }

        if ($queries === []) {
            return [];
        }

        $limit = max(1, min($limit, 25));
        $statement = $this->connection()->query(sprintf(
            'SELECT type, title, status, updated_at
             FROM (%s) content
             ORDER BY updated_at DESC
             LIMIT %d',
            implode(' UNION ALL ', $queries),
            $limit
        ));

        /** @var list<array{
         *   type: string,
         *   title: string,
         *   status: string,
         *   updated_at: string
         * }> $items
         */
        $items = $statement->fetchAll();

        return $items;
    }

    private function scopeCondition(
        string $table,
        int $userId,
        string $alias = ''
    ): string {
        if ($this->scope->hasGlobalScope($userId)) {
            return '1 = 1';
        }

        $departmentIds = $this->scope->departmentIds($userId);

        if ($departmentIds === []) {
            return '1 = 0';
        }

        $ids = implode(',', array_map('intval', $departmentIds));
        $prefix = $alias === '' ? '' : $alias . '.';

        return match ($table) {
            'departments' => sprintf('%sid IN (%s)', $prefix, $ids),
            'staff' => sprintf(
                'EXISTS (
                    SELECT 1 FROM staff_departments sd
                    WHERE sd.staff_id = %sid
                      AND sd.department_id IN (%s)
                      AND (sd.start_date IS NULL OR sd.start_date <= CURRENT_DATE)
                      AND (sd.end_date IS NULL OR sd.end_date >= CURRENT_DATE)
                )',
                $prefix,
                $ids
            ),
            'programmes' => sprintf(
                'EXISTS (
                    SELECT 1 FROM programme_departments pd
                    WHERE pd.programme_id = %sid
                      AND pd.department_id IN (%s)
                )',
                $prefix,
                $ids
            ),
            'research_units' => sprintf(
                '%sdepartment_id IN (%s)',
                $prefix,
                $ids
            ),
            'projects' => sprintf(
                '%slead_department_id IN (%s)',
                $prefix,
                $ids
            ),
            'publications' => sprintf(
                'EXISTS (
                    SELECT 1
                    FROM publication_authors pa
                    INNER JOIN staff_departments sd ON sd.staff_id = pa.staff_id
                    WHERE pa.publication_id = %sid
                      AND sd.department_id IN (%s)
                      AND (sd.start_date IS NULL OR sd.start_date <= CURRENT_DATE)
                      AND (sd.end_date IS NULL OR sd.end_date >= CURRENT_DATE)
                )',
                $prefix,
                $ids
            ),
            'news' => sprintf(
                'EXISTS (
                    SELECT 1 FROM news_departments nd
                    WHERE nd.news_id = %sid
                      AND nd.department_id IN (%s)
                )',
                $prefix,
                $ids
            ),
            'events' => sprintf(
                'EXISTS (
                    SELECT 1 FROM event_departments ed
                    WHERE ed.event_id = %sid
                      AND ed.department_id IN (%s)
                )',
                $prefix,
                $ids
            ),
            default => '1 = 0',
        };
    }

    private function connection(): PDO
    {
        return $this->database->connection();
    }
}
