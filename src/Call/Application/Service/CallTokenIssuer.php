<?php

declare(strict_types=1);

namespace App\Call\Application\Service;

use Ramsey\Uuid\UuidInterface;

interface CallTokenIssuer
{
    /**
     * Connection and subscription tokens for the user's personal call channel.
     */
    public function issue(UuidInterface $userId): CallToken;
}
