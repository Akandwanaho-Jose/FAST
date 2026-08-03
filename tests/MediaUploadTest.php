<?php

declare(strict_types=1);

use FastWebsite\Core\Database;
use FastWebsite\Repositories\MediaRepository;
use FastWebsite\Services\MediaUploadService;

return static function (): void {
    $temporaryPath = tempnam(sys_get_temp_dir(), 'fast-image-');

    if ($temporaryPath === false) {
        throw new RuntimeException('Could not create upload test file.');
    }

    try {
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true
        );

        if (!is_string($png) || file_put_contents($temporaryPath, $png) === false) {
            throw new RuntimeException('Could not prepare upload test image.');
        }

        $service = new MediaUploadService(
            new MediaRepository(new Database([])),
            sys_get_temp_dir(),
            20 * 1024 * 1024
        );
        $file = [
            'name' => '../Unsafe department.png',
            'tmp_name' => $temporaryPath,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($temporaryPath),
        ];
        $valid = $service->validate($file, 'Students working in a laboratory');

        if ($valid['errors'] !== []
            || $valid['upload'] === null
            || $valid['upload']['mime_type'] !== 'image/png'
            || $valid['upload']['original_name'] !== 'Unsafe department.png'
        ) {
            throw new RuntimeException('A valid image upload was rejected.');
        }

        $hdFile = $file;
        $hdFile['size'] = 6 * 1024 * 1024;
        $hd = $service->validate($hdFile, 'High-resolution faculty photograph');
        if ($hd['errors'] !== [] || $hd['upload'] === null) {
            throw new RuntimeException('An image larger than the former 5 MB limit was rejected.');
        }

        $oversizedFile = $file;
        $oversizedFile['size'] = 21 * 1024 * 1024;
        if ($service->validate($oversizedFile, 'Oversized photograph')['errors'] === []) {
            throw new RuntimeException('An image larger than the 20 MB policy was accepted.');
        }

        $missingDescription = $service->validate($file, '');

        if ($missingDescription['errors'] === []) {
            throw new RuntimeException(
                'An uploaded image without a description was accepted.'
            );
        }

        $noFile = $service->validate(
            ['error' => UPLOAD_ERR_NO_FILE],
            ''
        );

        if ($noFile['upload'] !== null || $noFile['errors'] !== []) {
            throw new RuntimeException('An empty optional upload was rejected.');
        }
    } finally {
        if (is_file($temporaryPath)) {
            unlink($temporaryPath);
        }
    }
};
