<?php

declare(strict_types=1);

namespace App\Call\Application\Service;

/**
 * Server-side checks of what calls depend on: Centrifugo (reachable, uptime, the
 * "call" namespace) and the STUN/TURN settings and ports.
 */
interface CallDiagnostics
{
    /**
     * @return list<CallCheck>
     */
    public function run(): array;
}
