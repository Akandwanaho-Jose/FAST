<?php

declare(strict_types=1);

use FastWebsite\Core\Database;
use FastWebsite\Repositories\HomepageRepository;

return static function (): void {
    /** @var Database $database */
    $database = require dirname(__DIR__, 2) . '/bootstrap/database.php';
    $connection = $database->connection();
    $connection->beginTransaction();

    try {
        $mediaId = (int) $connection->query(
            'SELECT id FROM media WHERE media_type = "image" AND status = "active"
             AND deleted_at IS NULL ORDER BY id LIMIT 1'
        )->fetchColumn();
        if ($mediaId < 1) {
            throw new RuntimeException('Homepage hero test requires an active image.');
        }

        $repository = new HomepageRepository($database);
        $suffix = bin2hex(random_bytes(5));
        $id = $repository->saveSlide(null, [
            'title' => 'Dynamic homepage hero ' . $suffix,
            'caption' => 'Managed from the administration area.',
            'media_id' => $mediaId,
            'mobile_media_id' => null,
            'button_label' => 'Explore programmes',
            'button_url' => '/programmes',
            'text_alignment' => 'left',
            'overlay_strength' => 40,
            'starts_at' => null,
            'ends_at' => null,
            'display_order' => 0,
            'is_active' => 1,
        ]);

        $slide = $repository->findSlide($id);
        if (!is_array($slide) || $slide['title'] !== 'Dynamic homepage hero ' . $suffix) {
            throw new RuntimeException('Managed homepage slide was not created.');
        }

        $data = array_intersect_key($slide, array_flip([
            'title', 'caption', 'media_id', 'mobile_media_id', 'button_label', 'button_url',
            'text_alignment', 'overlay_strength', 'starts_at', 'ends_at', 'display_order', 'is_active',
        ]));
        $data['caption'] = 'Updated through the dynamic homepage workflow.';
        $repository->saveSlide($id, $data);
        $public = array_values(array_filter(
            $repository->slides(),
            static fn (array $item): bool => (int) $item['id'] === $id
        ));
        if (count($public) !== 1 || $public[0]['caption'] !== $data['caption']) {
            throw new RuntimeException('Updated homepage slide was not available publicly.');
        }

        $sections = $repository->sections(true);
        if ($sections === []) {
            throw new RuntimeException('Dynamic homepage sections were not available.');
        }
        $section = $sections[0];
        $sectionHeading = 'Homepage section ' . $suffix;
        $repository->updateSection((string) $section['section_key'], [
            'heading' => $sectionHeading,
            'introduction' => 'Managed homepage section copy.',
            'display_order' => (int) $section['display_order'],
            'is_enabled' => 1,
        ]);
        $updatedSections = $repository->sections(true);
        $updatedSection = array_values(array_filter(
            $updatedSections,
            static fn (array $item): bool => $item['section_key'] === $section['section_key']
        ))[0] ?? null;
        if (!is_array($updatedSection) || $updatedSection['heading'] !== $sectionHeading) {
            throw new RuntimeException('Homepage section settings were not updated.');
        }

        $quickLinks = $repository->quickLinks(true);
        if ($quickLinks === []) {
            throw new RuntimeException('Dynamic homepage quick links were not available.');
        }
        $quickLink = $quickLinks[0];
        $linkLabel = 'Quick link ' . $suffix;
        $repository->updateQuickLink((int) $quickLink['id'], [
            'label' => $linkLabel,
            'description' => 'Managed destination.',
            'link_url' => '/programmes',
            'display_order' => (int) $quickLink['display_order'],
            'is_active' => 1,
        ]);
        $updatedLinks = $repository->quickLinks(true);
        $updatedLink = array_values(array_filter(
            $updatedLinks,
            static fn (array $item): bool => (int) $item['id'] === (int) $quickLink['id']
        ))[0] ?? null;
        if (!is_array($updatedLink) || $updatedLink['label'] !== $linkLabel) {
            throw new RuntimeException('Homepage quick link was not updated.');
        }
    } finally {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
    }
};
