<?php

declare(strict_types=1);

namespace App\Tests\Unit\Call\Application;

use App\Call\Application\Message\Command\SendCallSignalCommand;
use App\Call\Application\MessageHandler\SendCallSignalCommandHandler;
use App\Call\Domain\Exception\NotInRoomException;
use App\Call\Domain\Model\Participant;

final class SendCallSignalCommandHandlerTest extends CallFixture
{
    private function handler(): SendCallSignalCommandHandler
    {
        return new SendCallSignalCommandHandler($this->access, $this->rooms, $this->publisher);
    }

    public function testSignalIsRelayedToTheOtherParticipantOnly(): void
    {
        $this->rooms->rooms[$this->projectId()][$this->id($this->alice)] = new Participant($this->id($this->alice), 's1', $this->clock->now());
        $this->rooms->rooms[$this->projectId()][$this->id($this->bob)] = new Participant($this->id($this->bob), 's2', $this->clock->now());

        ($this->handler())(new SendCallSignalCommand($this->projectId(), 's1', $this->id($this->bob), 's2', 'offer', ['sdp' => 'v=0'], $this->id($this->alice)));

        self::assertCount(1, $this->publisher->published);
        $published = $this->publisher->published[0];
        self::assertSame([$this->id($this->bob)], $published['userIds']);
        self::assertSame('signal', $published['data']['type']);
        self::assertSame('offer', $published['data']['kind']);
        self::assertSame(['sdp' => 'v=0'], $published['data']['data']);
        self::assertSame('s2', $published['data']['toSessionId']);
    }

    public function testRecipientOutsideTheRoomIsRefused(): void
    {
        $this->rooms->rooms[$this->projectId()][$this->id($this->alice)] = new Participant($this->id($this->alice), 's1', $this->clock->now());

        $this->expectException(NotInRoomException::class);

        ($this->handler())(new SendCallSignalCommand($this->projectId(), 's1', $this->id($this->bob), 's2', 'offer', [], $this->id($this->alice)));
    }
}
