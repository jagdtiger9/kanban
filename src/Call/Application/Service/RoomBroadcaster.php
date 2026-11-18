<?php

declare(strict_types=1);

namespace App\Call\Application\Service;

use App\Call\Domain\Model\Room;
use App\Project\Domain\Repository\ProjectMemberRepository;
use App\User\Domain\Repository\UserRepository;
use Ramsey\Uuid\Uuid;

/**
 * Tells every project member (and everyone in the room) who is in the call
 * now. The one "room.changed" message drives both the "in call" badges and
 * the participants list of an open call.
 */
final class RoomBroadcaster
{
    public function __construct(
        private ProjectMemberRepository $memberRepository,
        private UserRepository $userRepository,
        private CallPublisher $publisher,
    ) {
    }

    public function roomChanged(Room $room): void
    {
        $recipients = $room->userIds();
        foreach ($this->memberRepository->findByProject($room->projectId()) as $member) {
            $recipients[] = (string) $member->userId();
        }

        $this->publisher->publish(array_values(array_unique($recipients)), [
            'type' => 'room.changed',
            'projectId' => (string) $room->projectId(),
            'participants' => $this->participants($room),
        ]);
    }

    /**
     * @return list<array{userId: string, sessionId: string, displayName: string}>
     */
    public function participants(Room $room): array
    {
        $result = [];
        foreach ($room->participants() as $participant) {
            $result[] = [
                'userId' => $participant->userId,
                'sessionId' => $participant->sessionId,
                'displayName' => $this->displayName($participant->userId),
            ];
        }

        return $result;
    }

    public function displayName(string $userId): string
    {
        return $this->userRepository->findById(Uuid::fromString($userId))?->displayName() ?? 'Unknown';
    }
}
