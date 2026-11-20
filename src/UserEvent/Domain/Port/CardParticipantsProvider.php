<?php

declare(strict_types=1);

namespace App\UserEvent\Domain\Port;

use Ramsey\Uuid\UuidInterface;

interface CardParticipantsProvider
{
    /** @return list<UuidInterface> */
    public function assigneeIds(UuidInterface $cardId): array;

    public function creatorId(UuidInterface $cardId): ?UuidInterface;
}
