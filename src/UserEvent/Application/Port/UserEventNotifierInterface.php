<?php

declare(strict_types=1);

namespace App\UserEvent\Application\Port;

use Ramsey\Uuid\UuidInterface;

interface UserEventNotifierInterface
{
    /**
     * Best-effort publication to the recipient's personal realtime channel.
     *
     * @param array<string, mixed> $item
     */
    public function notify(UuidInterface $userId, array $item): void;
}
