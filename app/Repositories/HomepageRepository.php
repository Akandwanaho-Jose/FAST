<?php

declare(strict_types=1);

namespace FastWebsite\Repositories;

use FastWebsite\Core\Database;

final class HomepageRepository
{
    public function __construct(private readonly Database$db){}
    /** @return array<string,mixed>|null */public function faculty():?array{$r=$this->db->connection()->query('SELECT * FROM faculties WHERE status="published" AND deleted_at IS NULL ORDER BY id LIMIT 1')->fetch();return is_array($r)?$r:null;}
    /** @return list<array<string,mixed>> */public function slides():array{return$this->db->connection()->query('SELECT h.*,m.file_path,m.alt_text,mm.file_path AS mobile_file_path,mm.alt_text AS mobile_alt_text FROM hero_slides h INNER JOIN media m ON m.id=h.media_id AND m.status="active" AND m.deleted_at IS NULL LEFT JOIN media mm ON mm.id=h.mobile_media_id AND mm.status="active" AND mm.deleted_at IS NULL WHERE h.is_active=1 AND (h.starts_at IS NULL OR h.starts_at<=NOW()) AND (h.ends_at IS NULL OR h.ends_at>=NOW()) ORDER BY h.display_order,h.id LIMIT 3')->fetchAll();}
    /** @return array<string,int> */public function counts():array{$p=$this->db->connection();$q=['departments'=>'SELECT COUNT(*) FROM departments WHERE status="published" AND deleted_at IS NULL','programmes'=>'SELECT COUNT(*) FROM programmes WHERE status="published" AND deleted_at IS NULL','staff'=>'SELECT COUNT(*) FROM staff WHERE status="published" AND deleted_at IS NULL','research'=>'SELECT COUNT(*) FROM research_units WHERE status="published" AND deleted_at IS NULL'];$out=[];foreach($q as$k=>$sql)$out[$k]=(int)$p->query($sql)->fetchColumn();return$out;}
    /** @return list<array<string,mixed>> */public function featuredDepartments():array{return$this->db->connection()->query('SELECT d.name,d.slug,d.short_name,d.overview,m.file_path AS hero_path,m.alt_text AS hero_alt_text FROM departments d LEFT JOIN media m ON m.id=d.hero_media_id AND m.status="active" AND m.deleted_at IS NULL WHERE d.status="published" AND d.published_at IS NOT NULL AND d.published_at<=NOW() AND d.deleted_at IS NULL ORDER BY d.display_order,d.name LIMIT 5')->fetchAll();}
    /** @return list<array<string,mixed>> */public function featuredProgrammes():array{return$this->db->connection()->query('SELECT p.name,p.slug,p.programme_code,p.duration_text,p.overview,pl.name AS level_name,d.name AS department_name,m.file_path AS hero_path,m.alt_text AS hero_alt_text FROM programmes p INNER JOIN programme_levels pl ON pl.id=p.programme_level_id LEFT JOIN programme_departments pd ON pd.programme_id=p.id AND pd.is_lead_department=1 LEFT JOIN departments d ON d.id=pd.department_id LEFT JOIN media m ON m.id=p.hero_media_id AND m.status="active" AND m.deleted_at IS NULL WHERE p.status="published" AND p.published_at IS NOT NULL AND p.published_at<=NOW() AND p.deleted_at IS NULL ORDER BY p.display_order,p.name LIMIT 3')->fetchAll();}
    /** @return array{undergraduate:int,postgraduate:int} */
    public function programmeCategoryCounts(): array
    {
        $rows = $this->db->connection()->query(
            'SELECT CASE WHEN pl.code = "UG" THEN "undergraduate" ELSE "postgraduate" END AS category,
                    COUNT(*) AS total
             FROM programmes p INNER JOIN programme_levels pl ON pl.id = p.programme_level_id
             WHERE p.status = "published" AND p.published_at IS NOT NULL
               AND p.published_at <= NOW() AND p.deleted_at IS NULL
               AND pl.code IN ("UG", "PGD", "MASTERS", "PHD")
             GROUP BY category'
        )->fetchAll();
        $counts = ['undergraduate' => 0, 'postgraduate' => 0];
        foreach ($rows as $row) {
            $counts[(string) $row['category']] = (int) $row['total'];
        }
        return $counts;
    }
    /** @return list<array<string,mixed>> */
    public function sections(bool $includeDisabled = false): array
    {
        $where = $includeDisabled ? '1=1' : 'is_enabled = 1';
        return $this->db->connection()->query(
            'SELECT * FROM homepage_sections WHERE ' . $where . ' ORDER BY display_order, id'
        )->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function quickLinks(bool $includeDisabled = false): array
    {
        $where = $includeDisabled ? '1=1' : 'is_active = 1';
        return $this->db->connection()->query(
            'SELECT * FROM homepage_quick_links WHERE ' . $where . ' ORDER BY display_order, id'
        )->fetchAll();
    }

    /** @param array<string,mixed> $data */
    public function updateSection(string $key, array $data): void
    {
        $statement = $this->db->connection()->prepare(
            'UPDATE homepage_sections SET heading = :heading, introduction = :introduction,
                    display_order = :display_order, is_enabled = :is_enabled
             WHERE section_key = :section_key'
        );
        $statement->execute([...$data, 'section_key' => $key]);
    }

    /** @param array<string,mixed> $data */
    public function updateQuickLink(int $id, array $data): void
    {
        $statement = $this->db->connection()->prepare(
            'UPDATE homepage_quick_links SET label = :label, description = :description,
                    link_url = :link_url, display_order = :display_order, is_active = :is_active
             WHERE id = :id'
        );
        $statement->execute([...$data, 'id' => $id]);
    }
    /** @return list<array<string,mixed>> */
    public function adminSlides(): array
    {
        return $this->db->connection()->query(
            'SELECT h.*, m.original_name AS image_name, m.file_path AS image_path,
                    m.alt_text AS image_alt_text, mm.original_name AS mobile_image_name
             FROM hero_slides h
             INNER JOIN media m ON m.id = h.media_id
             LEFT JOIN media mm ON mm.id = h.mobile_media_id
             ORDER BY h.display_order, h.id'
        )->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public function activeImages(): array
    {
        return $this->db->connection()->query(
            'SELECT id, original_name, file_path, alt_text, width, height
             FROM media WHERE media_type = "image" AND status = "active"
               AND deleted_at IS NULL ORDER BY created_at DESC, original_name'
        )->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function findSlide(int $id): ?array
    {
        $statement = $this->db->connection()->prepare('SELECT * FROM hero_slides WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    public function activeImageExists(int $id): bool
    {
        $statement = $this->db->connection()->prepare(
            'SELECT 1 FROM media WHERE id = :id AND media_type = "image"
             AND status = "active" AND deleted_at IS NULL LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        return $statement->fetchColumn() !== false;
    }

    /** @param array<string,mixed> $data */
    public function saveSlide(?int $id, array $data): int
    {
        if ($id === null) {
            $fields = array_keys($data);
            $statement = $this->db->connection()->prepare(sprintf(
                'INSERT INTO hero_slides (%s) VALUES (%s)',
                implode(', ', $fields),
                implode(', ', array_map(static fn (string $field): string => ':' . $field, $fields))
            ));
            $statement->execute($data);
            return (int) $this->db->connection()->lastInsertId();
        }

        $sets = array_map(static fn (string $field): string => $field . ' = :' . $field, array_keys($data));
        $statement = $this->db->connection()->prepare(
            'UPDATE hero_slides SET ' . implode(', ', $sets) . ' WHERE id = :id'
        );
        $statement->execute([...$data, 'id' => $id]);
        return $id;
    }

    public function connection(): \PDO
    {
        return $this->db->connection();
    }
}
