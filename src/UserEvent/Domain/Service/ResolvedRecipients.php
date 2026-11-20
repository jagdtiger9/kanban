<?php

declare(strict_types=1);

namespace App\UserEvent\Domain\Service;

use App\UserEvent\Domain\Enum\UserEventType;
use Ramsey\Uuid\UuidInterface;

final readonly class ResolvedRecipients
{
    /** @param list<UuidInterface> $userIds */
    public function __construct(
        private UserEventType $type,
        private array $userIds,
    ) {
    }

    public function type(): UserEventType
    {
        return $this->type;
    }

    /** @return list<UuidInterface> */
    public function userIds(): array
    {
        return $this->userIds;
    }
}
