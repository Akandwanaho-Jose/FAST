<?php

declare(strict_types=1);

namespace FastWebsite\Services;

use FastWebsite\Repositories\MediaRepository;
use RuntimeException;

final class MediaUploadService
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    public function __construct(
        private readonly MediaRepository $media,
        private readonly string $publicPath,
        private readonly int $maximumBytes
    ) {
    }

    /**
     * @param array<string, mixed>|null $file
     * @return array{upload: array<string, mixed>|null, errors: list<string>}
     */
    public function validate(?array $file, string $altText): array
    {
        if ($file === null
            || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
        ) {
            return ['upload' => null, 'errors' => []];
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

        if ($error !== UPLOAD_ERR_OK) {
            return [
                'upload' => null,
                'errors' => [$this->uploadErrorMessage($error)],
            ];
        }

        $temporaryPath = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        $altText = trim($altText);
        $errors = [];

        if ($temporaryPath === '' || !is_file($temporaryPath)) {
            $errors[] = 'The selected hero image could not be read.';
        }

        if ($size < 1 || $size > $this->maximumBytes) {
            $errors[] = sprintf(
                'Hero image must be no larger than %d MB.',
                (int) ceil($this->maximumBytes / 1048576)
            );
        }

        if ($altText === '') {
            $errors[] = 'Describe the hero image for visitors using assistive technology.';
        } elseif (mb_strlen($altText) > 255) {
            $errors[] = 'Hero image description must not exceed 255 characters.';
        }

        if ($errors !== []) {
            return ['upload' => null, 'errors' => $errors];
        }

        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
        $dimensions = @getimagesize($temporaryPath);

        if (!is_string($mimeType)
            || !isset(self::ALLOWED_MIME_TYPES[$mimeType])
            || !is_array($dimensions)
        ) {
            return [
                'upload' => null,
                'errors' => [
                    'Hero image must be a valid JPEG, PNG, WebP, or GIF file.',
                ],
            ];
        }

        return [
            'upload' => [
                'tmp_name' => $temporaryPath,
                'original_name' => $this->safeOriginalName(
                    (string) ($file['name'] ?? 'department-image')
                ),
                'mime_type' => $mimeType,
                'extension' => self::ALLOWED_MIME_TYPES[$mimeType],
                'size' => $size,
                'width' => (int) $dimensions[0],
                'height' => (int) $dimensions[1],
                'alt_text' => $altText,
            ],
            'errors' => [],
        ];
    }

    /**
     * @param array<string, mixed> $upload
     * @return array{id: int, absolute_path: string, public_path: string}
     */
    public function store(
        array $upload,
        int $userId,
        string $collection = 'departments'
    ): array
    {
        if (!in_array($collection, ['departments', 'staff', 'programmes', 'innovations', 'impact', 'news', 'events', 'pages', 'media'], true)) {
            throw new RuntimeException('The upload collection is invalid.');
        }

        $directory = sprintf(
            '%s/uploads/%s/%s',
            rtrim($this->publicPath, '/\\'),
            $collection,
            date('Y/m')
        );

        if (!is_dir($directory)
            && !mkdir($directory, 0755, true)
            && !is_dir($directory)
        ) {
            throw new RuntimeException('The department image directory is unavailable.');
        }

        $storedName = bin2hex(random_bytes(20)) . '.' . $upload['extension'];
        $absolutePath = $directory . DIRECTORY_SEPARATOR . $storedName;
        $temporaryPath = (string) $upload['tmp_name'];
        $moved = is_uploaded_file($temporaryPath)
            ? move_uploaded_file($temporaryPath, $absolutePath)
            : rename($temporaryPath, $absolutePath);

        if (!$moved) {
            throw new RuntimeException('The department image could not be stored.');
        }

        $relativeDirectory = 'public/uploads/'
            . $collection
            . '/'
            . date('Y/m');
        $publicPath = $relativeDirectory . '/' . $storedName;

        try {
            $id = $this->media->createImage([
                'original_name' => $upload['original_name'],
                'stored_name' => $storedName,
                'file_path' => $publicPath,
                'mime_type' => $upload['mime_type'],
                'file_extension' => $upload['extension'],
                'file_size' => $upload['size'],
                'width' => $upload['width'],
                'height' => $upload['height'],
                'alt_text' => $upload['alt_text'],
                'uploaded_by' => $userId,
            ]);
        } catch (\Throwable $exception) {
            @unlink($absolutePath);
            throw $exception;
        }

        return [
            'id' => $id,
            'absolute_path' => $absolutePath,
            'public_path' => $publicPath,
        ];
    }

    public function removeStoredFile(?string $absolutePath): void
    {
        if (is_string($absolutePath) && is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    private function safeOriginalName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[^\pL\pN._ -]+/u', '-', $name) ?? '';
        $name = trim($name, " .-\t\n\r\0\x0B");

        return mb_substr($name !== '' ? $name : 'department-image', 0, 255);
    }

    private function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE =>
                'The selected hero image is too large.',
            UPLOAD_ERR_PARTIAL =>
                'The hero image upload was interrupted. Please try again.',
            default =>
                'The hero image could not be uploaded. Please try again.',
        };
    }
}
