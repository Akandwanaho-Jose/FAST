<?php

declare(strict_types=1);

namespace FastWebsite\Services;

use FastWebsite\Core\HttpException;
use FastWebsite\Repositories\ProjectRepository;
use FastWebsite\Repositories\PublicationRepository;
use FastWebsite\Repositories\ResearchMetadataRepository;
use FastWebsite\Validation\ResearchMetadataValidator;
use Throwable;

final class ResearchMetadataService
{
    public function __construct(
        private readonly ResearchMetadataRepository $metadata,
        private readonly ProjectRepository $projects,
        private readonly PublicationRepository $publications,
        private readonly AuthorizationService $authorization,
        private readonly AuditService $audit
    ) {
    }

    /** @param array<string,mixed> $data */
    public function saveTheme(?int $id, array $data, int $departmentId, int $userId, bool $publish, string $ip, string $agent): int
    {
        $this->assertPermission($userId, $id === null ? 'research.create' : 'research.edit');
        if (!$this->metadata->canUseDepartment($userId, $departmentId)) {
            throw new HttpException(403, 'The selected department is outside your editing scope.');
        }
        if ($publish) {
            $this->assertPublisher($userId);
        }
        return $this->transaction(function () use ($id, $data, $departmentId, $userId, $publish, $ip, $agent): int {
            $creating = $id === null;
            if ($id === null) {
                $id = $this->metadata->createTheme($data, $departmentId);
            } else {
                $this->metadata->updateTheme($id, $data, $departmentId);
            }
            if ($publish) {
                $this->metadata->changeThemeStatus($id, 'published');
            }
            $this->audit->record($userId, $creating ? 'research_theme.created' : 'research_theme.updated', 'research_theme', $id, $ip, $agent, ['published'=>$publish]);
            return $id;
        });
    }

    public function themeStatus(int $id, string $action, int $userId, string $ip, string $agent): string
    {
        $theme = $this->metadata->findTheme($id, $userId);
        if ($theme === null) {
            throw new HttpException(404, 'Research theme not found.');
        }
        $target = match ($action) {
            'publish' => 'published',
            'archive' => 'archived',
            'restore' => 'draft',
            default => throw new HttpException(422, 'Choose a valid theme action.'),
        };
        if ($target === 'published') {
            $this->assertPublisher($userId);
        } else {
            $this->assertPermission($userId, 'research.edit');
        }
        $this->metadata->changeThemeStatus($id, $target);
        $this->audit->record($userId, 'research_theme.' . $target, 'research_theme', $id, $ip, $agent);
        return $target;
    }

    /** @param array<string,mixed> $data */
    public function savePartner(?int $id, array $data, int $userId, bool $publish, string $ip, string $agent): int
    {
        $this->assertPermission($userId, $id === null ? 'research.create' : 'research.edit');
        if ($publish) {
            $this->assertPublisher($userId);
        }
        return $this->transaction(function () use ($id, $data, $userId, $publish, $ip, $agent): int {
            $creating = $id === null;
            if ($creating) {
                $id = $this->metadata->createPartner($data);
            } else {
                $this->metadata->updatePartner($id, $data);
            }
            if ($publish) {
                $this->metadata->changePartnerStatus($id, 'published');
            }
            $this->audit->record($userId, $creating ? 'partner.created' : 'partner.updated', 'partner', $id, $ip, $agent, ['published'=>$publish]);
            return $id;
        });
    }

    public function partnerStatus(int $id, string $action, int $userId, string $ip, string $agent): string
    {
        if ($this->metadata->findPartner($id) === null) {
            throw new HttpException(404, 'Partner not found.');
        }
        $target = match ($action) {
            'publish' => 'published',
            'archive' => 'archived',
            'restore' => 'draft',
            default => throw new HttpException(422, 'Choose a valid partner action.'),
        };
        if ($target === 'published') {
            $this->assertPublisher($userId);
        } else {
            $this->assertPermission($userId, 'research.edit');
        }
        $this->metadata->changePartnerStatus($id, $target);
        $this->audit->record($userId, 'partner.' . $target, 'partner', $id, $ip, $agent);
        return $target;
    }

    public function addProjectTheme(int $projectId, int $themeId, bool $primary, int $userId, string $ip, string $agent): void
    {
        $this->assertProjectEdit($projectId, $userId);
        if (!$this->metadata->publishedThemeExists($themeId)) {
            throw new HttpException(422, 'Choose a published research theme.');
        }
        $this->metadata->addProjectTheme($projectId, $themeId, $primary);
        $this->audit->record($userId, 'project.theme_assigned', 'project', $projectId, $ip, $agent, ['theme_id'=>$themeId, 'primary'=>$primary]);
    }

    public function removeProjectTheme(int $projectId, int $themeId, int $userId, string $ip, string $agent): void
    {
        $this->assertProjectEdit($projectId, $userId);
        if (!$this->metadata->removeProjectTheme($projectId, $themeId)) {
            throw new HttpException(404, 'Project theme link not found.');
        }
        $this->audit->record($userId, 'project.theme_removed', 'project', $projectId, $ip, $agent, ['theme_id'=>$themeId]);
    }

    public function addProjectSdg(int $projectId, int $sdgId, bool $primary, int $userId, string $ip, string $agent): void
    {
        $this->assertProjectEdit($projectId, $userId);
        if (!$this->metadata->sdgExists($sdgId)) {
            throw new HttpException(422, 'Choose a valid Sustainable Development Goal.');
        }
        $this->metadata->addProjectSdg($projectId, $sdgId, $primary);
        $this->audit->record($userId, 'project.sdg_assigned', 'project', $projectId, $ip, $agent, ['sdg_id'=>$sdgId, 'primary'=>$primary]);
    }

    public function removeProjectSdg(int $projectId, int $sdgId, int $userId, string $ip, string $agent): void
    {
        $this->assertProjectEdit($projectId, $userId);
        if (!$this->metadata->removeProjectSdg($projectId, $sdgId)) {
            throw new HttpException(404, 'Project SDG link not found.');
        }
        $this->audit->record($userId, 'project.sdg_removed', 'project', $projectId, $ip, $agent, ['sdg_id'=>$sdgId]);
    }

    public function addProjectPartner(int $projectId, int $partnerId, string $role, string $contribution, int $userId, string $ip, string $agent): void
    {
        $this->assertProjectEdit($projectId, $userId);
        if (!$this->metadata->publishedPartnerExists($partnerId)) {
            throw new HttpException(422, 'Choose a published partner organization.');
        }
        if (!in_array($role, ResearchMetadataValidator::PARTNER_ROLES, true)) {
            throw new HttpException(422, 'Choose a valid partner role.');
        }
        $contribution = mb_substr(trim($contribution), 0, 5000);
        $this->metadata->addProjectPartner($projectId, $partnerId, $role, $contribution !== '' ? $contribution : null);
        $this->audit->record($userId, 'project.partner_assigned', 'project', $projectId, $ip, $agent, ['partner_id'=>$partnerId, 'role'=>$role]);
    }

    public function removeProjectPartner(int $projectId, int $linkId, int $userId, string $ip, string $agent): void
    {
        $this->assertProjectEdit($projectId, $userId);
        if (!$this->metadata->removeProjectPartner($projectId, $linkId)) {
            throw new HttpException(404, 'Project partner link not found.');
        }
        $this->audit->record($userId, 'project.partner_removed', 'project', $projectId, $ip, $agent, ['link_id'=>$linkId]);
    }

    public function addPublicationTheme(int $publicationId, int $themeId, int $userId, string $ip, string $agent): void
    {
        $this->assertPublicationEdit($publicationId, $userId);
        if (!$this->metadata->publishedThemeExists($themeId)) {
            throw new HttpException(422, 'Choose a published research theme.');
        }
        $this->metadata->addPublicationTheme($publicationId, $themeId);
        $this->audit->record($userId, 'publication.theme_assigned', 'publication', $publicationId, $ip, $agent, ['theme_id'=>$themeId]);
    }

    public function removePublicationTheme(int $publicationId, int $themeId, int $userId, string $ip, string $agent): void
    {
        $this->assertPublicationEdit($publicationId, $userId);
        if (!$this->metadata->removePublicationTheme($publicationId, $themeId)) {
            throw new HttpException(404, 'Publication theme link not found.');
        }
        $this->audit->record($userId, 'publication.theme_removed', 'publication', $publicationId, $ip, $agent, ['theme_id'=>$themeId]);
    }

    private function assertProjectEdit(int $projectId, int $userId): void
    {
        $this->assertPermission($userId, 'research.edit');
        if (!$this->projects->canAccess($userId, $projectId)) {
            throw new HttpException(404, 'Project not found.');
        }
    }

    private function assertPublicationEdit(int $publicationId, int $userId): void
    {
        $this->assertPermission($userId, 'research.edit');
        if (!$this->publications->canAccess($userId, $publicationId)) {
            throw new HttpException(404, 'Publication not found.');
        }
    }

    private function assertPublisher(int $userId): void
    {
        $this->assertPermission($userId, 'research.publish');
        $this->assertPermission($userId, 'content.approve');
    }

    private function assertPermission(int $userId, string $permission): void
    {
        if (!$this->authorization->can($userId, $permission)) {
            throw new HttpException(403, 'This research metadata action is not allowed.');
        }
    }

    private function transaction(callable $operation): mixed
    {
        $connection = $this->metadata->connection();
        $owns = !$connection->inTransaction();
        if ($owns) {
            $connection->beginTransaction();
        }
        try {
            $result = $operation();
            if ($owns) {
                $connection->commit();
            }
            return $result;
        } catch (Throwable $exception) {
            if ($owns && $connection->inTransaction()) {
                $connection->rollBack();
            }
            throw $exception;
        }
    }
}
