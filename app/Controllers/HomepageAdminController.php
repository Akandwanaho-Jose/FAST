<?php

declare(strict_types=1);

namespace FastWebsite\Controllers;

use FastWebsite\Core\Controller;
use FastWebsite\Core\Csrf;
use FastWebsite\Core\HttpException;
use FastWebsite\Core\Request;
use FastWebsite\Core\Response;
use FastWebsite\Core\Session;
use FastWebsite\Repositories\HomepageRepository;
use FastWebsite\Services\AdminPageContext;
use FastWebsite\Services\AuthService;
use FastWebsite\Services\MediaUploadService;
use Throwable;

final class HomepageAdminController extends Controller
{
    public function __construct(
        \FastWebsite\Core\View $view,
        private readonly AuthService $auth,
        private readonly AdminPageContext $context,
        private readonly HomepageRepository $homepage,
        private readonly MediaUploadService $uploads,
        private readonly Csrf $csrf,
        private readonly Session $session
    ) {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        return $this->admin($request, 'admin/homepage/index', [
            'pageTitle' => 'Homepage hero',
            'slides' => $this->homepage->adminSlides(),
            'sections' => $this->homepage->sections(true),
            'quickLinks' => $this->homepage->quickLinks(true),
            'images' => $this->homepage->activeImages(),
        ]);
    }

    public function updateSection(Request $request): Response
    {
        $this->checkCsrf($request);
        $key = (string) $request->route('key', '');
        $validKeys = array_column($this->homepage->sections(true), 'section_key');
        if (!in_array($key, $validKeys, true)) {
            throw new HttpException(404, 'Homepage section not found.');
        }
        $this->homepage->updateSection($key, [
            'heading' => $this->nullable($request->input('heading'), 255),
            'introduction' => $this->nullable($request->input('introduction'), 2000),
            'display_order' => max(0, min(32767, (int) $request->input('display_order', 0))),
            'is_enabled' => (string) $request->input('is_enabled') === '1' ? 1 : 0,
        ]);
        $this->session->put('_flash_success', 'Homepage section settings updated.');
        return Response::redirect($request->baseUrl() . 'admin/homepage#sections');
    }

    public function updateQuickLink(Request $request): Response
    {
        $this->checkCsrf($request);
        $id = $this->id($request);
        $label = mb_substr(trim((string) $request->input('label', '')), 0, 120);
        if ($label === '') {
            throw new HttpException(422, 'Quick-link label is required.');
        }
        $url = trim((string) $request->input('link_url', ''));
        if ($url === '' || preg_match('#^(?:https?://|/[^/])#i', $url) !== 1) {
            throw new HttpException(422, 'Quick-link URL must be an internal path or a complete http(s) URL.');
        }
        $mediaId = $this->positiveInteger($request->input('media_id'));
        if ($mediaId !== null && !$this->homepage->activeImageExists($mediaId)) {
            throw new HttpException(422, 'Choose a valid active image for this quick link.');
        }
        $this->homepage->updateQuickLink($id, [
            'label' => $label,
            'description' => $this->nullable($request->input('description'), 255),
            'link_url' => mb_substr($url, 0, 500),
            'media_id' => $mediaId,
            'display_order' => max(0, min(32767, (int) $request->input('display_order', 0))),
            'is_active' => (string) $request->input('is_active') === '1' ? 1 : 0,
        ]);
        $this->session->put('_flash_success', 'Homepage quick link updated.');
        return Response::redirect($request->baseUrl() . 'admin/homepage#quick-links');
    }

    public function create(Request $request): Response
    {
        return $this->form($request);
    }

    public function edit(Request $request): Response
    {
        $slide = $this->homepage->findSlide($this->id($request));
        if ($slide === null) {
            throw new HttpException(404, 'Homepage slide not found.');
        }
        return $this->form($request, $slide, [], (int) $slide['id']);
    }

    public function store(Request $request): Response
    {
        return $this->save($request);
    }

    public function update(Request $request): Response
    {
        return $this->save($request, $this->id($request));
    }

    private function save(Request $request, ?int $id = null): Response
    {
        $this->checkCsrf($request);
        $existing = $id === null ? null : $this->homepage->findSlide($id);
        if ($id !== null && $existing === null) {
            throw new HttpException(404, 'Homepage slide not found.');
        }

        $values = [];
        foreach ([
            'title', 'caption', 'media_id', 'mobile_media_id', 'button_label', 'button_url',
            'text_alignment', 'overlay_strength', 'starts_at', 'ends_at', 'display_order', 'is_active',
        ] as $field) {
            $values[$field] = $request->input($field);
        }

        $upload = $this->uploads->validate(
            $request->file('hero_image'),
            (string) $request->input('hero_alt_text', '')
        );
        $errors = $upload['errors'];
        $title = mb_substr(trim((string) $values['title']), 0, 255);
        if ($title === '') {
            $errors[] = 'Slide title is required.';
        }
        $buttonUrl = trim((string) $values['button_url']);
        if ($buttonUrl !== '' && preg_match('#^(?:https?://|/[^/])#i', $buttonUrl) !== 1) {
            $errors[] = 'Button URL must be an internal path beginning with / or a complete http(s) URL.';
        }

        $mediaId = $this->positiveInteger($values['media_id']);
        if ($upload['upload'] === null && ($mediaId === null || !$this->homepage->activeImageExists($mediaId))) {
            $errors[] = 'Choose an active hero image or upload a new one.';
        }
        $mobileMediaId = $this->positiveInteger($values['mobile_media_id']);
        if ($mobileMediaId !== null && !$this->homepage->activeImageExists($mobileMediaId)) {
            $errors[] = 'Choose a valid mobile image.';
        }

        $alignment = in_array($values['text_alignment'], ['left', 'centre', 'right'], true)
            ? (string) $values['text_alignment']
            : 'left';
        $overlay = (int) $values['overlay_strength'];
        if (!in_array($overlay, [20, 40, 60, 80], true)) {
            $overlay = 40;
        }
        $startsAt = $this->dateTime($values['starts_at'], 'start', $errors);
        $endsAt = $this->dateTime($values['ends_at'], 'end', $errors);
        if ($startsAt !== null && $endsAt !== null && $endsAt <= $startsAt) {
            $errors[] = 'The slide end time must be after its start time.';
        }

        if ($errors !== []) {
            return $this->form($request, $values, $errors, $id);
        }

        $storedPath = null;
        try {
            if ($upload['upload'] !== null) {
                $stored = $this->uploads->store($upload['upload'], (int) $this->user()['id'], 'pages');
                $storedPath = $stored['absolute_path'];
                $mediaId = $stored['id'];
            }
            $this->homepage->saveSlide($id, [
                'title' => $title,
                'caption' => $this->nullable($values['caption'], 10000),
                'media_id' => $mediaId,
                'mobile_media_id' => $mobileMediaId,
                'button_label' => $this->nullable($values['button_label'], 100),
                'button_url' => $this->nullable($buttonUrl, 500),
                'text_alignment' => $alignment,
                'overlay_strength' => $overlay,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'display_order' => max(0, min(32767, (int) $values['display_order'])),
                'is_active' => (string) $values['is_active'] === '1' ? 1 : 0,
            ]);
        } catch (Throwable $exception) {
            $this->uploads->removeStoredFile($storedPath);
            throw $exception;
        }

        $this->session->put('_flash_success', $id === null ? 'Homepage slide created.' : 'Homepage slide updated.');
        return Response::redirect($request->baseUrl() . 'admin/homepage');
    }

    /** @param array<string,mixed> $values @param list<string> $errors */
    private function form(Request $request, array $values = [], array $errors = [], ?int $id = null): Response
    {
        if ($values === []) {
            $values = [
                'text_alignment' => 'left', 'overlay_strength' => '40',
                'display_order' => '0', 'is_active' => '1',
            ];
        }
        return $this->admin($request, 'admin/homepage/form', [
            'pageTitle' => $id === null ? 'Add homepage slide' : 'Edit homepage slide',
            'itemId' => $id,
            'values' => $values,
            'errors' => $errors,
            'images' => $this->homepage->activeImages(),
        ]);
    }

    /** @param list<string> $errors */
    private function dateTime(mixed $value, string $label, array &$errors): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value);
        if ($date === false || $date->format('Y-m-d\TH:i') !== $value) {
            $errors[] = 'Choose a valid slide ' . $label . ' time.';
            return null;
        }
        return $date->format('Y-m-d H:i:s');
    }

    /** @param array<string,mixed> $data */
    private function admin(Request $request, string $template, array $data): Response
    {
        return $this->view(
            $template,
            array_merge($this->context->data($request, $this->user()), $data),
            200,
            'layouts/admin'
        )->withHeader('Cache-Control', 'no-store');
    }

    /** @return array<string,mixed> */
    private function user(): array
    {
        return $this->auth->user() ?? throw new HttpException(403, 'Authentication required.');
    }

    private function checkCsrf(Request $request): void
    {
        if (!$this->csrf->validate($request->input('_token'))) {
            throw new HttpException(403, 'The secure form session expired.');
        }
    }

    private function id(Request $request): int
    {
        $value = (string) $request->route('id', '');
        if (!ctype_digit($value) || (int) $value < 1) {
            throw new HttpException(404, 'Homepage slide not found.');
        }
        return (int) $value;
    }

    private function positiveInteger(mixed $value): ?int
    {
        $value = trim((string) $value);
        return ctype_digit($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function nullable(mixed $value, int $maximum): ?string
    {
        $value = mb_substr(trim((string) $value), 0, $maximum);
        return $value === '' ? null : $value;
    }
}
