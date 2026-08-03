<?php

declare(strict_types=1);

namespace FastWebsite\Repositories;

use FastWebsite\Core\Database;
use PDO;

final class MediaRepository
{
    public function __construct(private readonly Database $database)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createImage(array $data): int
    {
        $statement = $this->connection()->prepare(
            'INSERT INTO media
                (media_type, original_name, stored_name, file_path, mime_type,
                 file_extension, file_size, width, height, alt_text,
                 uploaded_by, status)
             VALUES
                ("image", :original_name, :stored_name, :file_path, :mime_type,
                 :file_extension, :file_size, :width, :height, :alt_text,
                 :uploaded_by, "active")'
        );
        $statement->execute($data);

        return (int) $this->connection()->lastInsertId();
    }

    public function connection(): PDO
    {
        return $this->database->connection();
    }
}
