<?php

declare(strict_types=1);

namespace FastWebsite\Repositories;

use FastWebsite\Core\Database;
use FastWebsite\Services\DepartmentScopeService;
use PDO;

final class ProgrammeRepository
{
    public function __construct(
        private readonly Database $database,
        private readonly DepartmentScopeService $scope
    ) {
    }

    /** @return list<array<string,mixed>> */
    public function levels(): array
    {
        return $this->connection()->query(
            'SELECT id, name, code FROM programme_levels
             WHERE is_active = 1 ORDER BY display_order, name'
        )->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function departments(): array
    {
        return $this->connection()->query(
            'SELECT id, name FROM departments
             WHERE deleted_at IS NULL ORDER BY name'
        )->fetchAll();
    }

    /** @return list<array{name:string,slug:string}> */
    public function publicDepartments(): array
    {
        return $this->connection()->query(
            'SELECT name, slug FROM departments
             WHERE status = "published" AND published_at IS NOT NULL
               AND published_at <= NOW() AND deleted_at IS NULL
             ORDER BY display_order, name'
        )->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function activeImages(): array
    {
        return $this->connection()->query(
            'SELECT id, original_name, file_path, alt_text FROM media
             WHERE media_type = "image" AND status = "active"
               AND deleted_at IS NULL ORDER BY original_name'
        )->fetchAll();
    }

    /** @return array{items:list<array<string,mixed>>,total:int,page:int,pages:int} */
    public function paginateAdmin(int $userId, string $search, string $status, int $page, int $perPage = 12): array
    {
        $conditions = ['p.deleted_at IS NULL', $this->scopeCondition($userId, 'p')];
        $parameters = [];
        if ($search !== '') {
            $conditions[] = '(p.name LIKE :name OR p.programme_code LIKE :code OR p.award_title LIKE :award)';
            $parameters = ['name' => '%' . $search . '%', 'code' => '%' . $search . '%', 'award' => '%' . $search . '%'];
        }
        if ($status !== '') {
            $conditions[] = 'p.status = :status';
            $parameters['status'] = $status;
        }
        $where = implode(' AND ', $conditions);
        $count = $this->connection()->prepare('SELECT COUNT(*) FROM programmes p WHERE ' . $where);
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $statement = $this->connection()->prepare(sprintf(
            'SELECT p.id, p.programme_code, p.name, p.status, p.updated_at,
                    pl.name AS level_name, d.name AS department_name
             FROM programmes p
             INNER JOIN programme_levels pl ON pl.id = p.programme_level_id
             LEFT JOIN programme_departments pd ON pd.programme_id = p.id AND pd.is_lead_department = 1
             LEFT JOIN departments d ON d.id = pd.department_id
             WHERE %s ORDER BY p.display_order, p.name LIMIT %d OFFSET %d',
            $where,
            $perPage,
            ($page - 1) * $perPage
        ));
        $statement->execute($parameters);
        return ['items' => $statement->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /** @return array<string,mixed>|null */
    public function findAdmin(int $id, int $userId): ?array
    {
        $statement = $this->connection()->prepare(
            'SELECT p.*, pl.name AS level_name, pd.department_id,
                    d.name AS department_name, m.file_path AS hero_path,
                    m.alt_text AS hero_alt_text
             FROM programmes p
             INNER JOIN programme_levels pl ON pl.id = p.programme_level_id
             LEFT JOIN programme_departments pd ON pd.programme_id = p.id AND pd.is_lead_department = 1
             LEFT JOIN departments d ON d.id = pd.department_id
             LEFT JOIN media m ON m.id = p.hero_media_id
                AND m.status = "active" AND m.deleted_at IS NULL
             WHERE p.id = :id AND p.deleted_at IS NULL
               AND ' . $this->scopeCondition($userId, 'p') . ' LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    public function findForUpdate(int $id): ?array
    {
        $statement = $this->connection()->prepare(
            'SELECT * FROM programmes WHERE id = :id AND deleted_at IS NULL FOR UPDATE'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    public function findPublishedBySlug(string $slug): ?array
    {
        $statement = $this->connection()->prepare(
            'SELECT p.*, pl.name AS level_name, d.name AS department_name,
                    d.slug AS department_slug, m.file_path AS hero_path,
                    m.alt_text AS hero_alt_text
             FROM programmes p
             INNER JOIN programme_levels pl ON pl.id = p.programme_level_id
             LEFT JOIN programme_departments pd ON pd.programme_id = p.id AND pd.is_lead_department = 1
             LEFT JOIN departments d ON d.id = pd.department_id
             LEFT JOIN media m ON m.id = p.hero_media_id
                AND m.status = "active" AND m.deleted_at IS NULL
             WHERE p.slug = :slug AND p.status = "published"
               AND p.published_at IS NOT NULL AND p.published_at <= NOW()
               AND p.deleted_at IS NULL LIMIT 1'
        );
        $statement->execute(['slug' => $slug]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return array{items:list<array<string,mixed>>,total:int,page:int,pages:int} */
    public function paginatePublished(string $search, string $category, string $department, int $page, int $perPage = 12): array
    {
        $conditions = ['p.status = "published"', 'p.published_at IS NOT NULL', 'p.published_at <= NOW()', 'p.deleted_at IS NULL'];
        $parameters = [];
        if ($search !== '') {
            $conditions[] = '(p.name LIKE :name OR p.programme_code LIKE :code OR p.overview LIKE :overview)';
            $parameters = ['name' => '%' . $search . '%', 'code' => '%' . $search . '%', 'overview' => '%' . $search . '%'];
        }
        if ($category === 'undergraduate') {
            $conditions[] = 'pl.code = "UG"';
        } elseif ($category === 'postgraduate') {
            $conditions[] = 'pl.code IN ("PGD", "MASTERS", "PHD")';
        }
        if ($department !== '') {
            $conditions[] = 'EXISTS (
                SELECT 1 FROM programme_departments filter_pd
                INNER JOIN departments filter_d ON filter_d.id = filter_pd.department_id
                WHERE filter_pd.programme_id = p.id AND filter_d.slug = :department
                  AND filter_d.status = "published" AND filter_d.deleted_at IS NULL
            )';
            $parameters['department'] = $department;
        }
        $where = implode(' AND ', $conditions);
        $count = $this->connection()->prepare(
            'SELECT COUNT(*) FROM programmes p INNER JOIN programme_levels pl ON pl.id = p.programme_level_id WHERE ' . $where
        );
        $count->execute($parameters);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);
        $statement = $this->connection()->prepare(sprintf(
            'SELECT p.id, p.programme_code, p.name, p.award_title, p.slug,
                    p.overview, p.duration_text, p.duration_years, p.study_mode,
                    p.delivery_mode, pl.name AS level_name, d.name AS department_name,
                    m.file_path AS hero_path, m.alt_text AS hero_alt_text
             FROM programmes p
             INNER JOIN programme_levels pl ON pl.id = p.programme_level_id
             LEFT JOIN programme_departments pd ON pd.programme_id = p.id AND pd.is_lead_department = 1
             LEFT JOIN departments d ON d.id = pd.department_id
             LEFT JOIN media m ON m.id = p.hero_media_id
                AND m.status = "active" AND m.deleted_at IS NULL
             WHERE %s ORDER BY pl.display_order, p.display_order, p.name LIMIT %d OFFSET %d',
            $where,
            $perPage,
            ($page - 1) * $perPage
        ));
        $statement->execute($parameters);
        return ['items' => $statement->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        return $this->uniqueExists('slug', $slug, $exceptId);
    }

    public function codeExists(string $code, ?int $exceptId = null): bool
    {
        return $this->uniqueExists('programme_code', $code, $exceptId);
    }

    public function relationExists(string $table, int $id): bool
    {
        if (!in_array($table, ['programme_levels', 'departments', 'media'], true)) {
            return false;
        }
        $extra = $table === 'programme_levels'
            ? ' AND is_active = 1'
            : ($table === 'departments'
                ? ' AND deleted_at IS NULL'
                : ' AND media_type = "image" AND status = "active" AND deleted_at IS NULL');
        $statement = $this->connection()->prepare(sprintf('SELECT 1 FROM %s WHERE id = :id%s LIMIT 1', $table, $extra));
        $statement->execute(['id' => $id]);
        return $statement->fetchColumn() !== false;
    }

    /** @param array<string,mixed> $data */
    public function create(array $data): int
    {
        $fields = array_keys($data);
        $statement = $this->connection()->prepare(sprintf(
            'INSERT INTO programmes (%s, status) VALUES (%s, "draft")',
            implode(', ', $fields),
            implode(', ', array_map(static fn (string $field): string => ':' . $field, $fields))
        ));
        $statement->execute($data);
        return (int) $this->connection()->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public function update(int $id, array $data): void
    {
        $sets = array_map(static fn (string $field): string => $field . ' = :' . $field, array_keys($data));
        $statement = $this->connection()->prepare(
            'UPDATE programmes SET ' . implode(', ', $sets) . ' WHERE id = :id AND deleted_at IS NULL'
        );
        $statement->execute([...$data, 'id' => $id]);
    }

    public function replaceLeadDepartment(int $programmeId, int $departmentId): void
    {
        $this->connection()->prepare('DELETE FROM programme_departments WHERE programme_id = :id')->execute(['id' => $programmeId]);
        $this->connection()->prepare(
            'INSERT INTO programme_departments (programme_id, department_id, is_lead_department)
             VALUES (:programme_id, :department_id, 1)'
        )->execute(['programme_id' => $programmeId, 'department_id' => $departmentId]);
    }

    public function changeStatus(int $id, string $status): void
    {
        $published = $status === 'published' ? ', published_at = NOW()' : ($status === 'draft' ? ', published_at = NULL' : '');
        $statement = $this->connection()->prepare(sprintf('UPDATE programmes SET status = :status%s WHERE id = :id', $published));
        $statement->execute(['status' => $status, 'id' => $id]);
    }

    /** @param array<string,mixed>|null $previous @param array<string,mixed> $revised */
    public function recordRevision(int $id, ?array $previous, array $revised, int $userId, string $note): void
    {
        $number = $this->connection()->prepare(
            'SELECT COALESCE(MAX(revision_number),0)+1 FROM content_revisions WHERE entity_type = "programme" AND entity_id = :id'
        );
        $number->execute(['id' => $id]);
        $statement = $this->connection()->prepare(
            'INSERT INTO content_revisions (entity_type, entity_id, revision_number, previous_data, revised_data, revised_by, revision_note)
             VALUES ("programme", :id, :number, :previous, :revised, :user, :note)'
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
            'INSERT INTO content_approvals (entity_type, entity_id, action, acted_by, comment)
             VALUES ("programme", :id, :action, :user, :comment)'
        );
        $statement->execute(['id' => $id, 'action' => $action, 'user' => $userId, 'comment' => $comment !== '' ? $comment : null]);
    }

    /** @return list<array<string,mixed>> */
    public function approvalHistory(int $id): array
    {
        $statement = $this->connection()->prepare(
            'SELECT ca.*, u.name AS actor_name FROM content_approvals ca
             LEFT JOIN users u ON u.id = ca.acted_by
             WHERE ca.entity_type = "programme" AND ca.entity_id = :id
             ORDER BY ca.created_at DESC, ca.id DESC'
        );
        $statement->execute(['id' => $id]);
        return $statement->fetchAll();
    }

    public function canAccess(int $userId, int $programmeId): bool
    {
        if ($this->scope->hasGlobalScope($userId)) {
            return true;
        }
        $ids = $this->scope->departmentIds($userId);
        if ($ids === []) {
            return false;
        }
        $statement = $this->connection()->prepare(sprintf(
            'SELECT 1 FROM programme_departments WHERE programme_id = :id AND department_id IN (%s) LIMIT 1',
            implode(',', array_map('intval', $ids))
        ));
        $statement->execute(['id' => $programmeId]);
        return $statement->fetchColumn() !== false;
    }

    public function canUseDepartment(int $userId, int $departmentId): bool
    {
        return $this->scope->hasGlobalScope($userId) || in_array($departmentId, $this->scope->departmentIds($userId), true);
    }

    public function connection(): PDO
    {
        return $this->database->connection();
    }

    private function uniqueExists(string $field, string $value, ?int $exceptId): bool
    {
        $sql = sprintf('SELECT 1 FROM programmes WHERE %s = :value AND deleted_at IS NULL', $field);
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
            'EXISTS (SELECT 1 FROM programme_departments scoped_pd WHERE scoped_pd.programme_id = %s.id AND scoped_pd.department_id IN (%s))',
            $alias,
            implode(',', array_map('intval', $ids))
        );
    }
}
