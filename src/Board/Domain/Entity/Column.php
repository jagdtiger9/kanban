<?php

declare(strict_types=1);

namespace App\Board\Domain\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'columns')]
final class Column
{
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

    public static function create(UuidInterface $id, UuidInterface $boardId, string $name, int $position): self
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Column name must not be empty');
        }
        if ($position < 0) {
            throw new \InvalidArgumentException('Column position must not be negative');
        }

        return new self($id, $boardId, $name, $position, new \DateTimeImmutable());
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

    public function rename(string $name): void
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Column name must not be empty');
        }

        $this->name = $name;
    }

    public function changePosition(int $position): void
    {
        if ($position < 0) {
            throw new \InvalidArgumentException('Column position must not be negative');
        }

        $this->position = $position;
    }
}
