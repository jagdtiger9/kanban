<?php

declare(strict_types=1);

namespace App\Call\Application\MessageHandler;

use App\Call\Application\Message\Command\CallHeartbeatCommand;
use App\Call\Application\Service\CallAccess;
use App\Call\Application\Service\CallSettings;
use App\Call\Application\Service\RoomBroadcaster;
use App\Call\Domain\Repository\RoomRepository;
use Psr\Clock\ClockInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Keeps the actor's place and drops participants who stopped sending
 * heartbeats (closed laptop, lost network).
 */
#[AsMessageHandler]
final class CallHeartbeatCommandHandler
{
    public function __construct(
        private CallAccess $access,
        private RoomRepository $rooms,
        private RoomBroadcaster $broadcaster,
        private CallSettings $settings,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return bool false when the actor's session is no longer in the room
     */
    public function __invoke(CallHeartbeatCommand $command): bool
    {
        $projectId = Uuid::fromString($command->projectId);
        $this->access->requireMember($projectId, Uuid::fromString($command->actorId));

        $now = $this->clock->now();
        $room = $this->rooms->get($projectId);
        $expired = $room->expire($now, $this->settings->participantTtl);
        $inRoom = $room->heartbeat($command->actorId, $command->sessionId, $now);

        $this->rooms->save($room);
        if ($expired !== []) {
            $this->broadcaster->roomChanged($room);
        }

        return $inRoom;
    }
}
