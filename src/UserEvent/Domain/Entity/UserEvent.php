<?php

declare(strict_types=1);

namespace App\UserEvent\Domain\Entity;

use App\UserEvent\Domain\Enum\UserEventType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'user_events')]
#[ORM\UniqueConstraint(
    name: 'uniq_user_events_recipient_event',
    columns: ['user_id', 'type', 'aggregate_root_id', 'occurred_on'],
)]
#[ORM\Index(name: 'idx_user_events_list', columns: ['user_id', 'occurred_on', 'id'])]
final class UserEvent
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ORM\Column(type: 'uuid')]
    private UuidInterface $userId;

    #[ORM\Column(type: Types::STRING, length: 40, enumType: UserEventType::class)]
    private UserEventType $type;

    #[ORM\Column(type: 'uuid')]
    private UuidInterface $aggregateRootId;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?UuidInterface $projectId;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?UuidInterface $cardId;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?UuidInterface $actorId;

    /** @var array<string, mixed> */
    #[ORM\Column(type: Types::JSON)]
    private array $payload;

    #[ORM\Column(type: 'datetimetz_micro', columnDefinition: 'TIMESTAMP(6) WITH TIME ZONE')]
    private \DateTimeImmutable $occurredOn;

    #[ORM\Column(type: 'datetimetz_micro', columnDefinition: 'TIMESTAMP(6) WITH TIME ZONE')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetimetz_micro', columnDefinition: 'TIMESTAMP(6) WITH TIME ZONE', nullable: true)]
    private ?\DateTimeImmutable $seenAt;

    /**
     * @param array<string, mixed> $payload
     */
    private function __construct(
        UuidInterface $id,
        UuidInterface $userId,
        UserEventType $type,
        UuidInterface $aggregateRootId,
        ?UuidInterface $projectId,
        ?UuidInterface $cardId,
        ?UuidInterface $actorId,
        array $payload,
        \DateTimeImmutable $occurredOn,
        \DateTimeImmutable $createdAt,
        ?\DateTimeImmutable $seenAt,
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->type = $type;
        $this->aggregateRootId = $aggregateRootId;
        $this->projectId = $projectId;
        $this->cardId = $cardId;
        $this->actorId = $actorId;
        $this->payload = $payload;
        $this->occurredOn = $occurredOn;
        $this->createdAt = $createdAt;
        $this->seenAt = $seenAt;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function record(
        UuidInterface $id,
        UuidInterface $userId,
        UserEventType $type,
        UuidInterface $aggregateRootId,
        \DateTimeImmutable $occurredOn,
        array $payload,
        ?UuidInterface $projectId = null,
        ?UuidInterface $cardId = null,
        ?UuidInterface $actorId = null,
    ): self {
        return new self(
            $id,
            $userId,
            $type,
            $aggregateRootId,
            $projectId,
            $cardId,
            $actorId,
            $payload,
            $occurredOn,
            new \DateTimeImmutable(),
            null,
        );
    }

    public function id(): UuidInterface
    {
        return $this->id;
    }

    public function userId(): UuidInterface
    {
        return $this->userId;
    }

    public function type(): UserEventType
    {
        return $this->type;
    }

    public function aggregateRootId(): UuidInterface
    {
        return $this->aggregateRootId;
    }

    public function projectId(): ?UuidInterface
    {
        return $this->projectId;
    }

    public function cardId(): ?UuidInterface
    {
        return $this->cardId;
    }

    public function actorId(): ?UuidInterface
    {
        return $this->actorId;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return $this->payload;
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function seenAt(): ?\DateTimeImmutable
    {
        return $this->seenAt;
    }
}
