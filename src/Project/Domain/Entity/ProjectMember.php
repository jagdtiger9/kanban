<?php

declare(strict_types=1);

namespace App\Project\Domain\Entity;

use App\Project\Domain\Enum\MemberRole;
use App\Project\Domain\Event\ProjectMemberRoleChanged;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'project_members')]
#[ORM\UniqueConstraint(name: 'uniq_project_member', columns: ['project_id', 'user_id'])]
final class ProjectMember
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ORM\Column(type: 'uuid')]
    private UuidInterface $projectId;

    #[ORM\Column(type: 'uuid')]
    private UuidInterface $userId;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: MemberRole::class)]
    private MemberRole $role;

    #[ORM\Column(type: 'datetimetz_micro', columnDefinition: 'TIMESTAMP(6) WITH TIME ZONE')]
    private \DateTimeImmutable $joinedAt;

    private function __construct(
        UuidInterface $id,
        UuidInterface $projectId,
        UuidInterface $userId,
        MemberRole $role,
        \DateTimeImmutable $joinedAt,
    ) {
        $this->id = $id;
        $this->projectId = $projectId;
        $this->userId = $userId;
        $this->role = $role;
        $this->joinedAt = $joinedAt;
    }

    public static function create(
        UuidInterface $id,
        UuidInterface $projectId,
        UuidInterface $userId,
        MemberRole $role,
    ): self {
        return new self($id, $projectId, $userId, $role, new \DateTimeImmutable());
    }

    public function id(): UuidInterface
    {
        return $this->id;
    }

    public function projectId(): UuidInterface
    {
        return $this->projectId;
    }

    public function userId(): UuidInterface
    {
        return $this->userId;
    }

    public function role(): MemberRole
    {
        return $this->role;
    }

    public function isAdmin(): bool
    {
        return $this->role === MemberRole::Admin;
    }

    public function joinedAt(): \DateTimeImmutable
    {
        return $this->joinedAt;
    }

    public function changeRole(MemberRole $role, UuidInterface $actorId): ?ProjectMemberRoleChanged
    {
        if ($role === $this->role) {
            return null;
        }

        $this->role = $role;

        return new ProjectMemberRoleChanged($this->projectId, $this->userId, $role, $actorId);
    }
}
