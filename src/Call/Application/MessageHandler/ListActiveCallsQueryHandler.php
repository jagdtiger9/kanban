<?php

declare(strict_types=1);

namespace App\Call\Application\MessageHandler;

use App\Call\Application\Message\Query\ListActiveCallsQuery;
use App\Call\Application\Service\CallSettings;
use App\Call\Application\Service\RoomBroadcaster;
use App\Call\Domain\Repository\RoomRepository;
use App\Project\Domain\Repository\ProjectRepository;
use Psr\Clock\ClockInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Non-empty rooms of the actor's projects, for the "in call" badges on page load.
 * Stale participants are filtered out but not written back.
 */
#[AsMessageHandler]
final class ListActiveCallsQueryHandler
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private RoomRepository $rooms,
        private RoomBroadcaster $broadcaster,
        private CallSettings $settings,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return list<array{projectId: string, participants: list<array{userId: string, sessionId: string, displayName: string}>}>
     */
    public function __invoke(ListActiveCallsQuery $query): array
    {
        $now = $this->clock->now();
        $result = [];
        foreach ($this->projectRepository->findByMember(Uuid::fromString($query->actorId)) as $project) {
            $room = $this->rooms->get($project->id());
            $room->expire($now, $this->settings->participantTtl);
            if ($room->isEmpty()) {
                continue;
            }
            $result[] = [
                'projectId' => (string) $project->id(),
                'participants' => $this->broadcaster->participants($room),
            ];
        }

        return $result;
    }
}
