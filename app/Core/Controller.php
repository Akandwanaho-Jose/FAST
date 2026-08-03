<?php

declare(strict_types=1);

namespace FastWebsite\Core;

abstract class Controller
{
    public function __construct(protected readonly View $view)
    {
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function view(
        string $template,
        array $data = [],
        int $status = 200,
        ?string $layout = 'layouts/public'
    ): Response {
        return Response::html(
            $this->view->render($template, $data, $layout),
            $status
        );
    }
}
