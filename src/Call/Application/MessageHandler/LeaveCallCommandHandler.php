<?php

declare(strict_types=1);

namespace App\Call\Application\MessageHandler;

use App\Call\Application\Message\Command\LeaveCallCommand;
use App\Call\Application\Service\CallAccess;
use App\Call\Application\Service\CallSettings;
use App\Call\Application\Service\RoomBroadcaster;
use App\Call\Domain\Repository\RoomRepository;
use Psr\Clock\ClockInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class LeaveCallCommandHandler
{
    public function __construct(
        private CallAccess $access,
        private RoomRepository $rooms,
        private RoomBroadcaster $broadcaster,
        private CallSettings $settings,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(LeaveCallCommand $command): void
    {
        $projectId = Uuid::fromString($command->projectId);
        $this->access->requireMember($projectId, Uuid::fromString($command->actorId));

        $room = $this->rooms->get($projectId);
        $left = $room->leave($command->actorId, $command->sessionId);
        $expired = $room->expire($this->clock->now(), $this->settings->participantTtl);
        if (!$left && $expired === []) {
            return;
        }

        $this->rooms->save($room);
        $this->broadcaster->roomChanged($room);
    }
}
