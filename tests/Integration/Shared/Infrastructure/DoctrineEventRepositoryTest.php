<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure;

use Aljerom\SymfonyBoilerplate\DomainEvents\DomainEventInterface;
use Aljerom\SymfonyBoilerplate\DomainEvents\EventRepositoryInterface;
use App\Board\Domain\Event\CardCreated;
use App\Shared\Domain\Entity\DomainEvent;
use App\Shared\Infrastructure\Doctrine\DoctrineEventRepository;
use App\Tests\Integration\DatabaseTestCase;
use DateTimeImmutable;
use Ramsey\Uuid\Uuid;

final class DoctrineEventRepositoryTest extends DatabaseTestCase
{
    private EventRepositoryInterface $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = $this->repo(EventRepositoryInterface::class);
    }

    public function testAppendStoresSerializedEventAndPublishMarksItPublished(): void
    {
        self::assertInstanceOf(DoctrineEventRepository::class, $this->repo);

        $cardId = Uuid::uuid4()->toString();
        $actorId = Uuid::uuid4()->toString();
        $event = new CardCreated($cardId, $actorId);

        $this->repo->append($event);
        $this->em()->flush();

        $unpublished = $this->repo->getUnpublished(new DateTimeImmutable('+1 second'));

        self::assertCount(1, $unpublished);
        $stored = $unpublished[0];
        self::assertInstanceOf(DomainEvent::class, $stored);
        self::assertSame(CardCreated::class, $stored->getTypeName());
        self::assertSame($cardId, $stored->getAggregateRoot());
        self::assertSame(
            $event->getOccurredOn(),
            $stored->getOccurredOn()->format(DomainEventInterface::MICROSECOND_DATE_FORMAT),
        );

        $body = json_decode($stored->getEventBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($cardId, $body['cardId']);
        self::assertSame($actorId, $body['actorId']);

        $this->repo->publish($stored);

        self::assertSame([], $this->repo->getUnpublished(new DateTimeImmutable('+1 second')));
    }

    public function testGetUnpublishedReturnsOnlyFutureCutoffEvents(): void
    {
        $this->repo->append(new CardCreated(Uuid::uuid4()->toString(), Uuid::uuid4()->toString()));
        $this->em()->flush();

        self::assertSame([], $this->repo->getUnpublished(new DateTimeImmutable('-1 day')));
        self::assertCount(1, $this->repo->getUnpublished(new DateTimeImmutable('+1 second')));
    }
}
