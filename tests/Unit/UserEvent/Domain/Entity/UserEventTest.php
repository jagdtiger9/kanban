<?php

declare(strict_types=1);

namespace App\Tests\Unit\UserEvent\Domain\Entity;

use App\UserEvent\Domain\Entity\UserEvent;
use App\UserEvent\Domain\Enum\UserEventType;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class UserEventTest extends TestCase
{
    public function testRecordFillsFields(): void
    {
        $id = Uuid::uuid4();
        $userId = Uuid::uuid4();
        $aggregateRootId = Uuid::uuid4();
        $projectId = Uuid::uuid4();
        $cardId = Uuid::uuid4();
        $actorId = Uuid::uuid4();
        $occurredOn = new \DateTimeImmutable('2026-11-20 10:00:00.123456+00:00');

        $event = UserEvent::record(
            $id,
            $userId,
            UserEventType::CardAssigned,
            $aggregateRootId,
            $occurredOn,
            ['actorName' => 'Alice', 'projectName' => 'Kanban', 'cardTitle' => 'Card'],
            $projectId,
            $cardId,
            $actorId,
        );

        self::assertTrue($id->equals($event->id()));
        self::assertTrue($userId->equals($event->userId()));
        self::assertSame(UserEventType::CardAssigned, $event->type());
        self::assertTrue($aggregateRootId->equals($event->aggregateRootId()));
        self::assertTrue($projectId->equals($event->projectId()));
        self::assertTrue($cardId->equals($event->cardId()));
        self::assertTrue($actorId->equals($event->actorId()));
        self::assertSame(
            ['actorName' => 'Alice', 'projectName' => 'Kanban', 'cardTitle' => 'Card'],
            $event->payload(),
        );
        self::assertSame($occurredOn->format('U.u'), $event->occurredOn()->format('U.u'));
        self::assertNull($event->seenAt());
        self::assertEqualsWithDelta(time(), $event->createdAt()->getTimestamp(), 5);
    }

    public function testRecordDefaultsOptionalReferencesToNull(): void
    {
        $event = UserEvent::record(
            Uuid::uuid4(),
            Uuid::uuid4(),
            UserEventType::ProjectArchived,
            Uuid::uuid4(),
            new \DateTimeImmutable(),
            ['actorName' => 'Alice', 'projectName' => 'Kanban'],
        );

        self::assertNull($event->projectId());
        self::assertNull($event->cardId());
        self::assertNull($event->actorId());
    }
}
