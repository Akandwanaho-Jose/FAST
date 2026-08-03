<?php

declare(strict_types=1);

namespace FastWebsite\Repositories;

use FastWebsite\Core\Database;
use FastWebsite\Services\DepartmentScopeService;
use PDO;

final class StaffRepository
{
    public function __construct(
        private readonly Database $database,
        private readonly DepartmentScopeService $scope
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function departments(): array
    {
        return $this->connection()->query(
            'SELECT id, name FROM departments
             WHERE deleted_at IS NULL ORDER BY name'
        )->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function positions(): array
    {
        return $this->connection()->query(
            'SELECT id, name, code, position_type FROM positions
             WHERE is_active = 1 ORDER BY hierarchy_level, name'
        )->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function locations(): array
    {
        return $this->connection()->query(
            'SELECT id, campus, building, floor, room FROM locations
             ORDER BY campus, building, floor, room'
        )->fetchAll();
    }

    /** @return list<array<string, mixed>> */
    public function activeImages(): array
    {
        return $this->connection()->query(
            'SELECT id, original_name, file_path, alt_text FROM media
             WHERE media_type = "image" AND status = "active"
               AND deleted_at IS NULL ORDER BY original_name'
        )->fetchAll();
    }

    /** @return array{items:list<array<string,mixed>>,total:int,page:int,pages:int} */
    public function paginateAdmin(
        int $userId,
        string $search,
        string $status,
        int $page,
        int $perPage = 12
    ): array {
        $conditions = ['s.deleted_at IS NULL', $this->scopeCondition($userId, 's')];
        $parameters = [];

        if ($search !== '') {
            $conditions[] = '(s.first_name LIKE :first OR s.last_name LIKE :last
                OR s.institutional_email LIKE :email OR s.staff_number LIKE :number)';
            $parameters = [
                'first' => '%' . $search . '%',
                'last' => '%' . $search . '%',
                'email' => '%' . $search . '%',
                'number' => '%' . $search . '%',
            ];
        }

        if ($status !== '') {
            $conditions[] = 's.status = :status';
            $parameters['status'] = $status;
        }

        $where = implode(' AND ', $conditions);
        $count = $this->connection()->prepare(
            'SELECT COUNT(*) FROM staff s WHERE ' . $where
        );
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $statement = $this->connection()->prepare(sprintf(
            'SELECT s.id, s.honorific_title, s.first_name, s.middle_name,
                    s.last_name, s.staff_category, s.institutional_email,
                    s.status, s.updated_at,
                    d.name AS department_name,
                    COALESCE(sp.title_override, p.name) AS position_title
             FROM staff s
             LEFT JOIN staff_departments sd
                ON sd.staff_id = s.id AND sd.is_primary = 1
                AND (sd.end_date IS NULL OR sd.end_date >= CURDATE())
             LEFT JOIN departments d ON d.id = sd.department_id
             LEFT JOIN staff_positions sp
                ON sp.staff_id = s.id AND sp.is_current = 1
             LEFT JOIN positions p ON p.id = sp.position_id
             WHERE %s
             GROUP BY s.id
             ORDER BY s.display_order, s.last_name, s.first_name
             LIMIT %d OFFSET %d',
            $where,
            $perPage,
            ($page - 1) * $perPage
        ));
        $statement->execute($parameters);

        return [
            'items' => $statement->fetchAll(),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ];
    }

    /** @return array<string,mixed>|null */
    public function findAdmin(int $id, int $userId): ?array
    {
        $statement = $this->connection()->prepare(
            'SELECT s.*, f.name AS faculty_name,
                    d.id AS department_id, d.name AS department_name,
                    sp.position_id, sp.title_override,
                    COALESCE(sp.title_override, p.name) AS position_title,
                    l.campus, l.building, l.floor, l.room,
                    m.file_path AS profile_path, m.alt_text AS profile_alt_text
             FROM staff s
             INNER JOIN faculties f ON f.id = s.faculty_id
             LEFT JOIN staff_departments sd
                ON sd.staff_id = s.id AND sd.is_primary = 1
                AND (sd.end_date IS NULL OR sd.end_date >= CURDATE())
             LEFT JOIN departments d ON d.id = sd.department_id
             LEFT JOIN staff_positions sp ON sp.staff_id = s.id AND sp.is_current = 1
             LEFT JOIN positions p ON p.id = sp.position_id
             LEFT JOIN locations l ON l.id = s.office_location_id
             LEFT JOIN media m ON m.id = s.profile_media_id
                AND m.status = "active" AND m.deleted_at IS NULL
             WHERE s.id = :id AND s.deleted_at IS NULL
               AND ' . $this->scopeCondition($userId, 's') . '
             GROUP BY s.id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    public function findForUpdate(int $id): ?array
    {
        $statement = $this->connection()->prepare(
            'SELECT * FROM staff WHERE id = :id AND deleted_at IS NULL FOR UPDATE'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    public function findPublishedBySlug(string $slug): ?array
    {
        $statement = $this->connection()->prepare(
            'SELECT s.*, f.name AS faculty_name, d.name AS department_name,
                    COALESCE(sp.title_override, p.name) AS position_title,
                    l.campus, l.building, l.floor, l.room,
                    m.file_path AS profile_path, m.alt_text AS profile_alt_text
             FROM staff s
             INNER JOIN faculties f ON f.id = s.faculty_id
             LEFT JOIN staff_departments sd ON sd.staff_id = s.id AND sd.is_primary = 1
                AND (sd.end_date IS NULL OR sd.end_date >= CURDATE())
             LEFT JOIN departments d ON d.id = sd.department_id
             LEFT JOIN staff_positions sp ON sp.staff_id = s.id AND sp.is_current = 1
             LEFT JOIN positions p ON p.id = sp.position_id
             LEFT JOIN locations l ON l.id = s.office_location_id
             LEFT JOIN media m ON m.id = s.profile_media_id
                AND m.status = "active" AND m.deleted_at IS NULL
             WHERE s.slug = :slug AND s.status = "published"
               AND s.published_at IS NOT NULL AND s.published_at <= NOW()
               AND s.deleted_at IS NULL
             GROUP BY s.id LIMIT 1'
        );
        $statement->execute(['slug' => $slug]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @return list<array{id:int,name:string,staff_count:int}> */
    public function publicDepartments(): array
    {
        return $this->connection()->query(
            'SELECT d.id, d.name, COUNT(DISTINCT s.id) AS staff_count
             FROM departments d
             INNER JOIN staff_departments sd ON sd.department_id = d.id
                AND sd.is_primary = 1
                AND (sd.end_date IS NULL OR sd.end_date >= CURDATE())
             INNER JOIN staff s ON s.id = sd.staff_id
                AND s.status = "published" AND s.published_at IS NOT NULL
                AND s.published_at <= NOW() AND s.deleted_at IS NULL
             WHERE d.status = "published" AND d.deleted_at IS NULL
             GROUP BY d.id, d.name ORDER BY d.name'
        )->fetchAll();
    }

    /** @return list<array{id:int,name:string,staff_count:int}> */
    public function publicExpertiseAreas(): array
    {
        return $this->connection()->query(
            'SELECT ea.id, ea.name, COUNT(DISTINCT s.id) AS staff_count
             FROM expertise_areas ea
             INNER JOIN staff_expertise se ON se.expertise_area_id = ea.id
             INNER JOIN staff s ON s.id = se.staff_id
                AND s.status = "published" AND s.published_at IS NOT NULL
                AND s.published_at <= NOW() AND s.deleted_at IS NULL
             WHERE ea.status = "active"
             GROUP BY ea.id, ea.name ORDER BY ea.name'
        )->fetchAll();
    }

    /** @return list<array{staff_category:string,staff_count:int}> */
    public function publicCategories(): array
    {
        return $this->connection()->query(
            'SELECT staff_category, COUNT(*) AS staff_count FROM staff
             WHERE status = "published" AND published_at IS NOT NULL
               AND published_at <= NOW() AND deleted_at IS NULL
             GROUP BY staff_category ORDER BY FIELD(
                staff_category, "academic", "research", "technical",
                "administrative", "support", "visiting", "emeritus", "other"
             )'
        )->fetchAll();
    }

    /** @return array{items:list<array<string,mixed>>,total:int,page:int,pages:int} */
    public function paginatePublished(
        string $search,
        ?int $departmentId,
        string $category,
        ?int $expertiseId,
        int $page,
        int $perPage = 12
    ): array {
        $conditions = [
            's.status = "published"',
            's.published_at IS NOT NULL',
            's.published_at <= NOW()',
            's.deleted_at IS NULL',
        ];
        $parameters = [];

        if ($search !== '') {
            $conditions[] = '(s.first_name LIKE :first OR s.middle_name LIKE :middle
                OR s.last_name LIKE :last OR s.short_biography LIKE :bio
                OR s.research_summary LIKE :research
                OR EXISTS (
                    SELECT 1 FROM staff_expertise search_se
                    INNER JOIN expertise_areas search_ea
                        ON search_ea.id = search_se.expertise_area_id
                        AND search_ea.status = "active"
                    WHERE search_se.staff_id = s.id AND search_ea.name LIKE :expertise_search
                ))';
            $parameters = [
                'first' => '%' . $search . '%',
                'middle' => '%' . $search . '%',
                'last' => '%' . $search . '%',
                'bio' => '%' . $search . '%',
                'research' => '%' . $search . '%',
                'expertise_search' => '%' . $search . '%',
            ];
        }

        if ($departmentId !== null) {
            $conditions[] = 'EXISTS (
                SELECT 1 FROM staff_departments filter_sd
                WHERE filter_sd.staff_id = s.id
                  AND filter_sd.department_id = :department
                  AND (filter_sd.end_date IS NULL OR filter_sd.end_date >= CURDATE())
            )';
            $parameters['department'] = $departmentId;
        }

        if ($category !== '') {
            $conditions[] = 's.staff_category = :category';
            $parameters['category'] = $category;
        }

        if ($expertiseId !== null) {
            $conditions[] = 'EXISTS (
                SELECT 1 FROM staff_expertise filter_se
                INNER JOIN expertise_areas filter_ea
                    ON filter_ea.id = filter_se.expertise_area_id
                    AND filter_ea.status = "active"
                WHERE filter_se.staff_id = s.id
                  AND filter_se.expertise_area_id = :expertise_id
            )';
            $parameters['expertise_id'] = $expertiseId;
        }

        $where = implode(' AND ', $conditions);
        $count = $this->connection()->prepare('SELECT COUNT(*) FROM staff s WHERE ' . $where);
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $statement = $this->connection()->prepare(sprintf(
            'SELECT s.id, s.honorific_title, s.first_name, s.middle_name,
                    s.last_name, s.post_nominals, s.slug, s.staff_category,
                    s.short_biography, s.supervision_available,
                    m.file_path AS profile_path, m.alt_text AS profile_alt_text,
                    d.name AS department_name, d.slug AS department_slug,
                    COALESCE(sp.title_override, p.name) AS position_title,
                    (
                        SELECT GROUP_CONCAT(ea.name ORDER BY se.is_primary DESC,
                            se.display_order, ea.name SEPARATOR "||")
                        FROM staff_expertise se
                        INNER JOIN expertise_areas ea
                            ON ea.id = se.expertise_area_id AND ea.status = "active"
                        WHERE se.staff_id = s.id
                    ) AS expertise_names
             FROM staff s
             LEFT JOIN media m ON m.id = s.profile_media_id
                AND m.status = "active" AND m.deleted_at IS NULL
             LEFT JOIN staff_departments sd ON sd.staff_id = s.id AND sd.is_primary = 1
                AND (sd.end_date IS NULL OR sd.end_date >= CURDATE())
             LEFT JOIN departments d ON d.id = sd.department_id
             LEFT JOIN staff_positions sp ON sp.staff_id = s.id AND sp.is_current = 1
             LEFT JOIN positions p ON p.id = sp.position_id
             WHERE %s GROUP BY s.id
             ORDER BY s.display_order, s.last_name, s.first_name
             LIMIT %d OFFSET %d',
            $where,
            $perPage,
            ($page - 1) * $perPage
        ));
        $statement->execute($parameters);

        return ['items' => $statement->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /** @return list<array<string,mixed>> */
    public function publicQualifications(int $staffId): array
    {
        $statement = $this->connection()->prepare(
            'SELECT qualification, field_of_study, institution, country, completion_year
             FROM staff_qualifications WHERE staff_id = :staff
             ORDER BY display_order, completion_year DESC, id'
        );
        $statement->execute(['staff' => $staffId]);
        return $statement->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function publicExpertise(int $staffId): array
    {
        $statement = $this->connection()->prepare(
            'SELECT ea.name, ea.slug, se.is_primary
             FROM staff_expertise se
             INNER JOIN expertise_areas ea ON ea.id = se.expertise_area_id
                AND ea.status = "active"
             WHERE se.staff_id = :staff
             ORDER BY se.is_primary DESC, se.display_order, ea.name'
        );
        $statement->execute(['staff' => $staffId]);
        return $statement->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function publicLinks(int $staffId): array
    {
        $statement = $this->connection()->prepare(
            'SELECT link_type, label, url FROM staff_links
             WHERE staff_id = :staff ORDER BY display_order, id'
        );
        $statement->execute(['staff' => $staffId]);
        return $statement->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function publicResearchUnits(int $staffId): array
    {
        $statement = $this->connection()->prepare(
            'SELECT ru.name, ru.slug, rum.membership_role, rum.role_title
             FROM research_unit_members rum
             INNER JOIN research_units ru ON ru.id = rum.research_unit_id
                AND ru.status = "published" AND ru.published_at IS NOT NULL
                AND ru.published_at <= NOW() AND ru.deleted_at IS NULL
             WHERE rum.staff_id = :staff AND rum.is_current = 1
             ORDER BY FIELD(rum.membership_role, "lead", "deputy_lead", "researcher",
                "technician", "graduate_researcher", "student_researcher",
                "affiliate", "external_collaborator", "other"), rum.display_order, ru.name'
        );
        $statement->execute(['staff' => $staffId]);
        return $statement->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function publicProjects(int $staffId): array
    {
        $statement = $this->connection()->prepare(
            'SELECT p.title, p.short_title, p.slug, p.project_status,
                    pm.project_role, pm.role_title
             FROM project_members pm
             INNER JOIN projects p ON p.id = pm.project_id
                AND p.publication_status = "published" AND p.published_at IS NOT NULL
                AND p.published_at <= NOW() AND p.deleted_at IS NULL
             WHERE pm.staff_id = :staff
             ORDER BY FIELD(p.project_status, "ongoing", "planned", "completed",
                "suspended", "cancelled"), p.start_date DESC, p.title'
        );
        $statement->execute(['staff' => $staffId]);
        return $statement->fetchAll();
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        return $this->uniqueExists('slug', $slug, $exceptId);
    }

    public function emailExists(string $email, ?int $exceptId = null): bool
    {
        return $this->uniqueExists('institutional_email', $email, $exceptId);
    }

    public function staffNumberExists(string $number, ?int $exceptId = null): bool
    {
        return $this->uniqueExists('staff_number', $number, $exceptId);
    }

    public function relationExists(string $table, int $id): bool
    {
        $allowed = ['faculties', 'departments', 'positions', 'locations', 'media'];
        if (!in_array($table, $allowed, true)) {
            return false;
        }
        $extra = $table === 'media'
            ? ' AND media_type = "image" AND status = "active" AND deleted_at IS NULL'
            : ($table === 'faculties' || $table === 'departments'
                ? ' AND deleted_at IS NULL'
                : '');
        $statement = $this->connection()->prepare(
            sprintf('SELECT 1 FROM %s WHERE id = :id%s LIMIT 1', $table, $extra)
        );
        $statement->execute(['id' => $id]);
        return $statement->fetchColumn() !== false;
    }

    /** @return array<string,mixed>|null */
    public function position(int $id): ?array
    {
        $statement = $this->connection()->prepare(
            'SELECT id, code, position_type FROM positions
             WHERE id = :id AND is_active = 1 LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    public function departmentBelongsToFaculty(int $departmentId, int $facultyId): bool
    {
        $statement = $this->connection()->prepare(
            'SELECT 1 FROM departments
             WHERE id = :department_id AND faculty_id = :faculty_id
               AND deleted_at IS NULL LIMIT 1'
        );
        $statement->execute([
            'department_id' => $departmentId,
            'faculty_id' => $facultyId,
        ]);
        return $statement->fetchColumn() !== false;
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $fields = array_keys($data);
        $statement = $this->connection()->prepare(sprintf(
            'INSERT INTO staff (%s, status) VALUES (%s, "draft")',
            implode(', ', $fields),
            implode(', ', array_map(static fn (string $f): string => ':' . $f, $fields))
        ));
        $statement->execute($data);
        return (int) $this->connection()->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): void
    {
        $sets = array_map(static fn (string $f): string => $f . ' = :' . $f, array_keys($data));
        $statement = $this->connection()->prepare(
            'UPDATE staff SET ' . implode(', ', $sets) . ' WHERE id = :id AND deleted_at IS NULL'
        );
        $statement->execute([...$data, 'id' => $id]);
    }

    public function replaceAssignment(
        int $staffId,
        ?int $departmentId,
        ?int $positionId,
        ?string $titleOverride,
        int $facultyId
    ): void {
        $this->connection()->prepare('DELETE FROM staff_departments WHERE staff_id = :id')
            ->execute(['id' => $staffId]);
        $this->connection()->prepare('DELETE FROM staff_positions WHERE staff_id = :id')
            ->execute(['id' => $staffId]);

        if ($departmentId !== null) {
            $this->connection()->prepare(
                'INSERT INTO staff_departments (staff_id, department_id, is_primary)
                 VALUES (:staff_id, :department_id, 1)'
            )->execute(['staff_id' => $staffId, 'department_id' => $departmentId]);
        }

        if ($positionId !== null) {
            $position = $this->position($positionId);

            if (is_array($position)
                && in_array($position['code'], ['dean', 'head_of_department'], true)
            ) {
                $scopeSql = $position['code'] === 'dean'
                    ? 'sp.faculty_id = :scope_id'
                    : 'sp.department_id = :scope_id';
                $scopeId = $position['code'] === 'dean'
                    ? $facultyId
                    : $departmentId;

                if ($scopeId !== null) {
                    $this->connection()->prepare(
                        'UPDATE staff_positions sp
                         INNER JOIN positions p ON p.id = sp.position_id
                         SET sp.is_current = 0,
                             sp.end_date = COALESCE(sp.end_date, CURDATE())
                         WHERE p.code = :code
                           AND sp.is_current = 1
                           AND ' . $scopeSql
                    )->execute([
                        'code' => $position['code'],
                        'scope_id' => $scopeId,
                    ]);
                }
            }

            $this->connection()->prepare(
                'INSERT INTO staff_positions
                    (staff_id, position_id, faculty_id, department_id,
                     title_override, is_current)
                 VALUES
                    (:staff_id, :position_id, :faculty_id, :department_id,
                     :title_override, 1)'
            )->execute([
                'staff_id' => $staffId,
                'position_id' => $positionId,
                'faculty_id' => $facultyId,
                'department_id' => $departmentId,
                'title_override' => $titleOverride,
            ]);
        }
    }

    public function changeStatus(int $id, string $status): void
    {
        $published = $status === 'published'
            ? ', published_at = NOW()'
            : ($status === 'draft' ? ', published_at = NULL' : '');
        $statement = $this->connection()->prepare(sprintf(
            'UPDATE staff SET status = :status%s WHERE id = :id',
            $published
        ));
        $statement->execute(['status' => $status, 'id' => $id]);
    }

    /** @param array<string,mixed>|null $previous @param array<string,mixed> $revised */
    public function recordRevision(int $id, ?array $previous, array $revised, int $userId, string $note): void
    {
        $number = $this->connection()->prepare(
            'SELECT COALESCE(MAX(revision_number),0)+1 FROM content_revisions
             WHERE entity_type = "staff" AND entity_id = :id'
        );
        $number->execute(['id' => $id]);
        $statement = $this->connection()->prepare(
            'INSERT INTO content_revisions
                (entity_type, entity_id, revision_number, previous_data,
                 revised_data, revised_by, revision_note)
             VALUES ("staff", :id, :number, :previous, :revised, :user, :note)'
        );
        $statement->execute([
            'id' => $id,
            'number' => (int) $number->fetchColumn(),
            'previous' => $previous === null ? null : json_encode($previous, JSON_THROW_ON_ERROR),
            'revised' => json_encode($revised, JSON_THROW_ON_ERROR),
            'user' => $userId,
            'note' => $note,
        ]);
    }

    public function recordApproval(int $id, string $action, int $userId, string $comment): void
    {
        $statement = $this->connection()->prepare(
            'INSERT INTO content_approvals
                (entity_type, entity_id, action, acted_by, comment)
             VALUES ("staff", :id, :action, :user, :comment)'
        );
        $statement->execute([
            'id' => $id, 'action' => $action, 'user' => $userId,
            'comment' => $comment !== '' ? $comment : null,
        ]);
    }

    /** @return list<array<string,mixed>> */
    public function approvalHistory(int $id): array
    {
        $statement = $this->connection()->prepare(
            'SELECT ca.*, u.name AS actor_name FROM content_approvals ca
             LEFT JOIN users u ON u.id = ca.acted_by
             WHERE ca.entity_type = "staff" AND ca.entity_id = :id
             ORDER BY ca.created_at DESC, ca.id DESC'
        );
        $statement->execute(['id' => $id]);
        return $statement->fetchAll();
    }

    public function connection(): PDO
    {
        return $this->database->connection();
    }

    private function uniqueExists(string $field, string $value, ?int $exceptId): bool
    {
        $sql = sprintf('SELECT 1 FROM staff WHERE %s = :value AND deleted_at IS NULL', $field);
        $parameters = ['value' => $value];
        if ($exceptId !== null) {
            $sql .= ' AND id <> :id';
            $parameters['id'] = $exceptId;
        }
        $statement = $this->connection()->prepare($sql . ' LIMIT 1');
        $statement->execute($parameters);
        return $statement->fetchColumn() !== false;
    }

    private function scopeCondition(int $userId, string $alias): string
    {
        if ($this->scope->hasGlobalScope($userId)) {
            return '1 = 1';
        }
        $ids = $this->scope->departmentIds($userId);
        if ($ids === []) {
            return '1 = 0';
        }
        return sprintf(
            'EXISTS (SELECT 1 FROM staff_departments scoped_sd
             WHERE scoped_sd.staff_id = %s.id AND scoped_sd.department_id IN (%s)
               AND (scoped_sd.end_date IS NULL OR scoped_sd.end_date >= CURDATE()))',
            $alias,
            implode(',', array_map('intval', $ids))
        );
    }
}
