<?php

declare(strict_types=1);

namespace App\Call\Domain\Model;

/**
 * One browser tab in a call room. A user holds at most one place in a room;
 * the session id tells that user's tabs apart.
 */
final readonly class Participant
{
    public function __construct(
        public string $userId,
        public string $sessionId,
        public \DateTimeImmutable $lastSeenAt,
    ) {
    }

    public function seenAt(\DateTimeImmutable $now): self
    {
        return new self($this->userId, $this->sessionId, $now);
    }

    public function isStale(\DateTimeImmutable $now, int $ttlSeconds): bool
    {
        return $now->getTimestamp() - $this->lastSeenAt->getTimestamp() >= $ttlSeconds;
    }
}
