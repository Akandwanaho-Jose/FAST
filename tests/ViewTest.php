<?php

declare(strict_types=1);

use FastWebsite\Core\View;

return static function (): void {
    $unsafe = '<script>alert("x")</script>';
    $escaped = View::escape($unsafe);

    if (str_contains($escaped, '<script>') || !str_contains($escaped, '&lt;script&gt;')) {
        throw new RuntimeException('View escaping did not neutralize HTML.');
    }

    if (View::setting(['page.title' => 'Managed title'], 'page.title', 'Fallback') !== 'Managed title'
        || View::setting([], 'page.title', 'Fallback') !== 'Fallback') {
        throw new RuntimeException('Dynamic site-setting fallback resolution failed.');
    }
};
