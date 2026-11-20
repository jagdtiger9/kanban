<?php

declare(strict_types=1);

namespace App\Tests\Unit\UserEvent\Application\MessageHandler;

use App\UserEvent\Application\Message\Command\MarkUserEventsSeenCommand;
use App\UserEvent\Application\MessageHandler\MarkUserEventsSeenCommandHandler;
use App\UserEvent\Domain\Repository\UserEventRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class MarkUserEventsSeenCommandHandlerTest extends TestCase
{
    public function testMarksEveryUnseenRowOfTheActor(): void
    {
        $userId = Uuid::uuid4();
        $events = $this->createMock(UserEventRepository::class);
        $events->expects($this->once())
            ->method('markAllSeen')
            ->with(
                $this->callback(static fn(UuidInterface $id): bool => $id->equals($userId)),
                $this->isInstanceOf(\DateTimeImmutable::class),
            );

        new MarkUserEventsSeenCommandHandler($events)(new MarkUserEventsSeenCommand((string)$userId));
    }
}
