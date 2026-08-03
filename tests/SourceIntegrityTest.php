<?php

declare(strict_types=1);

return static function (): void {
    $root = dirname(__DIR__);
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );
    $affected = [];

    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile() || strtolower($file->getExtension()) !== 'php') {
            continue;
        }
        $contents = file_get_contents($file->getPathname());
        if ($contents !== false && str_contains($contents, "\0")) {
            $affected[] = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        }
    }

    if ($affected !== []) {
        throw new RuntimeException('Null bytes found in PHP source: ' . implode(', ', $affected));
    }
};
