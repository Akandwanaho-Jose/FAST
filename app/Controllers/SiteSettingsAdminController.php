<?php

declare(strict_types=1);

namespace FastWebsite\Controllers;

use FastWebsite\Core\Controller;
use FastWebsite\Core\Csrf;
use FastWebsite\Core\HttpException;
use FastWebsite\Core\Request;
use FastWebsite\Core\Response;
use FastWebsite\Core\Session;
use FastWebsite\Repositories\SiteSettingsRepository;
use FastWebsite\Services\AdminPageContext;
use FastWebsite\Services\AuditService;
use FastWebsite\Services\AuthService;

final class SiteSettingsAdminController extends Controller
{
    public function __construct(
        \FastWebsite\Core\View $view,
        private readonly AuthService $auth,
        private readonly AdminPageContext $context,
        private readonly SiteSettingsRepository $settings,
        private readonly AuditService $audit,
        private readonly Csrf $csrf,
        private readonly Session $session
    ) {
        parent::__construct($view);
    }

    public function index(Request $request): Response
    {
        $groups = [];
        foreach ($this->settings->all() as $setting) {
            $prefix = explode('.', (string) $setting['setting_key'], 2)[0];
            $groups[$prefix][] = $setting;
        }
        return $this->view('admin/settings/index', array_merge(
            $this->context->data($request, $this->user()),
            ['pageTitle' => 'Site content', 'settingGroups' => $groups]
        ), 200, 'layouts/admin')->withHeader('Cache-Control', 'no-store');
    }

    public function update(Request $request): Response
    {
        if (!$this->csrf->validate($request->input('_token'))) {
            throw new HttpException(403, 'The secure form session expired.');
        }
        $user = $this->user();
        $values = [];
        foreach ($this->settings->all() as $setting) {
            $id = (int) $setting['id'];
            $value = (string) $request->input('setting_' . $id, (string) ($setting['setting_value'] ?? ''));
            if ($setting['value_type'] === 'url' && $value !== '' && preg_match('#^https?://#i', $value) !== 1) {
                throw new HttpException(422, 'External service URLs must be complete http(s) URLs.');
            }
            $values[$id] = $value;
        }
        $updated = $this->settings->updateMany($values, (int) $user['id']);
        $this->audit->record((int) $user['id'], 'site_content.updated', 'site_settings', null, $request->ipAddress(), $request->userAgent(), ['updated' => $updated]);
        $this->session->put('_flash_success', 'Public site content updated.');
        return Response::redirect($request->baseUrl() . 'admin/site-content');
    }

    /** @return array<string,mixed> */
    private function user(): array
    {
        return $this->auth->user() ?? throw new HttpException(403, 'Authentication required.');
    }
}
