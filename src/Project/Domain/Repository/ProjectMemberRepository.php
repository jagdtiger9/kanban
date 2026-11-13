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

    public function remove(ProjectMember $member): void;
}
