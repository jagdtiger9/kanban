<?php

declare(strict_types=1);

namespace App\UserEvent\Domain\Service\Rule;

use Aljerom\SymfonyBoilerplate\DomainEvents\DomainEventInterface;
use App\Board\Domain\Event\CardExecutorAssigned;
use App\UserEvent\Domain\Enum\UserEventType;
use App\UserEvent\Domain\Service\RecipientRule;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class CardExecutorAssignedRule implements RecipientRule
{
    public function supports(DomainEventInterface $event): bool
    {
        return $event instanceof CardExecutorAssigned;
    }

    public function type(): UserEventType
    {
        return UserEventType::CardExecutorAssigned;
    }

    public function recipients(DomainEventInterface $event): array
    {
        if (!$event instanceof CardExecutorAssigned) {
            return [];
        }

        return [Uuid::fromString($event->userId)];
    }
}
