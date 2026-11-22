<?php

declare(strict_types=1);

namespace App\Call\Application\Service;

/**
 * Result of the calls check: the server-side checks plus the ICE servers (with
 * the same short-lived TURN credentials a call gets), so the browser can run its
 * own STUN/TURN test.
 */
final readonly class CallsCheckReport
{
    /**
     * @param list<CallCheck>                                                     $checks
     * @param list<array{urls: list<string>, username?: string, credential?: string}> $iceServers
     */
    public function __construct(
        public array $checks,
        public array $iceServers,
    ) {
    }
}
