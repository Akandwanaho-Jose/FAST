<?php

declare(strict_types=1);

use FastWebsite\Core\Database;
use FastWebsite\Repositories\SiteSettingsRepository;

return static function (): void {
    /** @var Database $database */
    $database = require dirname(__DIR__, 2) . '/bootstrap/database.php';
    $repository = new SiteSettingsRepository($database);
    $userId = (int) $database->connection()->query('SELECT id FROM users ORDER BY id LIMIT 1')->fetchColumn();
    if ($userId < 1) {
        throw new RuntimeException('Site settings test requires an administrator account.');
    }
    $settings = $repository->all();
    if ($settings === []) {
        throw new RuntimeException('Site-wide public settings were not seeded.');
    }
    $setting = $settings[0];
    $id = (int) $setting['id'];
    $original = (string) ($setting['setting_value'] ?? '');
    $testValue = 'Dynamic site setting ' . bin2hex(random_bytes(4));
    try {
        $repository->updateMany([$id => $testValue], $userId);
        $map = $repository->publicMap();
        if (($map[$setting['setting_key']] ?? null) !== $testValue) {
            throw new RuntimeException('A managed public setting was not available to public views.');
        }
        $navigation = $repository->navigation();
        if ($navigation['departments'] === [] || $navigation['undergraduate'] === []) {
            throw new RuntimeException('Public navigation is not sourced from published records.');
        }
    } finally {
        $repository->updateMany([$id => $original], $userId);
    }
};
