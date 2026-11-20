<?php

declare(strict_types=1);

namespace App\UserEvent\Domain\Service;

use Aljerom\SymfonyBoilerplate\DomainEvents\DomainEventInterface;
use App\UserEvent\Domain\Enum\UserEventType;
use Ramsey\Uuid\UuidInterface;

interface RecipientRule
{
    public function supports(DomainEventInterface $event): bool;

    public function type(): UserEventType;

    /** @return list<UuidInterface> */
    public function recipients(DomainEventInterface $event): array;
}
