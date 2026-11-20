<?php

declare(strict_types=1);

namespace App\UserEvent\Application\Port;

use App\UserEvent\Application\Dto\CardEventContext;
use Ramsey\Uuid\UuidInterface;

interface UserEventContextProvider
{
    public function card(UuidInterface $cardId): ?CardEventContext;

    public function projectName(UuidInterface $projectId): ?string;

    public function userName(UuidInterface $userId): ?string;
}
