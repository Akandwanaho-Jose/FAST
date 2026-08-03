<?php

declare(strict_types=1);

namespace FastWebsite\Services;

final class AdminNavigation
{
    /**
     * @return list<array{
     *   key: string,
     *   label: string,
     *   route: string,
     *   permission: string,
     *   phase: string
     * }>
     */
    public function definitions(): array
    {
        return [
            ['key' => 'dashboard', 'label' => 'Dashboard', 'route' => 'admin', 'permission' => 'dashboard.view', 'phase' => 'Phase 3'],
            ['key' => 'homepage', 'label' => 'Homepage', 'route' => 'admin/homepage', 'permission' => 'pages.manage', 'phase' => 'Phase 1'],
            ['key' => 'site-content', 'label' => 'Site content', 'route' => 'admin/site-content', 'permission' => 'pages.manage', 'phase' => 'Dynamic content'],
            ['key' => 'departments', 'label' => 'Departments', 'route' => 'admin/departments', 'permission' => 'departments.view', 'phase' => 'Phase 4'],
            ['key' => 'staff', 'label' => 'Staff', 'route' => 'admin/staff', 'permission' => 'staff.view', 'phase' => 'Phase 5'],
            ['key' => 'programmes', 'label' => 'Programmes', 'route' => 'admin/programmes', 'permission' => 'programmes.view', 'phase' => 'Phase 6'],
            ['key' => 'research', 'label' => 'Research', 'route' => 'admin/research', 'permission' => 'research.view', 'phase' => 'Phase 7'],
            ['key' => 'innovations', 'label' => 'Innovations', 'route' => 'admin/innovations', 'permission' => 'research.view', 'phase' => 'MVP'],
            ['key' => 'facilities', 'label' => 'Facilities', 'route' => 'admin/facilities', 'permission' => 'research.view', 'phase' => 'MVP'],
            ['key' => 'engagement', 'label' => 'Engagement', 'route' => 'admin/engagement', 'permission' => 'research.view', 'phase' => 'MVP'],
            ['key' => 'news', 'label' => 'News', 'route' => 'admin/news', 'permission' => 'news.view', 'phase' => 'Phase 10'],
            ['key' => 'events', 'label' => 'Events', 'route' => 'admin/events', 'permission' => 'events.manage', 'phase' => 'Phase 10'],
            ['key' => 'pages', 'label' => 'Pages', 'route' => 'admin/pages', 'permission' => 'pages.manage', 'phase' => 'Phase 10'],
            ['key' => 'media', 'label' => 'Media', 'route' => 'admin/media', 'permission' => 'media.manage', 'phase' => 'Phase 11'],
            ['key' => 'documents', 'label' => 'Documents', 'route' => 'admin/documents', 'permission' => 'documents.manage', 'phase' => 'Phase 11'],
            ['key' => 'users', 'label' => 'Users', 'route' => 'admin/users', 'permission' => 'users.manage', 'phase' => 'Later administration phase'],
            ['key' => 'audit', 'label' => 'Audit logs', 'route' => 'admin/audit', 'permission' => 'audit.view', 'phase' => 'Later administration phase'],
            ['key' => 'settings', 'label' => 'Settings', 'route' => 'admin/settings', 'permission' => 'settings.manage', 'phase' => 'Later phase'],
        ];
    }

    /**
     * @return list<array{
     *   key: string,
     *   label: string,
     *   route: string,
     *   permission: string,
     *   phase: string
     * }>
     */
    public function visible(int $userId, AuthorizationService $authorization): array
    {
        return array_values(array_filter(
            $this->definitions(),
            static fn (array $item): bool => $authorization->can(
                $userId,
                $item['permission']
            )
        ));
    }
}
