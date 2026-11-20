<?php

declare(strict_types=1);

namespace App\UserEvent\Domain\ValueObject;

use Ramsey\Uuid\UuidInterface;

/**
 * Position in the feed list: the (occurred_on, id) pair of the last row shown.
 */
final readonly class UserEventCursor
{
    public function __construct(
        private \DateTimeImmutable $occurredOn,
        private UuidInterface $id,
    ) {
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function id(): UuidInterface
    {
        return $this->id;
    }
}
