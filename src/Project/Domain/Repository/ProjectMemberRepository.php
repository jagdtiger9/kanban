<?php

declare(strict_types=1);

namespace App\Project\Domain\Repository;

use App\Project\Domain\Entity\ProjectMember;
use Ramsey\Uuid\UuidInterface;

interface ProjectMemberRepository
{
    public function save(ProjectMember $member): void;

    public function findByProjectAndUser(UuidInterface $projectId, UuidInterface $userId): ?ProjectMember;

    /** @return list<ProjectMember> ordered by join time */
    public function findByProject(UuidInterface $projectId): array;

    /** @return list<ProjectMember> */
    public function findByUser(UuidInterface $userId): array;

    /**
     * @param list<UuidInterface> $projectIds
     *
     * @return array<string, int> member count keyed by project id
     */
    public function countByProjects(array $projectIds): array;

    public function remove(ProjectMember $member): void;
}
