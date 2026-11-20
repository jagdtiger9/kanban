<?php

declare(strict_types=1);

namespace App\UserEvent\Application\MessageHandler;

use App\Board\Application\Service\RealtimeToken;
use App\Board\Application\Service\RealtimeTokenIssuerInterface;
use App\UserEvent\Application\Message\Query\GetUserRealtimeTokenQuery;
use App\UserEvent\Application\Service\UserChannel;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetUserRealtimeTokenQueryHandler
{
    public function __construct(
        private RealtimeTokenIssuerInterface $issuer,
    ) {
    }

    public function __invoke(GetUserRealtimeTokenQuery $query): RealtimeToken
    {
        return $this->issuer->issue(Uuid::fromString($query->actorId), UserChannel::forUser($query->actorId));
    }
}
