<?php

declare(strict_types=1);

namespace FastWebsite\Repositories;

use FastWebsite\Core\Database;

final class SiteSettingsRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    /** @return array<string,string> */
    public function publicMap(): array
    {
        $rows = $this->database->connection()->query(
            'SELECT setting_key, setting_value FROM site_settings
             WHERE is_public = 1 ORDER BY setting_key'
        )->fetchAll();
        $settings = [];
        foreach ($rows as $row) {
            $settings[(string) $row['setting_key']] = (string) ($row['setting_value'] ?? '');
        }
        return $settings;
    }

    /** @return list<array<string,mixed>> */
    public function all(): array
    {
        return $this->database->connection()->query(
            'SELECT id, setting_key, setting_value, value_type, is_public
             FROM site_settings WHERE is_public = 1 ORDER BY setting_key'
        )->fetchAll();
    }

    /** @param array<int,string> $values */
    public function updateMany(array $values, int $userId): int
    {
        $connection = $this->database->connection();
        $statement = $connection->prepare(
            'UPDATE site_settings SET setting_value = :setting_value, updated_by = :updated_by
             WHERE id = :id AND is_public = 1'
        );
        $updated = 0;
        $connection->beginTransaction();
        try {
            foreach ($values as $id => $value) {
                $statement->execute([
                    'setting_value' => mb_substr(trim($value), 0, 10000),
                    'updated_by' => $userId,
                    'id' => $id,
                ]);
                $updated += $statement->rowCount();
            }
            $connection->commit();
        } catch (\Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }
            throw $exception;
        }
        return $updated;
    }

    /** @return array{departments:list<array<string,mixed>>,undergraduate:list<array<string,mixed>>,postgraduate:list<array<string,mixed>>} */
    public function navigation(): array
    {
        $connection = $this->database->connection();
        $departments = $connection->query(
            'SELECT name, slug FROM departments WHERE status = "published"
             AND published_at IS NOT NULL AND published_at <= NOW() AND deleted_at IS NULL
             ORDER BY display_order, name'
        )->fetchAll();
        $programmes = $connection->query(
            'SELECT p.name, p.slug,
                    CASE WHEN pl.code = "UG" THEN "undergraduate" ELSE "postgraduate" END AS category
             FROM programmes p INNER JOIN programme_levels pl ON pl.id = p.programme_level_id
             WHERE p.status = "published" AND p.published_at IS NOT NULL
               AND p.published_at <= NOW() AND p.deleted_at IS NULL
             ORDER BY p.display_order, p.name'
        )->fetchAll();
        return [
            'departments' => $departments,
            'undergraduate' => array_values(array_filter($programmes, static fn (array $item): bool => $item['category'] === 'undergraduate')),
            'postgraduate' => array_values(array_filter($programmes, static fn (array $item): bool => $item['category'] === 'postgraduate')),
        ];
    }
}
