<?php

declare(strict_types=1);

namespace App\Board\Application\Service;

use Ramsey\Uuid\UuidInterface;

interface RealtimeTokenIssuerInterface
{
    public function issue(UuidInterface $userId, string $channel): RealtimeToken;
}
