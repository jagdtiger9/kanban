<?php

declare(strict_types=1);

namespace App\UserEvent\Application\MessageHandler;

use App\UserEvent\Application\Message\Command\MarkUserEventsSeenCommand;
use App\UserEvent\Domain\Repository\UserEventRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class MarkUserEventsSeenCommandHandler
{
    public function __construct(
        private UserEventRepository $events,
    ) {
    }

    public function __invoke(MarkUserEventsSeenCommand $command): void
    {
        $this->events->markAllSeen(Uuid::fromString($command->actorId), new \DateTimeImmutable());
    }
}
