<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Domain\Entity;

use App\Board\Domain\Entity\CardOccurrence;
use App\Board\Domain\Event\CardOccurrenceCompleted;
use App\Board\Domain\Event\CardOccurrenceUncompleted;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class CardOccurrenceTest extends TestCase
{
    public function testCreateNormalizesPeriodAndRecordsCompletedEvent(): void
    {
        $id = Uuid::uuid4();
        $cardId = Uuid::uuid4();
        $userId = Uuid::uuid4();

        $occurrence = CardOccurrence::create($id, $cardId, new \DateTimeImmutable('2026-11-26 15:45:12'), $userId);

        self::assertTrue($occurrence->id()->equals($id));
        self::assertTrue($occurrence->cardId()->equals($cardId));
        self::assertSame('2026-11-26 00:00:00', $occurrence->period()->format('Y-m-d H:i:s'));
        self::assertSame($userId->toString(), $occurrence->completedBy()?->toString());

        $events = $occurrence->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CardOccurrenceCompleted::class, $events[0]);
        self::assertSame($cardId->toString(), $events[0]->cardId);
        self::assertSame('2026-11-26', $events[0]->period);
        self::assertSame($userId->toString(), $events[0]->actorId);
    }

    public function testUncompleteRecordsEventWithActor(): void
    {
        $cardId = Uuid::uuid4();
        $occurrence = CardOccurrence::create(
            Uuid::uuid4(),
            $cardId,
            new \DateTimeImmutable('2026-11-26'),
            Uuid::uuid4(),
        );
        $occurrence->releaseEvents();
        $actorId = Uuid::uuid4();

        $occurrence->uncomplete($actorId);

        $events = $occurrence->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CardOccurrenceUncompleted::class, $events[0]);
        self::assertSame($cardId->toString(), $events[0]->cardId);
        self::assertSame('2026-11-26', $events[0]->period);
        self::assertSame($actorId->toString(), $events[0]->actorId);
    }

    public function testCompletedAtIsSetOnCreation(): void
    {
        $before = new \DateTimeImmutable();

        $occurrence = CardOccurrence::create(
            Uuid::uuid4(),
            Uuid::uuid4(),
            new \DateTimeImmutable('2026-11-26'),
            Uuid::uuid4(),
        );

        self::assertGreaterThanOrEqual($before, $occurrence->completedAt());
    }
}
