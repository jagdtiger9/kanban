<?php

declare(strict_types=1);

namespace App\UserEvent\Domain\Service\Rule;

use Aljerom\SymfonyBoilerplate\DomainEvents\DomainEventInterface;
use App\Project\Domain\Event\ProjectArchived;
use App\UserEvent\Domain\Enum\UserEventType;
use App\UserEvent\Domain\Port\ProjectMembersProvider;
use App\UserEvent\Domain\Service\RecipientRule;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class ProjectArchivedRule implements RecipientRule
{
    public function __construct(private ProjectMembersProvider $members)
    {
    }

    public function supports(DomainEventInterface $event): bool
    {
        return $event instanceof ProjectArchived;
    }

    public function type(): UserEventType
    {
        return UserEventType::ProjectArchived;
    }

    public function recipients(DomainEventInterface $event): array
    {
        if (!$event instanceof ProjectArchived) {
            return [];
        }

        return $this->members->memberIds(Uuid::fromString($event->projectId));
    }
}
