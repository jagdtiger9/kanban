<?php

declare(strict_types=1);

namespace App\Call\Application\Service;

/**
 * Delivers call messages to users' personal channels. Unlike board
 * invalidation, these messages carry content (SDP, ICE candidates).
 */
interface CallPublisher
{
    /**
     * @param list<string> $userIds
     * @param array<string, mixed> $data
     */
    public function publish(array $userIds, array $data): void;
}
