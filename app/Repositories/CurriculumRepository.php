<?php

declare(strict_types=1);

namespace FastWebsite\Repositories;

use FastWebsite\Core\Database;
use PDO;

final class CurriculumRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    /** @return array<string,mixed>|null */
    public function versionForAdmin(int $programmeId): ?array
    {
        $statement = $this->connection()->prepare(
            'SELECT cv.* FROM curriculum_versions cv
             WHERE cv.programme_id = :programme_id
             ORDER BY cv.is_current DESC, cv.effective_year DESC, cv.id DESC LIMIT 1'
        );
        $statement->execute(['programme_id' => $programmeId]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    public function versionForUpdate(int $versionId): ?array
    {
        $statement = $this->connection()->prepare(
            'SELECT * FROM curriculum_versions WHERE id = :id FOR UPDATE'
        );
        $statement->execute(['id' => $versionId]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    public function publishedVersion(int $programmeId): ?array
    {
        $statement = $this->connection()->prepare(
            'SELECT * FROM curriculum_versions
             WHERE programme_id = :programme_id AND is_current = 1 AND status = "published"
             ORDER BY effective_year DESC, id DESC LIMIT 1'
        );
        $statement->execute(['programme_id' => $programmeId]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return list<array<string,mixed>> */
    public function placements(int $versionId, bool $publishedOnly = false): array
    {
        $status = $publishedOnly ? ' AND c.status = "published"' : '';
        $statement = $this->connection()->prepare(
            'SELECT cc.id AS placement_id, cc.curriculum_version_id, cc.study_year,
                    cc.semester, cc.requirement_type, cc.credit_units_override,
                    cc.display_order, c.id AS course_id, c.course_code, c.title,
                    c.slug, c.description, c.default_credit_units, c.course_type,
                    c.status AS course_status
             FROM curriculum_courses cc
             INNER JOIN courses c ON c.id = cc.course_id AND c.deleted_at IS NULL' . $status . '
             WHERE cc.curriculum_version_id = :version_id
             ORDER BY cc.study_year, cc.semester, cc.display_order, c.course_code'
        );
        $statement->execute(['version_id' => $versionId]);
        return $statement->fetchAll();
    }

    /** @param array<string,mixed> $data */
    public function createVersion(int $programmeId, array $data): int
    {
        $statement = $this->connection()->prepare(
            'INSERT INTO curriculum_versions
                (programme_id, version_name, effective_year, expiry_year,
                 approval_reference, approval_date, status, is_current)
             VALUES
                (:programme_id, :version_name, :effective_year, :expiry_year,
                 :approval_reference, :approval_date, "draft", 0)'
        );
        $statement->execute(['programme_id' => $programmeId, ...$data]);
        return (int) $this->connection()->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public function updateVersion(int $versionId, array $data): void
    {
        $statement = $this->connection()->prepare(
            'UPDATE curriculum_versions SET version_name = :version_name,
                    effective_year = :effective_year, expiry_year = :expiry_year,
                    approval_reference = :approval_reference, approval_date = :approval_date
             WHERE id = :id'
        );
        $statement->execute([...$data, 'id' => $versionId]);
    }

    /** @return array<string,mixed>|null */
    public function courseById(int $id): ?array
    {
        $statement = $this->connection()->prepare('SELECT * FROM courses WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    public function courseByCode(string $code): ?array
    {
        $statement = $this->connection()->prepare('SELECT * FROM courses WHERE course_code = :code AND deleted_at IS NULL LIMIT 1');
        $statement->execute(['code' => $code]);
        $row = $statement->fetch();
        return is_array($row) ? $row : null;
    }

    /** @param array<string,mixed> $data */
    public function createCourse(array $data): int
    {
        $statement = $this->connection()->prepare(
            'INSERT INTO courses
                (course_code, title, slug, description, default_credit_units, course_type, status)
             VALUES
                (:course_code, :title, :slug, :description, :default_credit_units, :course_type, :status)'
        );
        $statement->execute($data);
        return (int) $this->connection()->lastInsertId();
    }

    /** @param array<string,mixed> $data */
    public function updateCourse(int $courseId, array $data): void
    {
        $statement = $this->connection()->prepare(
            'UPDATE courses SET course_code = :course_code, title = :title,
                    description = :description, default_credit_units = :default_credit_units,
                    course_type = :course_type, status = :status
             WHERE id = :id AND deleted_at IS NULL'
        );
        $statement->execute([...$data, 'id' => $courseId]);
    }

    /** @param array<string,mixed> $data */
    public function savePlacement(int $versionId, int $courseId, array $data, ?int $placementId): int
    {
        if ($placementId !== null) {
            $statement = $this->connection()->prepare(
                'UPDATE curriculum_courses SET course_id = :course_id,
                        study_year = :study_year, semester = :semester,
                        requirement_type = :requirement_type,
                        credit_units_override = :credit_units_override,
                        display_order = :display_order
                 WHERE id = :id AND curriculum_version_id = :version_id'
            );
            $statement->execute([...$data, 'course_id' => $courseId, 'id' => $placementId, 'version_id' => $versionId]);
            return $placementId;
        }
        $statement = $this->connection()->prepare(
            'INSERT INTO curriculum_courses
                (curriculum_version_id, course_id, study_year, semester,
                 requirement_type, credit_units_override, display_order)
             VALUES
                (:version_id, :course_id, :study_year, :semester,
                 :requirement_type, :credit_units_override, :display_order)'
        );
        $statement->execute([...$data, 'course_id' => $courseId, 'version_id' => $versionId]);
        return (int) $this->connection()->lastInsertId();
    }

    public function deletePlacement(int $versionId, int $placementId): bool
    {
        $statement = $this->connection()->prepare(
            'DELETE FROM curriculum_courses WHERE id = :id AND curriculum_version_id = :version_id'
        );
        $statement->execute(['id' => $placementId, 'version_id' => $versionId]);
        return $statement->rowCount() === 1;
    }

    public function publish(int $programmeId, int $versionId): void
    {
        $this->connection()->prepare(
            'UPDATE curriculum_versions SET is_current = 0 WHERE programme_id = :programme_id AND id <> :id'
        )->execute(['programme_id' => $programmeId, 'id' => $versionId]);
        $this->connection()->prepare(
            'UPDATE courses c INNER JOIN curriculum_courses cc ON cc.course_id = c.id
             SET c.status = "published"
             WHERE cc.curriculum_version_id = :id AND c.deleted_at IS NULL'
        )->execute(['id' => $versionId]);
        $this->connection()->prepare(
            'UPDATE curriculum_versions cv SET cv.status = "published", cv.is_current = 1,
                    cv.total_credit_units = (
                        SELECT SUM(COALESCE(cc.credit_units_override, c.default_credit_units))
                        FROM curriculum_courses cc INNER JOIN courses c ON c.id = cc.course_id
                        WHERE cc.curriculum_version_id = cv.id
                    )
             WHERE cv.id = :id AND cv.programme_id = :programme_id'
        )->execute(['id' => $versionId, 'programme_id' => $programmeId]);
    }

    public function refreshTotal(int $versionId): void
    {
        $this->connection()->prepare(
            'UPDATE curriculum_versions cv SET cv.total_credit_units = (
                SELECT SUM(COALESCE(cc.credit_units_override, c.default_credit_units))
                FROM curriculum_courses cc INNER JOIN courses c ON c.id = cc.course_id
                WHERE cc.curriculum_version_id = cv.id
             ) WHERE cv.id = :id'
        )->execute(['id' => $versionId]);
    }

    public function connection(): PDO
    {
        return $this->database->connection();
    }
}
