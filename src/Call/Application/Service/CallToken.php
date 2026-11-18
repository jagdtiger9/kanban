<?php

declare(strict_types=1);

namespace App\Call\Application\Service;

final readonly class CallToken
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
