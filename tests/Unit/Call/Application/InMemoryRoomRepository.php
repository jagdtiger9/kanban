<?php

declare(strict_types=1);

namespace App\Tests\Unit\Call\Application;

use App\Call\Domain\Model\Participant;
use App\Call\Domain\Model\Room;
use App\Call\Domain\Repository\RoomRepository;
use Ramsey\Uuid\UuidInterface;

final class InMemoryRoomRepository implements RoomRepository
{
    /** @var array<string, array<string, Participant>> */
    public array $rooms = [];

    public function get(UuidInterface $projectId): Room
    {
        return new Room($projectId, array_values($this->rooms[(string) $projectId] ?? []));
    }

    public function save(Room $room): void
    {
        $key = (string) $room->projectId();
        foreach ($room->removedUserIds() as $userId) {
            unset($this->rooms[$key][$userId]);
        }
        foreach ($room->changedParticipants() as $userId => $participant) {
            $this->rooms[$key][$userId] = $participant;
        }
    }
}
