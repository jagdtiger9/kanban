<?php

declare(strict_types=1);

namespace App\Tests\Unit\UserEvent\Application\MessageHandler;

use App\UserEvent\Application\Message\Query\CountUnreadUserEventsQuery;
use App\UserEvent\Application\MessageHandler\CountUnreadUserEventsQueryHandler;
use App\UserEvent\Domain\Repository\UserEventRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class CountUnreadUserEventsQueryHandlerTest extends TestCase
{
    public function testReturnsTheRepositoryCountForTheActor(): void
    {
        $userId = Uuid::uuid4();
        $events = $this->createMock(UserEventRepository::class);
        $events->expects($this->once())
            ->method('unreadCount')
            ->with($this->callback(static fn(UuidInterface $id): bool => $id->equals($userId)))
            ->willReturn(4);

        $result = new CountUnreadUserEventsQueryHandler($events)(
            new CountUnreadUserEventsQuery((string)$userId),
        );

        self::assertSame(4, $result);
    }
}
