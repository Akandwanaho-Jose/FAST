<?php

declare(strict_types=1);

namespace FastWebsite\Repositories;

use FastWebsite\Core\Database;
use FastWebsite\Services\DepartmentScopeService;
use PDO;

final class DepartmentRepository
{
    public function __construct(
        private readonly Database $database,
        private readonly DepartmentScopeService $scope
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function faculty(): ?array
    {
        $row = $this->connection()->query(
            'SELECT f.*,
                    l.campus, l.building, l.floor, l.room, l.directions,
                    m.file_path AS logo_path, m.alt_text AS logo_alt_text
             FROM faculties f
             LEFT JOIN locations l ON l.id = f.location_id
             LEFT JOIN media m
                ON m.id = f.logo_media_id
               AND m.status = "active"
               AND m.deleted_at IS NULL
             WHERE f.deleted_at IS NULL
             ORDER BY f.id
             LIMIT 1'
        )->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function locations(): array
    {
        return $this->connection()->query(
            'SELECT id, campus, building, floor, room, directions
             FROM locations
             ORDER BY campus, building, floor, room, id'
        )->fetchAll();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function activeImages(): array
    {
        return $this->connection()->query(
            'SELECT id, original_name, file_path, alt_text
             FROM media
             WHERE media_type = "image"
               AND status = "active"
               AND deleted_at IS NULL
             ORDER BY original_name, id'
        )->fetchAll();
    }

    /**
     * @return array{
     *   items: list<array<string, mixed>>,
     *   total: int,
     *   page: int,
     *   pages: int
     * }
     */
    public function paginateAdmin(
        int $userId,
        string $search,
        string $status,
        int $page,
        int $perPage = 10
    ): array {
        $conditions = ['d.deleted_at IS NULL'];
        $parameters = [];
        $scopeCondition = $this->adminScopeCondition($userId, 'd');
        $conditions[] = $scopeCondition;

        if ($search !== '') {
            $conditions[] = '(d.name LIKE :search_name
                OR d.short_name LIKE :search_short_name
                OR d.slug LIKE :search_slug)';
            $parameters['search_name'] = '%' . $search . '%';
            $parameters['search_short_name'] = '%' . $search . '%';
            $parameters['search_slug'] = '%' . $search . '%';
        }

        if ($status !== '') {
            $conditions[] = 'd.status = :status';
            $parameters['status'] = $status;
        }

        $where = implode(' AND ', $conditions);
        $count = $this->connection()->prepare(
            'SELECT COUNT(*) FROM departments d WHERE ' . $where
        );
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $pages));
        $offset = ($page - 1) * $perPage;
        $statement = $this->connection()->prepare(sprintf(
            'SELECT d.id, d.name, d.short_name, d.slug, d.email, d.status,
                    d.display_order, d.updated_at, f.short_name AS faculty_short_name
             FROM departments d
             INNER JOIN faculties f ON f.id = d.faculty_id
             WHERE %s
             ORDER BY d.display_order, d.name
             LIMIT %d OFFSET %d',
            $where,
            $perPage,
            $offset
        ));
        $statement->execute($parameters);

        return [
            'items' => $statement->fetchAll(),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findAdmin(int $id, int $userId): ?array
    {
        $statement = $this->connection()->prepare(
            'SELECT d.*, f.name AS faculty_name,
                    l.campus, l.building, l.floor, l.room, l.directions,
                    m.file_path AS hero_path, m.alt_text AS hero_alt_text
             FROM departments d
             INNER JOIN faculties f ON f.id = d.faculty_id
             LEFT JOIN locations l ON l.id = d.location_id
             LEFT JOIN media m
                ON m.id = d.hero_media_id
               AND m.status = "active"
               AND m.deleted_at IS NULL
             WHERE d.id = :id
               AND d.deleted_at IS NULL
               AND ' . $this->adminScopeCondition($userId, 'd') . '
             LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findForUpdate(int $id): ?array
    {
        $statement = $this->connection()->prepare(
            'SELECT * FROM departments
             WHERE id = :id AND deleted_at IS NULL
             FOR UPDATE'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array{
     *   items: list<array<string, mixed>>,
     *   total: int,
     *   page: int,
     *   pages: int
     * }
     */
    public function paginatePublished(
        string $search,
        int $page,
        int $perPage = 12
    ): array {
        $conditions = [
            'd.deleted_at IS NULL',
            'd.status = "published"',
            'd.published_at IS NOT NULL',
            'd.published_at <= NOW()',
        ];
        $parameters = [];

        if ($search !== '') {
            $conditions[] = '(d.name LIKE :search_name
                OR d.short_name LIKE :search_short_name
                OR d.overview LIKE :search_overview)';
            $parameters['search_name'] = '%' . $search . '%';
            $parameters['search_short_name'] = '%' . $search . '%';
            $parameters['search_overview'] = '%' . $search . '%';
        }

        $where = implode(' AND ', $conditions);
        $count = $this->connection()->prepare(
            'SELECT COUNT(*) FROM departments d WHERE ' . $where
        );
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $pages));
        $offset = ($page - 1) * $perPage;
        $statement = $this->connection()->prepare(sprintf(
            'SELECT d.id, d.name, d.short_name, d.slug, d.overview,
                    d.email, d.phone, d.hero_media_id,
                    m.file_path AS hero_path, m.alt_text AS hero_alt_text
             FROM departments d
             LEFT JOIN media m
                ON m.id = d.hero_media_id
               AND m.status = "active"
               AND m.deleted_at IS NULL
             WHERE %s
             ORDER BY d.display_order, d.name
             LIMIT %d OFFSET %d',
            $where,
            $perPage,
            $offset
        ));
        $statement->execute($parameters);

        return [
            'items' => $statement->fetchAll(),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findPublishedBySlug(string $slug): ?array
    {
        $statement = $this->connection()->prepare(
            'SELECT d.*, f.name AS faculty_name,
                    l.campus, l.building, l.floor, l.room, l.directions,
                    m.file_path AS hero_path, m.alt_text AS hero_alt_text
             FROM departments d
             INNER JOIN faculties f ON f.id = d.faculty_id
             LEFT JOIN locations l ON l.id = d.location_id
             LEFT JOIN media m
                ON m.id = d.hero_media_id
               AND m.status = "active"
               AND m.deleted_at IS NULL
             WHERE d.slug = :slug
               AND d.status = "published"
               AND d.published_at IS NOT NULL
               AND d.published_at <= NOW()
               AND d.deleted_at IS NULL
             LIMIT 1'
        );
        $statement->execute(['slug' => $slug]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * Resolve department leadership from the authoritative staff directory.
     *
     * @return array<string, mixed>|null
     */
    public function findPublishedHead(int $departmentId): ?array
    {
        $statement = $this->connection()->prepare(
            'SELECT s.id, s.honorific_title, s.first_name, s.middle_name,
                    s.last_name, s.post_nominals, s.slug,
                    s.short_biography, s.institutional_email,
                    COALESCE(sp.title_override, p.name) AS position_title,
                    m.file_path AS profile_path,
                    m.alt_text AS profile_alt_text
             FROM staff_positions sp
             INNER JOIN staff s ON s.id = sp.staff_id
             INNER JOIN positions p
                ON p.id = sp.position_id
               AND p.code = "head_of_department"
               AND p.is_active = 1
             INNER JOIN departments d
                ON d.id = sp.department_id
               AND d.faculty_id = s.faculty_id
             LEFT JOIN media m
                ON m.id = s.profile_media_id
               AND m.media_type = "image"
               AND m.status = "active"
               AND m.deleted_at IS NULL
             WHERE sp.department_id = :department_id
               AND sp.is_current = 1
               AND (sp.start_date IS NULL OR sp.start_date <= CURDATE())
               AND (sp.end_date IS NULL OR sp.end_date >= CURDATE())
               AND s.status = "published"
               AND s.published_at IS NOT NULL
               AND s.published_at <= NOW()
               AND s.deleted_at IS NULL
             ORDER BY sp.display_order, sp.start_date DESC, sp.id DESC
             LIMIT 1'
        );
        $statement->execute(['department_id' => $departmentId]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return list<array<string,mixed>> */
    public function publishedProgrammes(int $departmentId): array
    {
        $statement = $this->connection()->prepare(
            'SELECT p.name, p.slug, p.programme_code, p.duration_text,
                    p.overview, pl.name AS level_name
             FROM programme_departments pd
             INNER JOIN programmes p ON p.id = pd.programme_id
             INNER JOIN programme_levels pl ON pl.id = p.programme_level_id
             WHERE pd.department_id = :department_id
               AND p.status = "published"
               AND p.published_at IS NOT NULL
               AND p.published_at <= NOW()
               AND p.deleted_at IS NULL
             ORDER BY pl.display_order, p.display_order, p.name'
        );
        $statement->execute(['department_id' => $departmentId]);
        return $statement->fetchAll();
    }

    public function facultyExists(int $id): bool
    {
        return $this->exists(
            'SELECT 1 FROM faculties WHERE id = :id AND deleted_at IS NULL',
            $id
        );
    }

    public function locationExists(int $id): bool
    {
        return $this->exists(
            'SELECT 1 FROM locations WHERE id = :id',
            $id
        );
    }

    public function activeImageExists(int $id): bool
    {
        return $this->exists(
            'SELECT 1 FROM media
             WHERE id = :id
               AND media_type = "image"
               AND status = "active"
               AND deleted_at IS NULL',
            $id
        );
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $sql = 'SELECT 1 FROM departments
                WHERE slug = :slug AND deleted_at IS NULL';
        $parameters = ['slug' => $slug];

        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $parameters['id'] = $exceptId;
        }

        $statement = $this->connection()->prepare($sql . ' LIMIT 1');
        $statement->execute($parameters);

        return $statement->fetchColumn() !== false;
    }

    public function facultyNameExists(
        int $facultyId,
        string $name,
        ?int $exceptId = null
    ): bool {
        $sql = 'SELECT 1 FROM departments
                WHERE faculty_id = :faculty_id
                  AND name = :name
                  AND deleted_at IS NULL';
        $parameters = ['faculty_id' => $facultyId, 'name' => $name];

        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $parameters['id'] = $exceptId;
        }

        $statement = $this->connection()->prepare($sql . ' LIMIT 1');
        $statement->execute($parameters);

        return $statement->fetchColumn() !== false;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): int
    {
        $statement = $this->connection()->prepare(
            'INSERT INTO departments
                (faculty_id, name, short_name, slug, overview, history, vision,
                 mission, strategic_direction, hod_message, email, phone,
                 location_id, hero_media_id, display_order, status)
             VALUES
                (:faculty_id, :name, :short_name, :slug, :overview, :history,
                 :vision, :mission, :strategic_direction, :hod_message, :email,
                 :phone, :location_id, :hero_media_id, :display_order, "draft")'
        );
        $statement->execute($data);

        return (int) $this->connection()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $statement = $this->connection()->prepare(
            'UPDATE departments SET
                faculty_id = :faculty_id,
                name = :name,
                short_name = :short_name,
                slug = :slug,
                overview = :overview,
                history = :history,
                vision = :vision,
                mission = :mission,
                strategic_direction = :strategic_direction,
                hod_message = :hod_message,
                email = :email,
                phone = :phone,
                location_id = :location_id,
                hero_media_id = :hero_media_id,
                display_order = :display_order
             WHERE id = :id AND deleted_at IS NULL'
        );
        $statement->execute([...$data, 'id' => $id]);
    }

    public function changeStatus(int $id, string $status): void
    {
        $published = $status === 'published'
            ? ', published_at = NOW()'
            : ($status === 'draft' ? ', published_at = NULL' : '');
        $statement = $this->connection()->prepare(
            sprintf(
                'UPDATE departments
                 SET status = :status%s
                 WHERE id = :id AND deleted_at IS NULL',
                $published
            )
        );
        $statement->execute(['status' => $status, 'id' => $id]);
    }

    /**
     * @param array<string, mixed>|null $previousData
     * @param array<string, mixed> $revisedData
     */
    public function recordRevision(
        int $entityId,
        ?array $previousData,
        array $revisedData,
        int $userId,
        string $note
    ): void {
        $numberStatement = $this->connection()->prepare(
            'SELECT COALESCE(MAX(revision_number), 0) + 1
             FROM content_revisions
             WHERE entity_type = "department" AND entity_id = :entity_id'
        );
        $numberStatement->execute(['entity_id' => $entityId]);
        $statement = $this->connection()->prepare(
            'INSERT INTO content_revisions
                (entity_type, entity_id, revision_number, previous_data,
                 revised_data, revised_by, revision_note)
             VALUES
                ("department", :entity_id, :revision_number, :previous_data,
                 :revised_data, :revised_by, :revision_note)'
        );
        $statement->execute([
            'entity_id' => $entityId,
            'revision_number' => (int) $numberStatement->fetchColumn(),
            'previous_data' => $previousData === null
                ? null
                : json_encode($previousData, JSON_THROW_ON_ERROR),
            'revised_data' => json_encode($revisedData, JSON_THROW_ON_ERROR),
            'revised_by' => $userId,
            'revision_note' => $note,
        ]);
    }

    public function recordApproval(
        int $entityId,
        string $action,
        int $userId,
        ?string $comment
    ): void {
        $statement = $this->connection()->prepare(
            'INSERT INTO content_approvals
                (entity_type, entity_id, action, acted_by, comment)
             VALUES
                ("department", :entity_id, :action, :acted_by, :comment)'
        );
        $statement->execute([
            'entity_id' => $entityId,
            'action' => $action,
            'acted_by' => $userId,
            'comment' => $comment !== '' ? $comment : null,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function approvalHistory(int $entityId): array
    {
        $statement = $this->connection()->prepare(
            'SELECT ca.action, ca.comment, ca.created_at, u.name AS actor_name
             FROM content_approvals ca
             LEFT JOIN users u ON u.id = ca.acted_by
             WHERE ca.entity_type = "department"
               AND ca.entity_id = :entity_id
             ORDER BY ca.created_at DESC, ca.id DESC'
        );
        $statement->execute(['entity_id' => $entityId]);

        return $statement->fetchAll();
    }

    public function connection(): PDO
    {
        return $this->database->connection();
    }

    private function exists(string $sql, int $id): bool
    {
        $statement = $this->connection()->prepare($sql . ' LIMIT 1');
        $statement->execute(['id' => $id]);

        return $statement->fetchColumn() !== false;
    }

    private function adminScopeCondition(int $userId, string $alias): string
    {
        if ($this->scope->hasGlobalScope($userId)) {
            return '1 = 1';
        }

        $ids = $this->scope->departmentIds($userId);

        if ($ids === []) {
            return '1 = 0';
        }

        return sprintf(
            '%s.id IN (%s)',
            $alias,
            implode(',', array_map('intval', $ids))
        );
    }
}
