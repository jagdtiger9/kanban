<?php

declare(strict_types=1);

namespace App\Call\Application\MessageHandler;

use App\Call\Application\Message\Command\DeclineCallCommand;
use App\Call\Application\Service\CallAccess;
use App\Call\Application\Service\CallPublisher;
use App\Call\Application\Service\RoomBroadcaster;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Tells the inviter that the invitation was declined.
 */
#[AsMessageHandler]
final class DeclineCallCommandHandler
{
    public function __construct(
        private CallAccess $access,
        private RoomBroadcaster $broadcaster,
        private CallPublisher $publisher,
    ) {
    }

    public function __invoke(DeclineCallCommand $command): void
    {
        $this->access->requireMember(Uuid::fromString($command->projectId), Uuid::fromString($command->actorId));

        $this->publisher->publish([$command->fromUserId], [
            'type' => 'invite.declined',
            'projectId' => $command->projectId,
            'userId' => $command->actorId,
            'displayName' => $this->broadcaster->displayName($command->actorId),
        ]);
    }
}
