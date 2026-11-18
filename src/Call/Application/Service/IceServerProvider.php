<?php

declare(strict_types=1);

namespace App\Call\Application\Service;

use Ramsey\Uuid\UuidInterface;

interface IceServerProvider
{
    /**
     * RTCIceServer entries for the browser; TURN entries carry short-lived credentials.
     *
     * @return list<array{urls: list<string>, username?: string, credential?: string}>
     */
    public function forUser(UuidInterface $userId): array;
}
