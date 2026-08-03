<?php

declare(strict_types=1);

namespace FastWebsite\Repositories;

use FastWebsite\Core\Database;
use FastWebsite\Services\DepartmentScopeService;
use PDO;

final class ResearchMetadataRepository
{
    public function __construct(
        private readonly Database $database,
        private readonly DepartmentScopeService $scope
    ) {
    }

    /** @return list<array<string,mixed>> */
    public function departments(int $userId): array
    {
        $where = $this->scope->hasGlobalScope($userId)
            ? '1=1'
            : $this->idCondition('id', $this->scope->departmentIds($userId));
        return $this->connection()->query(
            'SELECT id,name FROM departments WHERE deleted_at IS NULL AND ' . $where . ' ORDER BY name'
        )->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function themes(int $userId, bool $publishedOnly = false): array
    {
        $conditions = [$this->themeScope($userId, 'rt')];
        if ($publishedOnly) {
            $conditions[] = 'rt.status="published"';
            $conditions[] = 'rt.published_at IS NOT NULL';
            $conditions[] = 'rt.published_at<=NOW()';
        }
        $sql = 'SELECT rt.*,parent.name AS parent_name,
                       GROUP_CONCAT(DISTINCT d.name ORDER BY rtd.is_primary DESC,d.name SEPARATOR ", ") AS department_names
                FROM research_themes rt
                LEFT JOIN research_themes parent ON parent.id=rt.parent_theme_id
                LEFT JOIN research_theme_departments rtd ON rtd.research_theme_id=rt.id
                LEFT JOIN departments d ON d.id=rtd.department_id AND d.deleted_at IS NULL
                WHERE ' . implode(' AND ', $conditions) . '
                GROUP BY rt.id ORDER BY rt.display_order,rt.name';
        return $this->connection()->query($sql)->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function findTheme(int $id, int $userId): ?array
    {
        $statement = $this->connection()->prepare(
            'SELECT rt.*,rtd.department_id,rtd.is_primary
             FROM research_themes rt
             LEFT JOIN research_theme_departments rtd ON rtd.research_theme_id=rt.id AND rtd.is_primary=1
             WHERE rt.id=:id AND ' . $this->themeScope($userId, 'rt') . ' LIMIT 1'
        );
        $statement->execute(['id'=>$id]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    public function themeSlugExists(string $slug, ?int $exceptId = null): bool
    {
        $sql = 'SELECT 1 FROM research_themes WHERE slug=:slug';
        $parameters = ['slug'=>$slug];
        if ($exceptId !== null) {
            $sql .= ' AND id<>:id';
            $parameters['id'] = $exceptId;
        }
        $statement = $this->connection()->prepare($sql . ' LIMIT 1');
        $statement->execute($parameters);
        return $statement->fetchColumn() !== false;
    }

    /** @param array<string,mixed> $data */
    public function createTheme(array $data, int $departmentId): int
    {
        $fields = array_keys($data);
        $statement = $this->connection()->prepare(sprintf(
            'INSERT INTO research_themes (%s,status) VALUES (%s,"draft")',
            implode(',', $fields),
            implode(',', array_map(static fn (string $field): string => ':' . $field, $fields))
        ));
        $statement->execute($data);
        $id = (int) $this->connection()->lastInsertId();
        $this->replaceThemeDepartment($id, $departmentId);
        return $id;
    }

    /** @param array<string,mixed> $data */
    public function updateTheme(int $id, array $data, int $departmentId): void
    {
        $sets = array_map(static fn (string $field): string => $field . '=:' . $field, array_keys($data));
        $statement = $this->connection()->prepare(
            'UPDATE research_themes SET ' . implode(',', $sets) . ' WHERE id=:id'
        );
        $statement->execute([...$data, 'id'=>$id]);
        $this->replaceThemeDepartment($id, $departmentId);
    }

    public function changeThemeStatus(int $id, string $status): void
    {
        $published = $status === 'published'
            ? ',published_at=NOW()'
            : ($status === 'draft' ? ',published_at=NULL' : '');
        $statement = $this->connection()->prepare(
            'UPDATE research_themes SET status=:status' . $published . ' WHERE id=:id'
        );
        $statement->execute(['status'=>$status, 'id'=>$id]);
    }

    /** @return list<array<string,mixed>> */
    public function partners(bool $publishedOnly = false): array
    {
        $where = $publishedOnly ? ' WHERE p.status="published"' : '';
        return $this->connection()->query(
            'SELECT p.*,m.file_path AS logo_path,m.alt_text AS logo_alt_text
             FROM partners p LEFT JOIN media m ON m.id=p.logo_media_id AND m.status="active" AND m.deleted_at IS NULL' .
             $where . ' ORDER BY p.name'
        )->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function activeImages(): array
    {
        return $this->connection()->query(
            'SELECT id,original_name,file_path,alt_text FROM media
             WHERE media_type="image" AND status="active" AND deleted_at IS NULL ORDER BY original_name'
        )->fetchAll();
    }

    public function activeImageExists(int $id): bool
    {
        $statement = $this->connection()->prepare(
            'SELECT 1 FROM media WHERE id=:id AND media_type="image" AND status="active" AND deleted_at IS NULL LIMIT 1'
        );
        $statement->execute(['id'=>$id]);
        return $statement->fetchColumn() !== false;
    }

    /** @return array<string,mixed>|null */
    public function findPartner(int $id): ?array
    {
        $statement = $this->connection()->prepare('SELECT * FROM partners WHERE id=:id LIMIT 1');
        $statement->execute(['id'=>$id]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    public function partnerSlugExists(string $slug, ?int $exceptId = null): bool
    {
        $sql = 'SELECT 1 FROM partners WHERE slug=:slug';
        $parameters = ['slug'=>$slug];
        if ($exceptId !== null) {
            $sql .= ' AND id<>:id';
            $parameters['id'] = $exceptId;
        }
        $statement = $this->connection()->prepare($sql . ' LIMIT 1');
        $statement->execute($parameters);
        return $statement->fetchColumn() !== false;
    }

    /** @param array<string,mixed> $data */
    public function createPartner(array $data): int
    {
        $fields = array_keys($data);
        $statement = $this->connection()->prepare(sprintf(
            'INSERT INTO partners (%s,status) VALUES (%s,"draft")',
            implode(',', $fields),
            implode(',', array_map(static fn (string $field): string => ':' . $field, $fields))
        ));
        $statement->execute($data);
        return (int) $this->connection()->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public function updatePartner(int $id, array $data): void
    {
        $sets = array_map(static fn (string $field): string => $field . '=:' . $field, array_keys($data));
        $statement = $this->connection()->prepare('UPDATE partners SET ' . implode(',', $sets) . ' WHERE id=:id');
        $statement->execute([...$data, 'id'=>$id]);
    }

    public function changePartnerStatus(int $id, string $status): void
    {
        $statement = $this->connection()->prepare('UPDATE partners SET status=:status WHERE id=:id');
        $statement->execute(['status'=>$status, 'id'=>$id]);
    }

    /** @return list<array<string,mixed>> */
    public function sdgs(): array
    {
        return $this->connection()->query('SELECT id,code,name,colour_code FROM sdgs ORDER BY id')->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function projectThemes(int $projectId, bool $publishedOnly = false): array
    {
        $extra = $publishedOnly ? ' AND rt.status="published" AND rt.published_at IS NOT NULL AND rt.published_at<=NOW()' : '';
        $statement = $this->connection()->prepare(
            'SELECT rt.id,rt.name,rt.slug,rt.description,pt.is_primary
             FROM project_themes pt INNER JOIN research_themes rt ON rt.id=pt.research_theme_id' . $extra . '
             WHERE pt.project_id=:id ORDER BY pt.is_primary DESC,rt.display_order,rt.name'
        );
        $statement->execute(['id'=>$projectId]);
        return $statement->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function projectSdgs(int $projectId): array
    {
        $statement = $this->connection()->prepare(
            'SELECT s.id,s.code,s.name,s.colour_code,ps.is_primary
             FROM project_sdgs ps INNER JOIN sdgs s ON s.id=ps.sdg_id
             WHERE ps.project_id=:id ORDER BY ps.is_primary DESC,s.id'
        );
        $statement->execute(['id'=>$projectId]);
        return $statement->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function projectPartners(int $projectId, bool $publishedOnly = false): array
    {
        $extra = $publishedOnly ? ' AND p.status="published"' : '';
        $statement = $this->connection()->prepare(
            'SELECT pp.id AS link_id,pp.partner_role,pp.contribution,p.id,p.name,p.slug,p.partner_type,p.country,p.website_url,p.description,
                    m.file_path AS logo_path,m.alt_text AS logo_alt_text
             FROM project_partners pp INNER JOIN partners p ON p.id=pp.partner_id' . $extra . '
             LEFT JOIN media m ON m.id=p.logo_media_id AND m.status="active" AND m.deleted_at IS NULL
             WHERE pp.project_id=:id ORDER BY FIELD(pp.partner_role,"lead","funder","implementer","technical_partner","academic_partner","industry_partner","community_partner","other"),p.name'
        );
        $statement->execute(['id'=>$projectId]);
        return $statement->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function publicationThemes(int $publicationId, bool $publishedOnly = false): array
    {
        $extra = $publishedOnly ? ' AND rt.status="published" AND rt.published_at IS NOT NULL AND rt.published_at<=NOW()' : '';
        $statement = $this->connection()->prepare(
            'SELECT rt.id,rt.name,rt.slug,rt.description
             FROM publication_themes pt INNER JOIN research_themes rt ON rt.id=pt.research_theme_id' . $extra . '
             WHERE pt.publication_id=:id ORDER BY rt.display_order,rt.name'
        );
        $statement->execute(['id'=>$publicationId]);
        return $statement->fetchAll();
    }

    public function addProjectTheme(int $projectId, int $themeId, bool $primary): void
    {
        if ($primary) {
            $this->connection()->prepare('UPDATE project_themes SET is_primary=0 WHERE project_id=:id')->execute(['id'=>$projectId]);
        }
        $statement = $this->connection()->prepare(
            'INSERT INTO project_themes(project_id,research_theme_id,is_primary) VALUES(:project,:theme,:primary)
             ON DUPLICATE KEY UPDATE is_primary=VALUES(is_primary)'
        );
        $statement->execute(['project'=>$projectId, 'theme'=>$themeId, 'primary'=>$primary ? 1 : 0]);
    }

    public function removeProjectTheme(int $projectId, int $themeId): bool
    {
        $statement = $this->connection()->prepare('DELETE FROM project_themes WHERE project_id=:project AND research_theme_id=:theme');
        $statement->execute(['project'=>$projectId, 'theme'=>$themeId]);
        return $statement->rowCount() === 1;
    }

    public function addProjectSdg(int $projectId, int $sdgId, bool $primary): void
    {
        if ($primary) {
            $this->connection()->prepare('UPDATE project_sdgs SET is_primary=0 WHERE project_id=:id')->execute(['id'=>$projectId]);
        }
        $statement = $this->connection()->prepare(
            'INSERT INTO project_sdgs(project_id,sdg_id,is_primary) VALUES(:project,:sdg,:primary)
             ON DUPLICATE KEY UPDATE is_primary=VALUES(is_primary)'
        );
        $statement->execute(['project'=>$projectId, 'sdg'=>$sdgId, 'primary'=>$primary ? 1 : 0]);
    }

    public function removeProjectSdg(int $projectId, int $sdgId): bool
    {
        $statement = $this->connection()->prepare('DELETE FROM project_sdgs WHERE project_id=:project AND sdg_id=:sdg');
        $statement->execute(['project'=>$projectId, 'sdg'=>$sdgId]);
        return $statement->rowCount() === 1;
    }

    public function addProjectPartner(int $projectId, int $partnerId, string $role, ?string $contribution): void
    {
        $statement = $this->connection()->prepare(
            'INSERT INTO project_partners(project_id,partner_id,partner_role,contribution) VALUES(:project,:partner,:role,:contribution)
             ON DUPLICATE KEY UPDATE partner_role=VALUES(partner_role),contribution=VALUES(contribution)'
        );
        $statement->execute(['project'=>$projectId, 'partner'=>$partnerId, 'role'=>$role, 'contribution'=>$contribution]);
    }

    public function removeProjectPartner(int $projectId, int $linkId): bool
    {
        $statement = $this->connection()->prepare('DELETE FROM project_partners WHERE id=:id AND project_id=:project');
        $statement->execute(['id'=>$linkId, 'project'=>$projectId]);
        return $statement->rowCount() === 1;
    }

    public function addPublicationTheme(int $publicationId, int $themeId): void
    {
        $statement = $this->connection()->prepare(
            'INSERT IGNORE INTO publication_themes(publication_id,research_theme_id) VALUES(:publication,:theme)'
        );
        $statement->execute(['publication'=>$publicationId, 'theme'=>$themeId]);
    }

    public function removePublicationTheme(int $publicationId, int $themeId): bool
    {
        $statement = $this->connection()->prepare('DELETE FROM publication_themes WHERE publication_id=:publication AND research_theme_id=:theme');
        $statement->execute(['publication'=>$publicationId, 'theme'=>$themeId]);
        return $statement->rowCount() === 1;
    }

    public function publishedThemeExists(int $id): bool
    {
        $statement = $this->connection()->prepare(
            'SELECT 1 FROM research_themes WHERE id=:id AND status="published" AND published_at IS NOT NULL AND published_at<=NOW() LIMIT 1'
        );
        $statement->execute(['id'=>$id]);
        return $statement->fetchColumn() !== false;
    }

    public function publishedPartnerExists(int $id): bool
    {
        $statement = $this->connection()->prepare('SELECT 1 FROM partners WHERE id=:id AND status="published" LIMIT 1');
        $statement->execute(['id'=>$id]);
        return $statement->fetchColumn() !== false;
    }

    public function sdgExists(int $id): bool
    {
        $statement = $this->connection()->prepare('SELECT 1 FROM sdgs WHERE id=:id LIMIT 1');
        $statement->execute(['id'=>$id]);
        return $statement->fetchColumn() !== false;
    }

    public function departmentExists(int $id): bool
    {
        $statement = $this->connection()->prepare('SELECT 1 FROM departments WHERE id=:id AND deleted_at IS NULL LIMIT 1');
        $statement->execute(['id'=>$id]);
        return $statement->fetchColumn() !== false;
    }

    public function canUseDepartment(int $userId, int $departmentId): bool
    {
        return $this->scope->hasGlobalScope($userId)
            || in_array($departmentId, $this->scope->departmentIds($userId), true);
    }

    public function connection(): PDO
    {
        return $this->database->connection();
    }

    private function replaceThemeDepartment(int $themeId, int $departmentId): void
    {
        $this->connection()->prepare('DELETE FROM research_theme_departments WHERE research_theme_id=:id')->execute(['id'=>$themeId]);
        $this->connection()->prepare(
            'INSERT INTO research_theme_departments(research_theme_id,department_id,is_primary) VALUES(:theme,:department,1)'
        )->execute(['theme'=>$themeId, 'department'=>$departmentId]);
    }

    private function themeScope(int $userId, string $alias): string
    {
        if ($this->scope->hasGlobalScope($userId)) {
            return '1=1';
        }
        $ids = $this->scope->departmentIds($userId);
        if ($ids === []) {
            return '1=0';
        }
        return 'EXISTS(SELECT 1 FROM research_theme_departments scoped_rtd WHERE scoped_rtd.research_theme_id=' .
            $alias . '.id AND scoped_rtd.department_id IN(' . implode(',', array_map('intval', $ids)) . '))';
    }

    /** @param list<int> $ids */
    private function idCondition(string $field, array $ids): string
    {
        return $ids === [] ? '1=0' : $field . ' IN(' . implode(',', array_map('intval', $ids)) . ')';
    }
}
