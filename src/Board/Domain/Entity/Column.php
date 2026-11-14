<?php

declare(strict_types=1);

namespace App\Board\Domain\Entity;

use Aljerom\SymfonyBoilerplate\DomainEvents\DomainEventAwareInterface;
use Aljerom\SymfonyBoilerplate\DomainEvents\EventStoreTrait;
use App\Board\Domain\Event\ColumnCreated;
use App\Board\Domain\Event\ColumnDeleted;
use App\Board\Domain\Event\ColumnRenamed;
use App\Board\Domain\Event\ColumnReordered;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'columns')]
final class Column implements DomainEventAwareInterface
{
    use EventStoreTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ORM\Column(type: 'uuid')]
    private UuidInterface $boardId;

    #[ORM\Column(type: Types::STRING, length: 100)]
    private string $name;

    #[ORM\Column(type: Types::INTEGER)]
    private int $position;

    #[ORM\Column(type: 'datetimetz_micro', columnDefinition: 'TIMESTAMP(6) WITH TIME ZONE')]
    private \DateTimeImmutable $createdAt;

    private function __construct(
        UuidInterface $id,
        UuidInterface $boardId,
        string $name,
        int $position,
        \DateTimeImmutable $createdAt,
    ) {
        $this->id = $id;
        $this->boardId = $boardId;
        $this->name = $name;
        $this->position = $position;
        $this->createdAt = $createdAt;
    }

    public static function create(
        UuidInterface $id,
        UuidInterface $boardId,
        string $name,
        int $position,
        ?UuidInterface $actorId = null,
    ): self {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Column name must not be empty');
        }
        if ($position < 0) {
            throw new \InvalidArgumentException('Column position must not be negative');
        }

        $column = new self($id, $boardId, $name, $position, new \DateTimeImmutable());
        $column->recordEvent(new ColumnCreated($boardId->toString(), $id->toString(), $actorId?->toString()));

        return $column;
    }

    public function id(): UuidInterface
    {
        return $this->id;
    }

    public function boardId(): UuidInterface
    {
        return $this->boardId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function rename(string $name, ?UuidInterface $actorId = null): void
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Column name must not be empty');
        }
        if ($name === $this->name) {
            return;
        }

        $this->name = $name;
        $this->recordEvent(new ColumnRenamed($this->boardId->toString(), $this->id->toString(), $actorId?->toString()));
    }

    public function changePosition(int $position): void
    {
        if ($position < 0) {
            throw new \InvalidArgumentException('Column position must not be negative');
        }

        $this->position = $position;
    }

    public function recordReordered(?UuidInterface $actorId = null): void
    {
        $this->recordEvent(new ColumnReordered($this->boardId->toString(), $this->id->toString(), $actorId?->toString()));
    }

    public function delete(?UuidInterface $actorId = null): void
    {
        $this->recordEvent(new ColumnDeleted($this->boardId->toString(), $this->id->toString(), $actorId?->toString()));
    }
}
