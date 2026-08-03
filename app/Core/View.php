<?php

declare(strict_types=1);

namespace FastWebsite\Core;

use RuntimeException;

final class View
{
    /** @var array<string,mixed> */
    private array $sharedData = [];

    public function __construct(private readonly string $basePath)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(
        string $template,
        array $data = [],
        ?string $layout = 'layouts/public'
    ): string {
        $data = [...$this->sharedData, ...$data];
        $content = $this->renderFile($template, $data);

        if ($layout === null) {
            return $content;
        }

        return $this->renderFile($layout, [...$data, 'content' => $content]);
    }

    public static function escape(mixed $value): string
    {
        return htmlspecialchars(
            (string) $value,
            ENT_QUOTES | ENT_SUBSTITUTE,
            'UTF-8'
        );
    }

    public static function richText(mixed $value): string
    {
        return RichText::render($value);
    }

    /** @param array<string,mixed> $data */
    public function share(array $data): void
    {
        $this->sharedData = [...$this->sharedData, ...$data];
    }

    /** @param array<string,mixed> $settings */
    public static function setting(array $settings, string $key, string $fallback = ''): string
    {
        $value = $settings[$key] ?? null;
        return is_string($value) && trim($value) !== '' ? $value : $fallback;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderFile(string $template, array $data): string
    {
        if (str_contains($template, '..')) {
            throw new RuntimeException('Invalid view template path.');
        }

        $file = rtrim($this->basePath, '/\\')
            . DIRECTORY_SEPARATOR
            . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $template)
            . '.php';

        if (!is_file($file)) {
            throw new RuntimeException('The requested view template does not exist.');
        }

        extract($data, EXTR_SKIP);
        ob_start();

        try {
            require $file;

            return (string) ob_get_clean();
        } catch (\Throwable $exception) {
            ob_end_clean();

            throw $exception;
        }
    }
}
