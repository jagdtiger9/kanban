<?php

declare(strict_types=1);

namespace App\Call\Application\MessageHandler;

use App\Call\Application\Message\Command\InviteToCallCommand;
use App\Call\Application\Service\CallAccess;
use App\Call\Application\Service\CallPublisher;
use App\Call\Application\Service\RoomBroadcaster;
use App\Call\Domain\Repository\RoomRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Rings one project member. Only someone already in the room can invite.
 */
#[AsMessageHandler]
final class InviteToCallCommandHandler
{
    public function __construct(
        private CallAccess $access,
        private RoomRepository $rooms,
        private RoomBroadcaster $broadcaster,
        private CallPublisher $publisher,
    ) {
    }

    public function __invoke(InviteToCallCommand $command): void
    {
        $projectId = Uuid::fromString($command->projectId);
        $project = $this->access->requireMember($projectId, Uuid::fromString($command->actorId));
        $this->rooms->get($projectId)->requireParticipant($command->actorId);

        if ($command->userId === $command->actorId) {
            throw new \InvalidArgumentException('You cannot invite yourself');
        }
        $this->access->requireMember($projectId, Uuid::fromString($command->userId));

        $this->publisher->publish([$command->userId], [
            'type' => 'invite',
            'projectId' => $command->projectId,
            'projectName' => $project->name(),
            'fromUserId' => $command->actorId,
            'fromName' => $this->broadcaster->displayName($command->actorId),
        ]);
    }
}
