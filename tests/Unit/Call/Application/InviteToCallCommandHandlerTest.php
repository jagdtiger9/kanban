<?php

declare(strict_types=1);

namespace App\Tests\Unit\Call\Application;

use App\Call\Application\Message\Command\InviteToCallCommand;
use App\Call\Application\MessageHandler\InviteToCallCommandHandler;
use App\Call\Domain\Exception\NotInRoomException;
use App\Call\Domain\Model\Participant;
use App\Project\Domain\Exception\ProjectForbiddenException;

final class InviteToCallCommandHandlerTest extends CallFixture
{
    private function handler(): InviteToCallCommandHandler
    {
        return new InviteToCallCommandHandler($this->access, $this->rooms, $this->broadcaster, $this->publisher);
    }

    private function aliceInRoom(): void
    {
        $this->rooms->rooms[$this->projectId()][$this->id($this->alice)] = new Participant($this->id($this->alice), 's1', $this->clock->now());
    }

    public function testInviteRingsOneMember(): void
    {
        $this->aliceInRoom();

        ($this->handler())(new InviteToCallCommand($this->projectId(), $this->id($this->bob), $this->id($this->alice)));

        self::assertSame([[
            'userIds' => [$this->id($this->bob)],
            'data' => [
                'type' => 'invite',
                'projectId' => $this->projectId(),
                'projectName' => 'Kanban',
                'fromUserId' => $this->id($this->alice),
                'fromName' => 'Alice',
            ],
        ]], $this->publisher->published);
    }

    public function testOnlySomeoneInTheRoomCanInvite(): void
    {
        $this->expectException(NotInRoomException::class);

        ($this->handler())(new InviteToCallCommand($this->projectId(), $this->id($this->bob), $this->id($this->alice)));
    }

    public function testOutsiderCannotBeInvited(): void
    {
        $this->aliceInRoom();

        $this->expectException(ProjectForbiddenException::class);

        ($this->handler())(new InviteToCallCommand($this->projectId(), $this->id($this->eve), $this->id($this->alice)));
    }

    public function testSelfInviteIsRejected(): void
    {
        $this->aliceInRoom();

        $this->expectException(\InvalidArgumentException::class);

        ($this->handler())(new InviteToCallCommand($this->projectId(), $this->id($this->alice), $this->id($this->alice)));
    }
}
