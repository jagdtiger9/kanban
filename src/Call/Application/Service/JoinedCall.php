<?php

declare(strict_types=1);

namespace App\Call\Application\Service;

final readonly class JoinedCall
{
    /**
     * @param list<array{userId: string, sessionId: string, displayName: string}> $participants
     * @param list<array{urls: list<string>, username?: string, credential?: string}> $iceServers
     */
    public function __construct(
        public array $participants,
        public array $iceServers,
    ) {
    }
}
