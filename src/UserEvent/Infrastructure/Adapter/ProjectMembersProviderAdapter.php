<?php

declare(strict_types=1);

namespace App\UserEvent\Infrastructure\Adapter;

use App\Project\Domain\Entity\ProjectMember;
use App\Project\Domain\Repository\ProjectMemberRepository;
use App\UserEvent\Domain\Port\ProjectMembersProvider;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(ProjectMembersProvider::class)]
final class ProjectMembersProviderAdapter implements ProjectMembersProvider
{
    public function __construct(private ProjectMemberRepository $members)
    {
    }

    public function memberIds(UuidInterface $projectId): array
    {
        return array_map(
            static fn(ProjectMember $member): UuidInterface => $member->userId(),
            $this->members->findByProject($projectId),
        );
    }
}
