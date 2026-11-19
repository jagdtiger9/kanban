<?php

declare(strict_types=1);

namespace App\Board\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'project_views')]
#[ORM\UniqueConstraint(name: 'uniq_project_views_user_project', columns: ['user_id', 'project_id'])]
final class ProjectView
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ORM\Column(type: 'uuid')]
    private UuidInterface $userId;

    #[ORM\Column(type: 'uuid')]
    private UuidInterface $projectId;

    #[ORM\Column(type: 'datetimetz_micro', columnDefinition: 'TIMESTAMP(6) WITH TIME ZONE')]
    private \DateTimeImmutable $firstSeenAt;

    private function __construct(
        UuidInterface $id,
        UuidInterface $userId,
        UuidInterface $projectId,
        \DateTimeImmutable $firstSeenAt,
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->projectId = $projectId;
        $this->firstSeenAt = $firstSeenAt;
    }

    public static function create(UuidInterface $id, UuidInterface $userId, UuidInterface $projectId): self
    {
        return new self($id, $userId, $projectId, new \DateTimeImmutable());
    }

    public function id(): UuidInterface
    {
        return $this->id;
    }

    public function userId(): UuidInterface
    {
        return $this->userId;
    }

    public function projectId(): UuidInterface
    {
        return $this->projectId;
    }

    public function firstSeenAt(): \DateTimeImmutable
    {
        return $this->firstSeenAt;
    }
}
