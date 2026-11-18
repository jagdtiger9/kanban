<?php

declare(strict_types=1);

namespace App\Call\Application\MessageHandler;

use App\Call\Application\Message\Command\JoinCallCommand;
use App\Call\Application\Service\CallAccess;
use App\Call\Application\Service\CallSettings;
use App\Call\Application\Service\IceServerProvider;
use App\Call\Application\Service\JoinedCall;
use App\Call\Application\Service\RoomBroadcaster;
use App\Call\Domain\Repository\RoomRepository;
use Psr\Clock\ClockInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class JoinCallCommandHandler
{
    public function __construct(
        private CallAccess $access,
        private RoomRepository $rooms,
        private RoomBroadcaster $broadcaster,
        private IceServerProvider $iceServers,
        private CallSettings $settings,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(JoinCallCommand $command): JoinedCall
    {
        $projectId = Uuid::fromString($command->projectId);
        $actorId = Uuid::fromString($command->actorId);
        $this->access->requireMember($projectId, $actorId);

        $now = $this->clock->now();
        $room = $this->rooms->get($projectId);
        $room->expire($now, $this->settings->participantTtl);
        $room->join($command->actorId, $command->sessionId, $now, $this->settings->maxParticipants);
        $this->rooms->save($room);

        // Another tab of the same user sees its session replaced in this message and leaves.
        $this->broadcaster->roomChanged($room);

        return new JoinedCall($this->broadcaster->participants($room), $this->iceServers->forUser($actorId));
    }
}
