<?php

declare(strict_types=1);

namespace App\Call\Application\MessageHandler;

use App\Call\Application\Message\Command\SendCallSignalCommand;
use App\Call\Application\Service\CallAccess;
use App\Call\Application\Service\CallPublisher;
use App\Call\Domain\Repository\RoomRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Relays WebRTC signaling (SDP, ICE candidates, mic/camera state) between two
 * participants of the same room. The content is opaque to the server.
 */
#[AsMessageHandler]
final class SendCallSignalCommandHandler
{
    public function __construct(
        private CallAccess $access,
        private RoomRepository $rooms,
        private CallPublisher $publisher,
    ) {
    }

    public function __invoke(SendCallSignalCommand $command): void
    {
        $projectId = Uuid::fromString($command->projectId);
        $this->access->requireMember($projectId, Uuid::fromString($command->actorId));

        $room = $this->rooms->get($projectId);
        $room->requireParticipant($command->actorId);
        $room->requireParticipant($command->toUserId);

        $this->publisher->publish([$command->toUserId], [
            'type' => 'signal',
            'projectId' => $command->projectId,
            'fromUserId' => $command->actorId,
            'fromSessionId' => $command->sessionId,
            'toSessionId' => $command->toSessionId,
            'kind' => $command->kind,
            'data' => $command->data,
        ]);
    }
}
