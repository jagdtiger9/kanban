<?php

declare(strict_types=1);

namespace App\Tests\Unit\Call\Application;

use App\Call\Application\Message\Command\JoinCallCommand;
use App\Call\Application\MessageHandler\JoinCallCommandHandler;
use App\Call\Application\Service\CallSettings;
use App\Call\Application\Service\IceServerProvider;
use App\Call\Domain\Exception\RoomFullException;
use App\Call\Domain\Model\Participant;
use App\Project\Domain\Exception\ProjectForbiddenException;
use Ramsey\Uuid\UuidInterface;

final class JoinCallCommandHandlerTest extends CallFixture
{
    private function handler(?CallSettings $settings = null): JoinCallCommandHandler
    {
        $ice = new class () implements IceServerProvider {
            public function forUser(UuidInterface $userId): array
            {
                return [['urls' => ['stun:turn.test:3478']]];
            }
        };

        return new JoinCallCommandHandler($this->access, $this->rooms, $this->broadcaster, $ice, $settings ?? $this->settings, $this->clock);
    }

    public function testMemberJoinsAndProjectIsNotified(): void
    {
        $joined = ($this->handler())(new JoinCallCommand($this->projectId(), 'session-alice', $this->id($this->alice)));

        self::assertSame([['userId' => $this->id($this->alice), 'sessionId' => 'session-alice', 'displayName' => 'Alice']], $joined->participants);
        self::assertSame([['urls' => ['stun:turn.test:3478']]], $joined->iceServers);

        $changed = $this->publisher->ofType('room.changed');
        self::assertCount(1, $changed);
        self::assertEqualsCanonicalizing([$this->id($this->alice), $this->id($this->bob)], $changed[0]['userIds']);
    }

    public function testOutsiderCannotJoin(): void
    {
        $this->expectException(ProjectForbiddenException::class);

        ($this->handler())(new JoinCallCommand($this->projectId(), 'session-eve', $this->id($this->eve)));
    }

    public function testFullRoomIsRefused(): void
    {
        $this->rooms->rooms[$this->projectId()][$this->id($this->alice)] = new Participant($this->id($this->alice), 's1', $this->clock->now());

        $this->expectException(RoomFullException::class);

        ($this->handler(new CallSettings(1, 60)))(new JoinCallCommand($this->projectId(), 'session-bob', $this->id($this->bob)));
    }

    public function testStaleParticipantDoesNotTakeAPlace(): void
    {
        $this->rooms->rooms[$this->projectId()][$this->id($this->alice)] = new Participant($this->id($this->alice), 's1', $this->clock->now()->modify('-2 minutes'));

        $joined = ($this->handler(new CallSettings(1, 60)))(new JoinCallCommand($this->projectId(), 'session-bob', $this->id($this->bob)));

        self::assertSame([$this->id($this->bob)], array_column($joined->participants, 'userId'));
    }
}
