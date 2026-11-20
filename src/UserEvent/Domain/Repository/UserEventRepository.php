<?php

declare(strict_types=1);

namespace App\UserEvent\Domain\Repository;

use App\UserEvent\Domain\Entity\UserEvent;
use App\UserEvent\Domain\Enum\UserEventType;
use App\UserEvent\Domain\ValueObject\UserEventCursor;
use Ramsey\Uuid\UuidInterface;

interface UserEventRepository
{
    public function save(UserEvent $event): void;

    public function findById(UuidInterface $id): ?UserEvent;

    public function existsByNaturalKey(
        UuidInterface $userId,
        UserEventType $type,
        UuidInterface $aggregateRootId,
        \DateTimeImmutable $occurredOn,
    ): bool;

    /**
     * Newest-first page, at most $limit + 1 rows so the caller can detect more.
     *
     * @return list<UserEvent>
     */
    public function findPageForUser(UuidInterface $userId, ?UserEventCursor $cursor, int $limit): array;

    public function unreadCount(UuidInterface $userId): int;

    /**
     * Stamps every unseen row of the user; rows already seen keep their stamp.
     */
    public function markAllSeen(UuidInterface $userId, \DateTimeImmutable $seenAt): void;
}
