<?php

declare(strict_types=1);

namespace App\Tests\Unit\Call\Domain;

use App\Call\Domain\Exception\NotInRoomException;
use App\Call\Domain\Exception\RoomFullException;
use App\Call\Domain\Model\Participant;
use App\Call\Domain\Model\Room;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class RoomTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-11-18 12:00:00+00:00');
    }

    public function testJoinAddsParticipantAndTracksChange(): void
    {
        $room = new Room(Uuid::uuid4());

        self::assertNull($room->join('u1', 'session-1', $this->now, 4));

        self::assertSame(['u1'], $room->userIds());
        self::assertArrayHasKey('u1', $room->changedParticipants());
    }

    public function testJoinFromAnotherTabReplacesSession(): void
    {
        $room = new Room(Uuid::uuid4(), [new Participant('u1', 'session-1', $this->now)]);

        self::assertSame('session-1', $room->join('u1', 'session-2', $this->now, 4));
        self::assertSame('session-2', $room->participants()[0]->sessionId);
    }

    public function testRejoinWithSameSessionReplacesNothing(): void
    {
        $room = new Room(Uuid::uuid4(), [new Participant('u1', 'session-1', $this->now)]);

        self::assertNull($room->join('u1', 'session-1', $this->now, 4));
    }

    public function testFullRoomRejectsNewcomerButNotExistingUser(): void
    {
        $room = new Room(Uuid::uuid4(), [
            new Participant('u1', 's1', $this->now),
            new Participant('u2', 's2', $this->now),
        ]);

        $room->join('u2', 's2-new', $this->now, 2);

        $this->expectException(RoomFullException::class);
        $room->join('u3', 's3', $this->now, 2);
    }

    public function testLeaveIgnoresForeignSession(): void
    {
        $room = new Room(Uuid::uuid4(), [new Participant('u1', 'session-1', $this->now)]);

        self::assertFalse($room->leave('u1', 'session-old'));
        self::assertTrue($room->leave('u1', 'session-1'));
        self::assertTrue($room->isEmpty());
        self::assertSame(['u1'], $room->removedUserIds());
    }

    public function testHeartbeatRefreshesOnlyOwnSession(): void
    {
        $room = new Room(Uuid::uuid4(), [new Participant('u1', 'session-1', $this->now)]);
        $later = $this->now->modify('+30 seconds');

        self::assertFalse($room->heartbeat('u1', 'session-2', $later));
        self::assertTrue($room->heartbeat('u1', 'session-1', $later));
        self::assertEquals($later, $room->participants()[0]->lastSeenAt);
    }

    public function testExpireDropsStaleParticipants(): void
    {
        $room = new Room(Uuid::uuid4(), [
            new Participant('stale', 's1', $this->now->modify('-61 seconds')),
            new Participant('fresh', 's2', $this->now->modify('-10 seconds')),
        ]);

        self::assertSame(['stale'], $room->expire($this->now, 60));
        self::assertSame(['fresh'], $room->userIds());
        self::assertSame(['stale'], $room->removedUserIds());
    }

    public function testRequireParticipant(): void
    {
        $room = new Room(Uuid::uuid4(), [new Participant('u1', 's1', $this->now)]);
        $room->requireParticipant('u1');

        $this->expectException(NotInRoomException::class);
        $room->requireParticipant('u2');
    }
}
