<?php

declare(strict_types=1);

namespace FastWebsite\Controllers;

use FastWebsite\Core\Controller;use FastWebsite\Core\HttpException;use FastWebsite\Core\Request;use FastWebsite\Core\Response;use FastWebsite\Repositories\SiteContentRepository;

final class SiteContentPublicController extends Controller
{
    private const ABOUT_PAGES = [
        'faculty-overview',
        'history-of-the-faculty',
        'deans-message',
        'vision-and-mission',
        'core-values',
    ];

    public function __construct(\FastWebsite\Core\View $view, private readonly SiteContentRepository $r)
    {
        parent::__construct($view);
    }

    public function aboutIndex(Request $request): Response
    {
        return Response::redirect($request->baseUrl() . 'about/faculty-overview');
    }

    public function aboutPage(Request $request): Response
    {
        $slug = (string) $request->route('slug', '');

        if (!in_array($slug, self::ABOUT_PAGES, true)) {
            throw new HttpException(404, 'About page not found.');
        }

        return $this->renderPage($request, $slug);
    }

    public function news(Request $q): Response
    {
        return $this->public($q, 'public/content/news-index', 'News & Announcements', [
            'items' => $this->r->news(null, true),
        ]);
    }

    public function newsItem(Request $q): Response
    {
        $item = $this->r->publicNews((string) $q->route('slug', ''));
        if ($item === null) {
            throw new HttpException(404, 'News item not found.');
        }
        $related = array_values(array_filter(
            $this->r->news(null, true, 5),
            static fn (array $candidate): bool => (int) $candidate['id'] !== (int) $item['id']
        ));
        return $this->public($q, 'public/content/news-show', (string) $item['title'], [
            'item' => $item,
            'related' => array_slice($related, 0, 3),
        ]);
    }

    public function events(Request $q): Response
    {
        return $this->public($q, 'public/content/events-index', 'Events', [
            'items' => $this->r->events(null, true),
        ]);
    }

    public function event(Request $q): Response
    {
        $item = $this->r->publicEvent((string) $q->route('slug', ''));
        if ($item === null) {
            throw new HttpException(404, 'Event not found.');
        }
        $related = array_values(array_filter(
            $this->r->events(null, true, 8),
            static fn (array $candidate): bool => (int) $candidate['id'] !== (int) $item['id']
                && strtotime((string) $candidate['starts_at']) >= time()
        ));
        return $this->public($q, 'public/content/event-show', (string) $item['title'], [
            'item' => $item,
            'related' => array_slice($related, 0, 3),
        ]);
    }
    public function page(Request $request): Response{return $this->renderPage($request,(string)$request->route('slug',''));}

    private function renderPage(Request $request, string $slug): Response
    {
        $item = $this->r->publicPage($slug);

        if ($item === null) {
            throw new HttpException(404, 'Page not found.');
        }

        return $this->public(
            $request,
            'public/content/page',
            (string) $item['title'],
            [
                'item' => $item,
                'sections' => $this->r->sections((int) $item['id'], true),
                'dean' => $slug === 'deans-message' ? $this->r->publicDean() : null,
                'relatedPages' => $slug === 'student-life' ? $this->r->publicStudentLifePages() : [],
            ]
        );
    }

    private function public(Request $q,string $template,string $title,array $data):Response{return $this->view($template,['pageTitle'=>$title,'metaDescription'=>$title,'currentPath'=>$q->path(),'baseUrl'=>$q->baseUrl(),...$data]);}
}
