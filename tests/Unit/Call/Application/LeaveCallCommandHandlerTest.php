<?php

declare(strict_types=1);

namespace App\Tests\Unit\Call\Application;

use App\Call\Application\Message\Command\LeaveCallCommand;
use App\Call\Application\MessageHandler\LeaveCallCommandHandler;
use App\Call\Domain\Model\Participant;

final class LeaveCallCommandHandlerTest extends CallFixture
{
    private function handler(): LeaveCallCommandHandler
    {
        return new LeaveCallCommandHandler($this->access, $this->rooms, $this->broadcaster, $this->settings, $this->clock);
    }

    public function testLeaveEmptiesRoomAndNotifies(): void
    {
        $this->rooms->rooms[$this->projectId()][$this->id($this->alice)] = new Participant($this->id($this->alice), 's1', $this->clock->now());

        ($this->handler())(new LeaveCallCommand($this->projectId(), 's1', $this->id($this->alice)));

        self::assertSame([], $this->rooms->rooms[$this->projectId()]);
        self::assertSame([], $this->publisher->ofType('room.changed')[0]['data']['participants']);
    }

    public function testLeaveFromReplacedTabChangesNothing(): void
    {
        $this->rooms->rooms[$this->projectId()][$this->id($this->alice)] = new Participant($this->id($this->alice), 's2', $this->clock->now());

        ($this->handler())(new LeaveCallCommand($this->projectId(), 's1', $this->id($this->alice)));

        self::assertCount(1, $this->rooms->rooms[$this->projectId()]);
        self::assertSame([], $this->publisher->published);
    }
}
