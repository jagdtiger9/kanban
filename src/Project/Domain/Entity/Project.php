<?php

declare(strict_types=1);

namespace App\Project\Domain\Entity;

use Aljerom\SymfonyBoilerplate\DomainEvents\DomainEventAwareInterface;
use Aljerom\SymfonyBoilerplate\DomainEvents\EventStoreTrait;
use App\Project\Domain\Event\ProjectArchived;
use App\Project\Domain\Event\ProjectRestored;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'projects')]
final class Project implements DomainEventAwareInterface
{
    use EventStoreTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $name;

    #[ORM\Column(type: 'datetimetz_micro', columnDefinition: 'TIMESTAMP(6) WITH TIME ZONE')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetimetz_micro', columnDefinition: 'TIMESTAMP(6) WITH TIME ZONE', nullable: true)]
    private ?\DateTimeImmutable $archivedAt;

    private function __construct(
        UuidInterface $id,
        string $name,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $archivedAt,
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->createdAt = $createdAt;
        $this->archivedAt = $archivedAt;
    }

    public static function create(UuidInterface $id, string $name): self
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Project name must not be empty');
        }

        return new self($id, $name, new \DateTimeImmutable(), null);
    }

    public function id(): UuidInterface
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function archivedAt(): ?\DateTimeImmutable
    {
        return $this->archivedAt;
    }

    public function isArchived(): bool
    {
        return $this->archivedAt !== null;
    }

    public function rename(string $name): void
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Project name must not be empty');
        }

        $this->name = $name;
    }

    public function archive(UuidInterface $actorId): void
    {
        if ($this->archivedAt !== null) {
            return;
        }

        $this->archivedAt = new \DateTimeImmutable();
        $this->recordEvent(new ProjectArchived($this->id->toString(), $actorId->toString()));
    }

    public function restore(UuidInterface $actorId): void
    {
        if ($this->archivedAt === null) {
            return;
        }

        $this->archivedAt = null;
        $this->recordEvent(new ProjectRestored($this->id->toString(), $actorId->toString()));
    }
}
