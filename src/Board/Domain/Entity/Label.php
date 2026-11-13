<?php

declare(strict_types=1);

namespace App\Board\Domain\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'labels')]
final class Label
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ORM\Column(type: 'uuid')]
    private UuidInterface $boardId;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $name;

    #[ORM\Column(type: Types::STRING, length: 7)]
    private string $color;

    #[ORM\Column(type: 'datetimetz_micro', columnDefinition: 'TIMESTAMP(6) WITH TIME ZONE')]
    private \DateTimeImmutable $createdAt;

    private function __construct(
        UuidInterface $id,
        UuidInterface $boardId,
        string $name,
        string $color,
        \DateTimeImmutable $createdAt,
    ) {
        $this->id = $id;
        $this->boardId = $boardId;
        $this->name = $name;
        $this->color = $color;
        $this->createdAt = $createdAt;
    }

    public static function create(UuidInterface $id, UuidInterface $boardId, string $name, string $color): self
    {
        $name = trim($name);
        $color = trim($color);
        if ($name === '') {
            throw new \InvalidArgumentException('Label name must not be empty');
        }
        if ($color === '') {
            throw new \InvalidArgumentException('Label color must not be empty');
        }

        return new self($id, $boardId, $name, $color, new \DateTimeImmutable());
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

    public function color(): string
    {
        return $this->color;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function rename(string $name): void
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Label name must not be empty');
        }

        $this->name = $name;
    }

    public function changeColor(string $color): void
    {
        $color = trim($color);
        if ($color === '') {
            throw new \InvalidArgumentException('Label color must not be empty');
        }

        $this->color = $color;
    }
}
