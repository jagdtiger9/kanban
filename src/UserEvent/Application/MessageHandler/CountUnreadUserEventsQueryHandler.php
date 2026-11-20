<?php

declare(strict_types=1);

namespace App\UserEvent\Application\MessageHandler;

use App\UserEvent\Application\Message\Query\CountUnreadUserEventsQuery;
use App\UserEvent\Domain\Repository\UserEventRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CountUnreadUserEventsQueryHandler
{
    public function __construct(
        private UserEventRepository $events,
    ) {
    }

    public function __invoke(CountUnreadUserEventsQuery $query): int
    {
        return $this->events->unreadCount(Uuid::fromString($query->actorId));
    }
}
