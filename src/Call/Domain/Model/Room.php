<?php

declare(strict_types=1);

namespace App\Call\Domain\Model;

use App\Call\Domain\Exception\NotInRoomException;
use App\Call\Domain\Exception\RoomFullException;
use Ramsey\Uuid\UuidInterface;

/**
 * Who is in the call of one project right now. Short-lived state, not an
 * entity: the room exists while someone is in it.
 *
 * Mutations are tracked per participant so the repository writes only the
 * changed places — concurrent heartbeats of different users never overwrite
 * each other.
 */
final class Room
{
    /** @var array<string, Participant> keyed by user id */
    private array $participants = [];

    /** @var array<string, Participant> */
    private array $changed = [];

    /** @var array<string, true> */
    private array $removed = [];

    /**
     * @param list<Participant> $participants
     */
    public function __construct(
        private readonly UuidInterface $projectId,
        array $participants = [],
    ) {
        foreach ($participants as $participant) {
            $this->participants[$participant->userId] = $participant;
        }
    }

    public function projectId(): UuidInterface
    {
        return $this->projectId;
    }

    /**
     * @return list<Participant>
     */
    public function participants(): array
    {
        return array_values($this->participants);
    }

    /**
     * @return list<string>
     */
    public function userIds(): array
    {
        return array_keys($this->participants);
    }

    public function has(string $userId): bool
    {
        return isset($this->participants[$userId]);
    }

    public function isEmpty(): bool
    {
        return $this->participants === [];
    }

    /**
     * @return string|null the session id this join replaced (the same user in another tab)
     */
    public function join(string $userId, string $sessionId, \DateTimeImmutable $now, int $maxParticipants): ?string
    {
        $current = $this->participants[$userId] ?? null;
        if ($current === null && count($this->participants) >= $maxParticipants) {
            throw new RoomFullException('Room is full');
        }

        $this->put(new Participant($userId, $sessionId, $now));

        return $current !== null && $current->sessionId !== $sessionId ? $current->sessionId : null;
    }

    /**
     * @return bool false when that session was not in the room
     */
    public function leave(string $userId, string $sessionId): bool
    {
        $current = $this->participants[$userId] ?? null;
        if ($current === null || $current->sessionId !== $sessionId) {
            return false;
        }

        $this->drop($userId);

        return true;
    }

    /**
     * @return bool false when that session is no longer in the room
     */
    public function heartbeat(string $userId, string $sessionId, \DateTimeImmutable $now): bool
    {
        $current = $this->participants[$userId] ?? null;
        if ($current === null || $current->sessionId !== $sessionId) {
            return false;
        }

        $this->put($current->seenAt($now));

        return true;
    }

    /**
     * Removes participants that stopped sending heartbeats.
     *
     * @return list<string> user ids removed
     */
    public function expire(\DateTimeImmutable $now, int $ttlSeconds): array
    {
        $removed = [];
        foreach ($this->participants as $userId => $participant) {
            if ($participant->isStale($now, $ttlSeconds)) {
                $this->drop($userId);
                $removed[] = (string) $userId;
            }
        }

        return $removed;
    }

    public function requireParticipant(string $userId): void
    {
        if (!$this->has($userId)) {
            throw new NotInRoomException('You are not in this call');
        }
    }

    /**
     * @return array<string, Participant>
     */
    public function changedParticipants(): array
    {
        return $this->changed;
    }

    /**
     * @return list<string>
     */
    public function removedUserIds(): array
    {
        return array_keys($this->removed);
    }

    private function put(Participant $participant): void
    {
        $this->participants[$participant->userId] = $participant;
        $this->changed[$participant->userId] = $participant;
        unset($this->removed[$participant->userId]);
    }

    private function drop(string $userId): void
    {
        unset($this->participants[$userId], $this->changed[$userId]);
        $this->removed[$userId] = true;
    }
}
