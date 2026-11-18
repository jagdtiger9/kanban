<?php

declare(strict_types=1);

namespace App\Call\Infrastructure\Redis;

use App\Call\Application\Service\CallSettings;
use App\Call\Domain\Model\Participant;
use App\Call\Domain\Model\Room;
use App\Call\Domain\Repository\RoomRepository;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/**
 * One hash per room: field = user id, value = {"sessionId", "lastSeenAt"}.
 * Only changed fields are written, so concurrent heartbeats do not clash.
 * The key expires with the participant TTL: an abandoned room disappears.
 */
#[AsAlias(RoomRepository::class)]
final class RedisRoomRepository implements RoomRepository
{
    public function __construct(
        private \Redis $redis,
        private CallSettings $settings,
    ) {
    }

    public function get(UuidInterface $projectId): Room
    {
        /** @var array<string, string>|false $fields */
        $fields = $this->redis->hGetAll($this->key($projectId));
        $participants = [];
        foreach ($fields ?: [] as $userId => $json) {
            $participant = $this->decode((string) $userId, $json);
            if ($participant !== null) {
                $participants[] = $participant;
            }
        }

        return new Room($projectId, $participants);
    }

    public function save(Room $room): void
    {
        $key = $this->key($room->projectId());
        $changed = $room->changedParticipants();
        $removed = $room->removedUserIds();
        if ($changed === [] && $removed === []) {
            return;
        }

        $this->redis->multi();
        if ($removed !== []) {
            $this->redis->hDel($key, ...$removed);
        }
        foreach ($changed as $participant) {
            $this->redis->hSet($key, $participant->userId, json_encode([
                'sessionId' => $participant->sessionId,
                'lastSeenAt' => $participant->lastSeenAt->format(\DATE_ATOM),
            ], \JSON_THROW_ON_ERROR));
        }
        if ($changed !== []) {
            $this->redis->expire($key, $this->settings->participantTtl);
        }
        $this->redis->exec();
    }

    private function key(UuidInterface $projectId): string
    {
        return 'call:room:' . $projectId;
    }

    private function decode(string $userId, string $json): ?Participant
    {
        try {
            $data = json_decode($json, true, 4, \JSON_THROW_ON_ERROR);
            if (!is_array($data) || !is_string($data['sessionId'] ?? null) || !is_string($data['lastSeenAt'] ?? null)) {
                return null;
            }

            return new Participant($userId, $data['sessionId'], new \DateTimeImmutable($data['lastSeenAt']));
        } catch (\JsonException|\Exception) {
            return null;
        }
    }
}
