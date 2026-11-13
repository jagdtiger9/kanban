<?php

declare(strict_types=1);

namespace App\Board\Domain\Event;

use Ramsey\Uuid\UuidInterface;

final readonly class CardFieldChanged
{
    public function __construct(
        public UuidInterface $cardId,
        public string $field,
        public ?string $oldValue,
        public ?string $newValue,
        public ?UuidInterface $actorId,
    ) {
    }
}
