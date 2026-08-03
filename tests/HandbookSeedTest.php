<?php

declare(strict_types=1);

return static function (): void {
    $root = dirname(__DIR__);
    $departments = require $root . '/database/seeds/departments-handbook-2025-2026.php';
    $programmes = require $root . '/database/seeds/undergraduate-handbook-2025-2026.php';
    $handle = fopen($root . '/database/seeds/staff-handbook-2025-2026.csv', 'rb');
    if ($handle === false) {
        throw new RuntimeException('Staff handbook seed could not be opened.');
    }
    $headers = fgetcsv($handle, null, ',', '"', '');
    $staff = [];
    while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
        if ($row !== [null] && $row !== []) {
            $staff[] = array_combine($headers, $row);
        }
    }
    fclose($handle);

    $imageHandle = fopen($root . '/database/seeds/staff-handbook-2025-2026-images.csv', 'rb');
    if ($imageHandle === false) {
        throw new RuntimeException('Staff handbook image map could not be opened.');
    }
    $imageHeaders = fgetcsv($imageHandle, null, ',', '"', '');
    $images = [];
    while (($row = fgetcsv($imageHandle, null, ',', '"', '')) !== false) {
        if ($row !== [null] && $row !== []) {
            $images[] = array_combine($imageHeaders, $row);
        }
    }
    fclose($imageHandle);

    if (count($departments) !== 3 || count($programmes) !== 5
        || count($staff) !== 46 || count($images) !== 44
    ) {
        throw new RuntimeException('Handbook seed record counts changed unexpectedly.');
    }

    $curriculumHandle = fopen($root . '/database/seeds/curriculum-handbook-2025-2026.csv', 'rb');
    if ($curriculumHandle === false) {
        throw new RuntimeException('Curriculum handbook seed could not be opened.');
    }
    $curriculumHeaders = fgetcsv($curriculumHandle, null, ',', '"', '');
    $curriculum = [];
    while (($row = fgetcsv($curriculumHandle, null, ',', '"', '')) !== false) {
        if ($row !== [null] && $row !== []) {
            $curriculum[] = array_combine($curriculumHeaders, $row);
        }
    }
    fclose($curriculumHandle);
    if (count($curriculum) !== 294 || count(array_unique(array_column($curriculum, 'programme_slug'))) !== 5) {
        throw new RuntimeException('Handbook curriculum placement counts changed unexpectedly.');
    }
    $productionEngineering = array_values(array_filter($curriculum, static fn (array $row): bool =>
        $row['course_title'] === 'Production Engineering I'
        && $row['programme_slug'] === 'bachelor-of-mechanical-and-industrial-engineering'
    ));
    if (count($productionEngineering) !== 1 || $productionEngineering[0]['course_code'] !== 'MIE1106') {
        throw new RuntimeException('The documented MIE1106 handbook correction is missing.');
    }

    $identities = [];
    $emails = [];
    foreach ($staff as $record) {
        if (!is_array($record)) {
            throw new RuntimeException('A staff seed row is malformed.');
        }
        $identity = strtolower(trim(implode(' ', array_filter([
            $record['first_name'], $record['middle_name'], $record['last_name'],
        ]))));
        if (isset($identities[$identity])) {
            throw new RuntimeException('Duplicate handbook staff identity: ' . $identity);
        }
        $identities[$identity] = true;
        foreach (['institutional_email', 'alternative_email'] as $field) {
            $email = strtolower(trim((string) $record[$field]));
            if ($email === '') {
                continue;
            }
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || isset($emails[$email])) {
                throw new RuntimeException('Invalid or duplicate handbook email: ' . $email);
            }
            $emails[$email] = true;
        }
    }

    $mutanda = array_values(array_filter(
        $staff,
        static fn (array $record): bool => $record['last_name'] === 'Mutanda'
    ));
    if (count($mutanda) !== 1 || $mutanda[0]['institutional_email'] !== '') {
        throw new RuntimeException('The malformed Mutanda handbook email must remain withheld.');
    }

    $staffSlugs = [];
    foreach ($staff as $record) {
        $name = trim(implode(' ', array_filter([
            $record['first_name'], $record['middle_name'], $record['last_name'],
        ])));
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        $slug = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $ascii ?: $name));
        $staffSlugs[trim($slug, '-')] = true;
    }
    $imageSlugs = [];
    foreach ($images as $record) {
        $slug = $record['staff_slug'];
        if (!isset($staffSlugs[$slug]) || isset($imageSlugs[$slug])) {
            throw new RuntimeException('A portrait map has an unknown or duplicate staff slug: ' . $slug);
        }
        $imageSlugs[$slug] = true;
        $path = $root . '/' . $record['public_path'];
        $dimensions = is_file($path) ? getimagesize($path) : false;
        if (!is_array($dimensions) || ($dimensions['mime'] ?? '') !== 'image/webp'
            || filesize($path) > 5 * 1024 * 1024
        ) {
            throw new RuntimeException('A handbook portrait is missing or invalid: ' . $slug);
        }
    }
    $missingPortraits = array_values(array_diff(array_keys($staffSlugs), array_keys($imageSlugs)));
    sort($missingPortraits);
    if ($missingPortraits !== ['julius-taremwa', 'phionah-nabimanya']) {
        throw new RuntimeException('The handbook no-portrait review list changed unexpectedly.');
    }
};
