<?php

declare(strict_types=1);

namespace App\UserEvent\Domain\Service\Rule;

use Aljerom\SymfonyBoilerplate\DomainEvents\DomainEventInterface;
use App\Board\Domain\Event\CardExecutorUnassigned;
use App\UserEvent\Domain\Enum\UserEventType;
use App\UserEvent\Domain\Service\RecipientRule;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class CardExecutorUnassignedRule implements RecipientRule
{
    public function supports(DomainEventInterface $event): bool
    {
        return $event instanceof CardExecutorUnassigned;
    }

    public function type(): UserEventType
    {
        return UserEventType::CardExecutorUnassigned;
    }

    public function recipients(DomainEventInterface $event): array
    {
        if (!$event instanceof CardExecutorUnassigned) {
            return [];
        }

        return [Uuid::fromString($event->userId)];
    }
}
