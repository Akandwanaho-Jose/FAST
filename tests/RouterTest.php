<?php

declare(strict_types=1);

use FastWebsite\Core\HttpException;
use FastWebsite\Core\Request;
use FastWebsite\Core\Response;
use FastWebsite\Core\Router;

return static function (): void {
    $router = new Router();
    $router->get(
        '/test',
        static fn (Request $request): Response => Response::html($request->path())
    );

    $response = $router->dispatch(new Request('GET', '/test'));

    if ($response->status() !== 200 || $response->body() !== '/test') {
        throw new RuntimeException('Registered route did not return its response.');
    }

    $router->get(
        '/departments/{slug}',
        static fn (Request $request): Response => Response::html(
            (string) $request->route('slug')
        )
    );
    $dynamic = $router->dispatch(
        new Request('GET', '/departments/civil-engineering')
    );

    if ($dynamic->body() !== 'civil-engineering') {
        throw new RuntimeException('Dynamic route parameter was not captured.');
    }

    try {
        $router->dispatch(new Request('GET', '/missing'));
    } catch (HttpException $exception) {
        if ($exception->status() === 404) {
            return;
        }
    }

    throw new RuntimeException('Missing route did not produce a 404 exception.');
};
