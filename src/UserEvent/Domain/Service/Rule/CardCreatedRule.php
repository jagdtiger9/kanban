<?php

declare(strict_types=1);

namespace App\UserEvent\Domain\Service\Rule;

use Aljerom\SymfonyBoilerplate\DomainEvents\DomainEventInterface;
use App\Board\Domain\Event\CardCreated;
use App\UserEvent\Domain\Enum\UserEventType;
use App\UserEvent\Domain\Port\CardParticipantsProvider;
use App\UserEvent\Domain\Port\ProjectMembersProvider;
use App\UserEvent\Domain\Service\RecipientRule;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class CardCreatedRule implements RecipientRule
{
    public function __construct(
        private CardParticipantsProvider $cards,
        private ProjectMembersProvider $members,
    ) {
    }

    public function supports(DomainEventInterface $event): bool
    {
        return $event instanceof CardCreated;
    }

    public function type(): UserEventType
    {
        return UserEventType::CardCreated;
    }

    /** @return list<UuidInterface> */
    public function recipients(DomainEventInterface $event): array
    {
        if (!$event instanceof CardCreated) {
            return [];
        }

        $projectId = $this->cards->projectId(Uuid::fromString($event->cardId));

        return $projectId === null ? [] : $this->members->memberIds($projectId);
    }
}
