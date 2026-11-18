<?php

declare(strict_types=1);

namespace App\Call\Application\MessageHandler;

use App\Call\Application\Message\Query\GetCallTokenQuery;
use App\Call\Application\Service\CallToken;
use App\Call\Application\Service\CallTokenIssuer;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetCallTokenQueryHandler
{
    public function __construct(
        private CallTokenIssuer $issuer,
    ) {
    }

    public function __invoke(GetCallTokenQuery $query): CallToken
    {
        return $this->issuer->issue(Uuid::fromString($query->actorId));
    }
}
