<?php

declare(strict_types=1);

namespace App\UserEvent\Application\Dto;

final readonly class UserEventPage
{
    /**
     * @param list<array<string, mixed>> $items
     */
    public function __construct(
        public array $items,
        public ?string $nextCursor,
    ) {
    }
}
