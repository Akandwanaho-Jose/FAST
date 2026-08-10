<?php

declare(strict_types=1);

use FastWebsite\Core\Request;

return static function (): void {
    $request = new Request(
        'POST',
        '/login',
        ['next' => 'admin'],
        '/fast/public',
        ['email' => 'user@example.invalid', 'links' => [['url' => 'https://example.invalid']]],
        '127.0.0.1',
        'Test agent'
    );

    if ($request->input('email') !== 'user@example.invalid'
        || $request->query('next') !== 'admin'
        || $request->baseUrl() !== '/fast/public/'
        || $request->ipAddress() !== '127.0.0.1'
        || $request->input('links') !== null
        || $request->arrayInput('links')[0]['url'] !== 'https://example.invalid'
    ) {
        throw new RuntimeException('Request data was not represented correctly.');
    }

    $routed = $request->withRouteParameters(['id' => '42']);

    if ($routed->route('id') !== '42' || $request->route('id') !== null) {
        throw new RuntimeException('Route parameters were not represented immutably.');
    }

    $requestWithFile = new Request(
        'POST',
        '/admin/departments/42',
        [],
        '/fast/public',
        [],
        '127.0.0.1',
        'Test agent',
        [],
        ['hero_image' => ['name' => 'department.jpg']]
    );

    if ($requestWithFile->file('hero_image')['name'] !== 'department.jpg'
        || $requestWithFile->file('missing') !== null
    ) {
        throw new RuntimeException('Uploaded request files were not represented.');
    }
};
