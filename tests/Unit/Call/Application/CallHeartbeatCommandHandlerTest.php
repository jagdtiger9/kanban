<?php

declare(strict_types=1);

namespace App\Tests\Unit\Call\Application;

use App\Call\Application\Message\Command\CallHeartbeatCommand;
use App\Call\Application\MessageHandler\CallHeartbeatCommandHandler;
use App\Call\Domain\Model\Participant;

final class CallHeartbeatCommandHandlerTest extends CallFixture
{
    private function handler(): CallHeartbeatCommandHandler
    {
        return new CallHeartbeatCommandHandler($this->access, $this->rooms, $this->broadcaster, $this->settings, $this->clock);
    }

    public function testHeartbeatKeepsPlaceWithoutBroadcast(): void
    {
        $this->rooms->rooms[$this->projectId()][$this->id($this->alice)] = new Participant($this->id($this->alice), 's1', $this->clock->now()->modify('-30 seconds'));

        self::assertTrue(($this->handler())(new CallHeartbeatCommand($this->projectId(), 's1', $this->id($this->alice))));

        self::assertEquals($this->clock->now(), $this->rooms->rooms[$this->projectId()][$this->id($this->alice)]->lastSeenAt);
        self::assertSame([], $this->publisher->published);
    }

    public function testReplacedSessionIsToldItIsOut(): void
    {
        $this->rooms->rooms[$this->projectId()][$this->id($this->alice)] = new Participant($this->id($this->alice), 's2', $this->clock->now());

        self::assertFalse(($this->handler())(new CallHeartbeatCommand($this->projectId(), 's1', $this->id($this->alice))));
    }

    public function testStaleParticipantIsDroppedAndProjectNotified(): void
    {
        $this->rooms->rooms[$this->projectId()][$this->id($this->alice)] = new Participant($this->id($this->alice), 's1', $this->clock->now());
        $this->rooms->rooms[$this->projectId()][$this->id($this->bob)] = new Participant($this->id($this->bob), 's2', $this->clock->now()->modify('-90 seconds'));

        ($this->handler())(new CallHeartbeatCommand($this->projectId(), 's1', $this->id($this->alice)));

        self::assertSame([$this->id($this->alice)], array_keys($this->rooms->rooms[$this->projectId()]));
        $changed = $this->publisher->ofType('room.changed');
        self::assertCount(1, $changed);
        self::assertSame([$this->id($this->alice)], array_column($changed[0]['data']['participants'], 'userId'));
    }
}
