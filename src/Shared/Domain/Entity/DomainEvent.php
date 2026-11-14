<?php

declare(strict_types=1);

namespace App\Shared\Domain\Entity;

use Aljerom\SymfonyBoilerplate\DomainEvents\EventId;
use Aljerom\SymfonyBoilerplate\DomainEvents\StoredEvent;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'domain_event')]
#[ORM\Index(name: 'domain_event_unpublished_idx', columns: ['request_id', 'published_on', 'occurred_on'])]
final class DomainEvent extends StoredEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT)]
    private ?int $id = null;

    #[ORM\Column(type: 'uuid', unique: true)]
    private UuidInterface $eventId;

    #[ORM\Column(type: 'datetimetz_micro', columnDefinition: 'TIMESTAMP(6) WITH TIME ZONE')]
    private DateTimeImmutable $occurredOn;

    #[ORM\Column(type: Types::INTEGER)]
    private int $publishedOn = 0;

    #[ORM\Column(type: 'uuid')]
    private UuidInterface $aggregateRoot;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $typeName;

    #[ORM\Column(type: Types::TEXT)]
    private string $eventBody;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $requestId;

    public function __construct(
        EventId $eventId,
        string $typeName,
        DateTimeImmutable $occurredOn,
        string $aggregateRoot,
        string $eventBody,
        string $requestId,
    ) {
        $this->eventId = Uuid::fromString($eventId->asString());
        $this->typeName = $typeName;
        $this->occurredOn = $occurredOn;
        $this->aggregateRoot = Uuid::fromString($aggregateRoot);
        $this->eventBody = $eventBody;
        $this->requestId = $requestId;
    }

    public function getEventId(): EventId
    {
        return EventId::fromUuid($this->eventId);
    }

    public function getTypeName(): string
    {
        return $this->typeName;
    }

    public function getOccurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function getAggregateRoot(): string
    {
        return $this->aggregateRoot->toString();
    }

    public function getEventBody(): string
    {
        return $this->eventBody;
    }

    public function getRequestId(): string
    {
        return $this->requestId;
    }

    public function setPublishedOn(DateTimeImmutable $publishedOn): void
    {
        $this->publishedOn = $publishedOn->getTimestamp();
    }
}
