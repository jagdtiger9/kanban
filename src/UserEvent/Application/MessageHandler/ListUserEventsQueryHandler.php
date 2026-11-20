<?php

declare(strict_types=1);

namespace App\UserEvent\Application\MessageHandler;

use App\UserEvent\Application\Dto\UserEventPage;
use App\UserEvent\Application\Message\Query\ListUserEventsQuery;
use App\UserEvent\Application\Service\UserEventCursorCodec;
use App\UserEvent\Application\Service\UserEventView;
use App\UserEvent\Domain\Repository\UserEventRepository;
use App\UserEvent\Domain\ValueObject\UserEventCursor;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ListUserEventsQueryHandler
{
    public function __construct(
        private UserEventRepository $events,
        private UserEventCursorCodec $codec,
    ) {
    }

    public function __invoke(ListUserEventsQuery $query): UserEventPage
    {
        $cursor = $query->cursor === null || $query->cursor === ''
            ? null
            : $this->codec->decode($query->cursor);
        $rows = $this->events->findPageForUser(Uuid::fromString($query->actorId), $cursor, $query->limit);

        $nextCursor = null;
        if (count($rows) > $query->limit) {
            $rows = array_slice($rows, 0, $query->limit);
            $last = $rows[$query->limit - 1];
            $nextCursor = $this->codec->encode(new UserEventCursor($last->occurredOn(), $last->id()));
        }

        return new UserEventPage(array_map(UserEventView::item(...), $rows), $nextCursor);
    }
}
