<?php

declare(strict_types=1);

namespace App\Call\Domain\Repository;

use App\Call\Domain\Model\Room;
use Ramsey\Uuid\UuidInterface;

interface RoomRepository
{
    /**
     * Never null: a project without a call has an empty room.
     */
    public function get(UuidInterface $projectId): Room;

    /**
     * Writes only the participants changed or removed since the room was loaded.
     */
    public function save(Room $room): void;
}
