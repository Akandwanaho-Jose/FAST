<?php

declare(strict_types=1);

use FastWebsite\Core\Database;
use FastWebsite\Repositories\MediaRepository;
use FastWebsite\Services\MediaUploadService;

return static function (): void {
    /** @var Database $database */
    $database = require dirname(__DIR__, 2) . '/bootstrap/database.php';
    $connection = $database->connection();
    $suffix = bin2hex(random_bytes(6));
    $testRoot = rtrim(sys_get_temp_dir(), '/\\')
        . DIRECTORY_SEPARATOR
        . 'fast-media-'
        . $suffix;
    $temporaryPath = $testRoot . DIRECTORY_SEPARATOR . 'source.png';
    $storedPath = null;
    $connection->beginTransaction();

    try {
        if (!mkdir($testRoot, 0755, true) && !is_dir($testRoot)) {
            throw new RuntimeException('Could not create media test directory.');
        }

        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true
        );

        if (!is_string($png) || file_put_contents($temporaryPath, $png) === false) {
            throw new RuntimeException('Could not prepare integration test image.');
        }

        $userId = (int) $connection->query(
            'SELECT id FROM users
             WHERE is_active = 1 AND deleted_at IS NULL
             ORDER BY id
             LIMIT 1'
        )->fetchColumn();

        if ($userId < 1) {
            throw new RuntimeException('Media upload test requires an active user.');
        }

        $service = new MediaUploadService(
            new MediaRepository($database),
            $testRoot,
            5 * 1024 * 1024
        );
        $validation = $service->validate(
            [
                'name' => 'Department laboratory.png',
                'tmp_name' => $temporaryPath,
                'error' => UPLOAD_ERR_OK,
                'size' => filesize($temporaryPath),
            ],
            'Students using equipment in the department laboratory'
        );

        if ($validation['errors'] !== [] || $validation['upload'] === null) {
            throw new RuntimeException('Integration image validation failed.');
        }

        $stored = $service->store($validation['upload'], $userId);
        $storedPath = $stored['absolute_path'];
        $statement = $connection->prepare(
            'SELECT media_type, file_path, mime_type, alt_text, uploaded_by
             FROM media WHERE id = :id'
        );
        $statement->execute(['id' => $stored['id']]);
        $record = $statement->fetch();

        if (!is_array($record)
            || $record['media_type'] !== 'image'
            || $record['mime_type'] !== 'image/png'
            || !str_starts_with(
                (string) $record['file_path'],
                'public/uploads/departments/'
            )
            || (int) $record['uploaded_by'] !== $userId
            || !is_file($storedPath)
        ) {
            throw new RuntimeException(
                'Uploaded image path or media metadata was not stored correctly.'
            );
        }
    } finally {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }

        if (is_string($storedPath) && is_file($storedPath)) {
            unlink($storedPath);
        }

        if (is_file($temporaryPath)) {
            unlink($temporaryPath);
        }

        $candidate = is_string($storedPath) ? dirname($storedPath) : null;
        $resolvedRoot = realpath($testRoot);

        while (is_string($candidate)
            && is_string($resolvedRoot)
            && str_starts_with($candidate, $resolvedRoot)
            && $candidate !== $resolvedRoot
        ) {
            @rmdir($candidate);
            $candidate = dirname($candidate);
        }

        if (is_dir($testRoot)) {
            @rmdir($testRoot);
        }
    }
};
