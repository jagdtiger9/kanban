<?php

declare(strict_types=1);

namespace App\Tests\Unit\UserEvent\Application\MessageHandler;

use App\UserEvent\Application\Message\Query\ListUserEventsQuery;
use App\UserEvent\Application\MessageHandler\ListUserEventsQueryHandler;
use App\UserEvent\Application\Service\UserEventCursorCodec;
use App\UserEvent\Domain\Entity\UserEvent;
use App\UserEvent\Domain\Enum\UserEventType;
use App\UserEvent\Domain\Repository\UserEventRepository;
use App\UserEvent\Domain\ValueObject\UserEventCursor;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class ListUserEventsQueryHandlerTest extends TestCase
{
    private UserEventRepository&MockObject $events;
    private UserEventCursorCodec $codec;

    protected function setUp(): void
    {
        $this->events = $this->createMock(UserEventRepository::class);
        $this->codec = new UserEventCursorCodec();
    }

    public function testReturnsAnEmptyPageWithoutCursor(): void
    {
        $userId = Uuid::uuid4();
        $this->events->method('findPageForUser')->willReturn([]);

        $page = $this->handler()(new ListUserEventsQuery(actorId: (string)$userId));

        self::assertSame([], $page->items);
        self::assertNull($page->nextCursor);
    }

    public function testReturnsViewItemsAndNoCursorWhenThePageIsNotFull(): void
    {
        $userId = Uuid::uuid4();
        $row = $this->event('2026-11-20 10:00:00.000001+00:00');
        $this->expectPage($userId, null, 2, [$row]);

        $page = $this->handler()(new ListUserEventsQuery(limit: 2, actorId: (string)$userId));

        self::assertCount(1, $page->items);
        self::assertSame((string)$row->id(), $page->items[0]['id']);
        self::assertSame('card.assigned', $page->items[0]['type']);
        self::assertNull($page->nextCursor);
    }

    public function testDetectsMoreRowsAndCursorsAfterTheLastReturnedItem(): void
    {
        $userId = Uuid::uuid4();
        $rows = [
            $this->event('2026-11-20 10:00:00.000003+00:00'),
            $this->event('2026-11-20 10:00:00.000002+00:00'),
            $this->event('2026-11-20 10:00:00.000001+00:00'),
        ];
        $this->expectPage($userId, null, 2, $rows);

        $page = $this->handler()(new ListUserEventsQuery(limit: 2, actorId: (string)$userId));

        self::assertCount(2, $page->items);
        self::assertNotNull($page->nextCursor);
        $cursor = $this->codec->decode($page->nextCursor);
        self::assertSame($rows[1]->occurredOn()->format('U.u'), $cursor->occurredOn()->format('U.u'));
        self::assertTrue($rows[1]->id()->equals($cursor->id()));
    }

    public function testResumesFromAnEncodedCursor(): void
    {
        $userId = Uuid::uuid4();
        $position = new UserEventCursor(new \DateTimeImmutable('2026-11-19 09:00:00.500000+00:00'), Uuid::uuid4());
        $this->expectPage($userId, $position, 20, []);

        $page = $this->handler()(
            new ListUserEventsQuery(
                cursor: $this->codec->encode($position),
                actorId: (string)$userId,
            )
        );

        self::assertSame([], $page->items);
        self::assertNull($page->nextCursor);
    }

    private function handler(): ListUserEventsQueryHandler
    {
        return new ListUserEventsQueryHandler($this->events, $this->codec);
    }

    /**
     * @param list<UserEvent> $rows
     */
    private function expectPage(UuidInterface $userId, ?UserEventCursor $cursor, int $limit, array $rows): void
    {
        $this->events->expects($this->once())
            ->method('findPageForUser')
            ->with(
                $this->callback(static fn(UuidInterface $id): bool => $id->equals($userId)),
                $this->callback(static function (?UserEventCursor $actual) use ($cursor): bool {
                    if ($cursor === null || $actual === null) {
                        return $cursor === $actual;
                    }

                    return $actual->occurredOn()->format('U.u') === $cursor->occurredOn()->format('U.u')
                        && $actual->id()->equals($cursor->id());
                }),
                $limit,
            )
            ->willReturn($rows);
    }

    private function event(string $occurredOn): UserEvent
    {
        return UserEvent::record(
            Uuid::uuid4(),
            Uuid::uuid4(),
            UserEventType::CardAssigned,
            Uuid::uuid4(),
            new \DateTimeImmutable($occurredOn),
            ['actorName' => 'Alice', 'projectName' => 'Kanban', 'cardTitle' => 'Card'],
        );
    }
}
