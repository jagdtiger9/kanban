<?php

declare(strict_types=1);

namespace App\Board\Domain\Entity;

use Aljerom\SymfonyBoilerplate\DomainEvents\DomainEventAwareInterface;
use Aljerom\SymfonyBoilerplate\DomainEvents\EventStoreTrait;
use App\Board\Domain\Event\CardOccurrenceCompleted;
use App\Board\Domain\Event\CardOccurrenceUncompleted;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'card_occurrences')]
#[ORM\UniqueConstraint(name: 'uniq_card_occurrences_card_period', columns: ['card_id', 'period'])]
final class CardOccurrence implements DomainEventAwareInterface
{
    use EventStoreTrait;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $id;

    #[ORM\Column(type: 'uuid')]
    private UuidInterface $cardId;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $period;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?UuidInterface $completedBy;

    #[ORM\Column(type: 'datetimetz_micro', columnDefinition: 'TIMESTAMP(6) WITH TIME ZONE')]
    private \DateTimeImmutable $completedAt;

    private function __construct(
        UuidInterface $id,
        UuidInterface $cardId,
        \DateTimeImmutable $period,
        UuidInterface $completedBy,
        \DateTimeImmutable $completedAt,
    ) {
        $this->id = $id;
        $this->cardId = $cardId;
        $this->period = $period;
        $this->completedBy = $completedBy;
        $this->completedAt = $completedAt;
    }

    public static function create(
        UuidInterface $id,
        UuidInterface $cardId,
        \DateTimeImmutable $period,
        UuidInterface $completedBy,
    ): self {
        $occurrence = new self(
            $id,
            $cardId,
            $period->setTime(0, 0),
            $completedBy,
            new \DateTimeImmutable(),
        );
        $occurrence->recordEvent(
            new CardOccurrenceCompleted(
                $cardId->toString(),
                $occurrence->period->format('Y-m-d'),
                $completedBy->toString(),
            )
        );

        return $occurrence;
    }

    public function uncomplete(?UuidInterface $actorId = null): void
    {
        $this->recordEvent(
            new CardOccurrenceUncompleted(
                $this->cardId->toString(),
                $this->period->format('Y-m-d'),
                $actorId?->toString(),
            )
        );
    }

    public function id(): UuidInterface
    {
        return $this->id;
    }

    public function cardId(): UuidInterface
    {
        return $this->cardId;
    }

    public function period(): \DateTimeImmutable
    {
        return $this->period;
    }

    public function completedBy(): ?UuidInterface
    {
        return $this->completedBy;
    }

    public function completedAt(): \DateTimeImmutable
    {
        return $this->completedAt;
    }
}
