<?php

namespace App\Common\Application\MessageHandler;

use App\Common\Application\Message\Query\PingQuery;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class PingQueryHandler
{
    public function __invoke(PingQuery $query): array
    {
        return [
            'status' => 'ok',
            'service' => 'canban-api',
            'time' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];
    }
}
