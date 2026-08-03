<?php

declare(strict_types=1);

use FastWebsite\Core\RichText;

return static function():void{
    $plain=RichText::render("First line\nSecond line");if($plain!=="First line<br />\nSecond line")throw new RuntimeException('Plain text compatibility failed.');
    $formatted=RichText::render('<h2>Heading</h2><p><strong>Bold</strong> and <em>italic</em>.</p><ul><li>One</li></ul>');if(!str_contains($formatted,'<h2>Heading</h2>')||!str_contains($formatted,'<strong>Bold</strong>')||!str_contains($formatted,'<li>One</li>'))throw new RuntimeException('Allowed formatting was removed.');
    $unsafe=RichText::render('<p onclick="alert(1)">Safe</p><script>alert(2)</script><a href="javascript:alert(3)">Bad link</a><a href="https://example.com">Good link</a>');if(str_contains($unsafe,'script')||str_contains($unsafe,'onclick')||str_contains($unsafe,'javascript:')||!str_contains($unsafe,'rel="noopener noreferrer"'))throw new RuntimeException('Unsafe rich text was not sanitized.');
};
