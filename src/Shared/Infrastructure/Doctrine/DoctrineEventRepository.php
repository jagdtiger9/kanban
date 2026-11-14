<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine;

use Aljerom\SymfonyBoilerplate\DomainEvents\Doctrine\AbstractDoctrineEventRepository;
use Aljerom\SymfonyBoilerplate\DomainEvents\EventId;
use Aljerom\SymfonyBoilerplate\DomainEvents\StoredEvent;
use App\Shared\Domain\Entity\DomainEvent;
use DateTimeImmutable;

final class DoctrineEventRepository extends AbstractDoctrineEventRepository
{
    protected function getEntityClass(): string
    {
        return DomainEvent::class;
    }

    protected function createStoredEvent(
        EventId $eventId,
        string $typeName,
        DateTimeImmutable $occurredOn,
        string $aggregateRoot,
        string $eventBody,
        string $requestId,
    ): StoredEvent {
        return new DomainEvent($eventId, $typeName, $occurredOn, $aggregateRoot, $eventBody, $requestId);
    }
}
