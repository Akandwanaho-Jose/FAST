<?php

declare(strict_types=1);

namespace FastWebsite\Services;

use FastWebsite\Core\HttpException;
use FastWebsite\Repositories\CurriculumRepository;
use FastWebsite\Repositories\ProgrammeRepository;
use Throwable;

final class CurriculumService
{
    public function __construct(
        private readonly CurriculumRepository $curricula,
        private readonly ProgrammeRepository $programmes,
        private readonly AuthorizationService $authorization,
        private readonly AuditService $audit
    ) {
    }

    /** @param array<string,mixed> $version @param array<string,mixed> $course @param array<string,mixed> $placement */
    public function savePlacement(
        int $programmeId,
        ?int $versionId,
        ?int $placementId,
        ?int $courseId,
        array $version,
        array $course,
        array $placement,
        int $userId,
        string $ip,
        string $agent
    ): int {
        $this->requireAccess($programmeId, $userId, 'programmes.edit');
        return $this->transaction(function () use ($programmeId, $versionId, $placementId, $courseId, $version, $course, $placement, $userId, $ip, $agent): int {
            $current = $this->curricula->versionForAdmin($programmeId);
            if ($versionId !== null && (!is_array($current) || (int) $current['id'] !== $versionId)) {
                throw new HttpException(409, 'The curriculum version changed. Reload and try again.');
            }
            if ($current === null) {
                $versionId = $this->curricula->createVersion($programmeId, $version);
                $current = $this->curricula->versionForUpdate($versionId);
            } else {
                $versionId = (int) $current['id'];
                $this->requireLiveAccess($current, $programmeId, $userId);
                $this->curricula->updateVersion($versionId, $version);
            }
            $existingByCode = $this->curricula->courseByCode((string) $course['course_code']);
            if ($courseId !== null) {
                $selected = $this->curricula->courseById($courseId);
                if ($selected === null) {
                    throw new HttpException(404, 'Course not found.');
                }
                if ($existingByCode !== null && (int) $existingByCode['id'] !== $courseId) {
                    throw new HttpException(409, 'That course code is already used by another course.');
                }
            } elseif ($existingByCode !== null) {
                $courseId = (int) $existingByCode['id'];
            }
            $course['status'] = ($current['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
            if ($courseId === null) {
                $course['slug'] = strtolower((string) $course['course_code']);
                $courseId = $this->curricula->createCourse($course);
            } else {
                $this->curricula->updateCourse($courseId, $course);
            }
            $saved = $this->curricula->savePlacement($versionId, $courseId, $placement, $placementId);
            $this->curricula->refreshTotal($versionId);
            $this->audit->record($userId, $placementId === null ? 'curriculum.course_added' : 'curriculum.course_updated', 'curriculum_version', $versionId, $ip, $agent, [
                'programme_id' => $programmeId,
                'course_code' => $course['course_code'],
                'placement_id' => $saved,
            ]);
            return $saved;
        });
    }

    public function deletePlacement(int $programmeId, int $versionId, int $placementId, int $userId, string $ip, string $agent): void
    {
        $this->requireAccess($programmeId, $userId, 'programmes.edit');
        $this->transaction(function () use ($programmeId, $versionId, $placementId, $userId, $ip, $agent): void {
            $current = $this->curricula->versionForUpdate($versionId);
            if ($current === null || (int) $current['programme_id'] !== $programmeId) {
                throw new HttpException(404, 'Curriculum version not found.');
            }
            $this->requireLiveAccess($current, $programmeId, $userId);
            if (!$this->curricula->deletePlacement($versionId, $placementId)) {
                throw new HttpException(404, 'Curriculum course placement not found.');
            }
            $this->curricula->refreshTotal($versionId);
            $this->audit->record($userId, 'curriculum.course_removed', 'curriculum_version', $versionId, $ip, $agent, ['placement_id' => $placementId]);
        });
    }

    public function publish(int $programmeId, int $versionId, int $userId, string $ip, string $agent): void
    {
        if (!$this->canPublish($programmeId, $userId)) {
            throw new HttpException(403, 'You do not have all permissions required to publish this curriculum.');
        }
        $this->transaction(function () use ($programmeId, $versionId, $userId, $ip, $agent): void {
            $version = $this->curricula->versionForUpdate($versionId);
            if ($version === null || (int) $version['programme_id'] !== $programmeId) {
                throw new HttpException(404, 'Curriculum version not found.');
            }
            if ($this->curricula->placements($versionId) === []) {
                throw new HttpException(409, 'Add at least one course before publishing the curriculum.');
            }
            $this->curricula->publish($programmeId, $versionId);
            $this->audit->record($userId, 'curriculum.published', 'curriculum_version', $versionId, $ip, $agent, ['programme_id' => $programmeId]);
        });
    }

    public function canPublish(int $programmeId, int $userId): bool
    {
        return $this->programmes->canAccess($userId, $programmeId)
            && $this->authorization->can($userId, 'programmes.edit')
            && $this->authorization->can($userId, 'programmes.publish')
            && $this->authorization->can($userId, 'content.approve');
    }

    /** @param array<string,mixed> $version */
    private function requireLiveAccess(array $version, int $programmeId, int $userId): void
    {
        if ($version['status'] === 'published' && !$this->canPublish($programmeId, $userId)) {
            throw new HttpException(403, 'Editing a published curriculum requires publishing access.');
        }
        if (in_array($version['status'], ['under_review', 'approved', 'archived'], true)) {
            throw new HttpException(409, 'This curriculum version cannot be edited in its current state.');
        }
    }

    private function requireAccess(int $programmeId, int $userId, string $permission): void
    {
        if (!$this->authorization->can($userId, $permission) || !$this->programmes->canAccess($userId, $programmeId)) {
            throw new HttpException(403, 'This curriculum is outside your programme access.');
        }
    }

    private function transaction(callable $operation): mixed
    {
        $connection = $this->curricula->connection();
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
