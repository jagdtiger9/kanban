<?php

declare(strict_types=1);

namespace App\Board\Application\Service;

interface RealtimeNotifierInterface
{
    public function notify(string $channel, string $event, ?string $actorId = null): void;
}
