<?php

declare(strict_types=1);

namespace App\UserEvent\Domain\Service\Rule;

use Aljerom\SymfonyBoilerplate\DomainEvents\DomainEventInterface;
use App\Project\Domain\Event\ProjectMemberRoleChanged;
use App\UserEvent\Domain\Enum\UserEventType;
use App\UserEvent\Domain\Service\RecipientRule;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class ProjectMemberRoleChangedRule implements RecipientRule
{
    public function supports(DomainEventInterface $event): bool
    {
        return $event instanceof ProjectMemberRoleChanged;
    }

    public function type(): UserEventType
    {
        return UserEventType::ProjectMemberRoleChanged;
    }

    public function recipients(DomainEventInterface $event): array
    {
        if (!$event instanceof ProjectMemberRoleChanged) {
            return [];
        }

        return [Uuid::fromString($event->userId)];
    }
}
