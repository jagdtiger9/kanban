<?php

declare(strict_types=1);

namespace App\User\Domain\Event;

use Aljerom\SymfonyBoilerplate\DomainEvents\DomainEventInterface;
use Aljerom\SymfonyBoilerplate\DomainEvents\DomainEventTrait;

final class UserAvatarChanged implements DomainEventInterface
{
    use DomainEventTrait;

    public function __construct(
        public string $userId,
        public ?string $previousStorageKey,
        public string $storageKey,
    ) {
        $this->initEvent($userId, $userId);
    }
}
