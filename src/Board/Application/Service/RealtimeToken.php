<?php

declare(strict_types=1);

namespace App\Board\Application\Service;

final readonly class RealtimeToken
{
    public function __construct(
        public string $url,
        public string $channel,
        public string $token,
        public string $subscriptionToken,
        public int $expiresAt,
    ) {
    }
}
