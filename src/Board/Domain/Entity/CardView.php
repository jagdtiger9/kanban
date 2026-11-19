<?php

declare(strict_types=1);

namespace App\Board\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'card_views')]
#[ORM\UniqueConstraint(name: 'uniq_card_views_user_card', columns: ['user_id', 'card_id'])]
final class CardView
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ORM\Column(type: 'uuid')]
    private UuidInterface $userId;

    #[ORM\Column(type: 'uuid')]
    private UuidInterface $cardId;

    #[ORM\Column(type: 'datetimetz_micro', columnDefinition: 'TIMESTAMP(6) WITH TIME ZONE')]
    private \DateTimeImmutable $lastSeenAt;

    private function __construct(
        UuidInterface $id,
        UuidInterface $userId,
        UuidInterface $cardId,
        \DateTimeImmutable $lastSeenAt,
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->cardId = $cardId;
        $this->lastSeenAt = $lastSeenAt;
    }

    public static function create(UuidInterface $id, UuidInterface $userId, UuidInterface $cardId): self
    {
        return new self($id, $userId, $cardId, new \DateTimeImmutable());
    }

    public function markSeen(\DateTimeImmutable $at): void
    {
        if ($at > $this->lastSeenAt) {
            $this->lastSeenAt = $at;
        }
    }

    public function id(): UuidInterface
    {
        return $this->id;
    }

    public function userId(): UuidInterface
    {
        return $this->userId;
    }

    public function cardId(): UuidInterface
    {
        return $this->cardId;
    }

    public function lastSeenAt(): \DateTimeImmutable
    {
        return $this->lastSeenAt;
    }
}
