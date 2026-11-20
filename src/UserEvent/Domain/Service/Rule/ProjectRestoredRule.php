<?php

declare(strict_types=1);

namespace App\UserEvent\Domain\Service\Rule;

use Aljerom\SymfonyBoilerplate\DomainEvents\DomainEventInterface;
use App\Project\Domain\Event\ProjectRestored;
use App\UserEvent\Domain\Enum\UserEventType;
use App\UserEvent\Domain\Port\ProjectMembersProvider;
use App\UserEvent\Domain\Service\RecipientRule;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class ProjectRestoredRule implements RecipientRule
{
    public function __construct(private ProjectMembersProvider $members)
    {
    }

    public function supports(DomainEventInterface $event): bool
    {
        return $event instanceof ProjectRestored;
    }

    public function type(): UserEventType
    {
        return UserEventType::ProjectRestored;
    }

    public function recipients(DomainEventInterface $event): array
    {
        if (!$event instanceof ProjectRestored) {
            return [];
        }

        return $this->members->memberIds(Uuid::fromString($event->projectId));
    }
}
