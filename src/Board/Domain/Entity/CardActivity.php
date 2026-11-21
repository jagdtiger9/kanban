<?php

declare(strict_types=1);

namespace App\Board\Domain\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'card_activities')]
#[ORM\Index(name: 'idx_card_activities_created_at', columns: ['created_at', 'id'])]
#[ORM\Index(name: 'idx_card_activities_card_created', columns: ['card_id', 'created_at', 'id'])]
final class CardActivity
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ORM\Column(type: 'uuid')]
    private UuidInterface $cardId;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?UuidInterface $actorId;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $type;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $payload;

    #[ORM\Column(type: 'datetimetz_micro', columnDefinition: 'TIMESTAMP(6) WITH TIME ZONE')]
    private \DateTimeImmutable $createdAt;

    /**
     * @param array<string, mixed> $payload
     */
    private function __construct(
        UuidInterface $id,
        UuidInterface $cardId,
        ?UuidInterface $actorId,
        string $type,
        array $payload,
        \DateTimeImmutable $createdAt,
    ) {
        $this->id = $id;
        $this->cardId = $cardId;
        $this->actorId = $actorId;
        $this->type = $type;
        $this->payload = $payload;
        $this->createdAt = $createdAt;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function create(
        UuidInterface $id,
        UuidInterface $cardId,
        ?UuidInterface $actorId,
        string $type,
        array $payload,
    ): self {
        $type = trim($type);
        if ($type === '') {
            throw new \InvalidArgumentException('Activity type must not be empty');
        }

        return new self($id, $cardId, $actorId, $type, $payload, new \DateTimeImmutable());
    }

    public function id(): UuidInterface
    {
        return $this->id;
    }

    public function cardId(): UuidInterface
    {
        return $this->cardId;
    }

    public function actorId(): ?UuidInterface
    {
        return $this->actorId;
    }

    public function type(): string
    {
        return $this->type;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return $this->payload;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
