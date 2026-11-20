<?php

declare(strict_types=1);

namespace App\UserEvent\Domain\Service\Rule;

use Aljerom\SymfonyBoilerplate\DomainEvents\DomainEventInterface;
use App\Board\Domain\Event\CommentCreated;
use App\UserEvent\Domain\Enum\UserEventType;
use App\UserEvent\Domain\Port\CardParticipantsProvider;
use App\UserEvent\Domain\Service\RecipientRule;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class CommentCreatedRule implements RecipientRule
{
    public function __construct(private CardParticipantsProvider $participants)
    {
    }

    public function supports(DomainEventInterface $event): bool
    {
        return $event instanceof CommentCreated;
    }

    public function type(): UserEventType
    {
        return UserEventType::CommentCreated;
    }

    public function recipients(DomainEventInterface $event): array
    {
        if (!$event instanceof CommentCreated) {
            return [];
        }

        $cardId = Uuid::fromString($event->cardId);
        $userIds = $this->participants->assigneeIds($cardId);
        $creatorId = $this->participants->creatorId($cardId);
        if ($creatorId !== null) {
            $userIds[] = $creatorId;
        }

        return $userIds;
    }
}
