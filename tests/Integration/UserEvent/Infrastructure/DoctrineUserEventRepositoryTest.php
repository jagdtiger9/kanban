<?php

declare(strict_types=1);

namespace App\Tests\Integration\UserEvent\Infrastructure;

use App\Tests\Integration\DatabaseTestCase;
use App\UserEvent\Domain\Entity\UserEvent;
use App\UserEvent\Domain\Enum\UserEventType;
use App\UserEvent\Domain\Repository\UserEventRepository;
use App\UserEvent\Domain\ValueObject\UserEventCursor;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class DoctrineUserEventRepositoryTest extends DatabaseTestCase
{
    private UserEventRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = $this->repo(UserEventRepository::class);
    }

    public function testSaveAndFindRoundtripKeepsAllFields(): void
    {
        $id = Uuid::uuid4();
        $userId = Uuid::uuid4();
        $aggregateRootId = Uuid::uuid4();
        $projectId = Uuid::uuid4();
        $cardId = Uuid::uuid4();
        $actorId = Uuid::uuid4();
        $occurredOn = new \DateTimeImmutable('2026-11-20 10:00:00.123456+00:00');
        $event = UserEvent::record(
            $id,
            $userId,
            UserEventType::CommentCreated,
            $aggregateRootId,
            $occurredOn,
            ['actorName' => 'Bob', 'projectName' => 'Kanban', 'cardTitle' => 'Fix it'],
            $projectId,
            $cardId,
            $actorId,
        );

        $this->repo->save($event);
        $this->em()->clear();

        $found = $this->repo->findById($id);

        self::assertInstanceOf(UserEvent::class, $found);
        self::assertSame(UserEventType::CommentCreated, $found->type());
        self::assertTrue($userId->equals($found->userId()));
        self::assertTrue($aggregateRootId->equals($found->aggregateRootId()));
        self::assertTrue($projectId->equals($found->projectId()));
        self::assertTrue($cardId->equals($found->cardId()));
        self::assertTrue($actorId->equals($found->actorId()));
        self::assertSame(
            ['actorName' => 'Bob', 'projectName' => 'Kanban', 'cardTitle' => 'Fix it'],
            $found->payload(),
        );
        self::assertSame($occurredOn->format('U.u'), $found->occurredOn()->format('U.u'));
        self::assertNull($found->seenAt());
    }

    public function testRecordWithoutOptionalReferencesRoundtrips(): void
    {
        $id = Uuid::uuid4();
        $event = UserEvent::record(
            $id,
            Uuid::uuid4(),
            UserEventType::ProjectRestored,
            Uuid::uuid4(),
            new \DateTimeImmutable('2026-11-20 10:00:00.000001+00:00'),
            ['actorName' => 'Alice', 'projectName' => 'Kanban'],
        );
        $this->repo->save($event);
        $this->em()->clear();

        $found = $this->repo->findById($id);

        self::assertInstanceOf(UserEvent::class, $found);
        self::assertNull($found->projectId());
        self::assertNull($found->cardId());
        self::assertNull($found->actorId());
    }

    public function testExistsByNaturalKeyMatchesExactEvent(): void
    {
        $userId = Uuid::uuid4();
        $aggregateRootId = Uuid::uuid4();
        $occurredOn = new \DateTimeImmutable('2026-11-20 10:00:00.654321+00:00');
        $this->repo->save(UserEvent::record(
            Uuid::uuid4(),
            $userId,
            UserEventType::CardAssigned,
            $aggregateRootId,
            $occurredOn,
            ['actorName' => 'Alice', 'projectName' => 'Kanban', 'cardTitle' => 'Card'],
        ));

        self::assertTrue($this->repo->existsByNaturalKey(
            $userId,
            UserEventType::CardAssigned,
            $aggregateRootId,
            $occurredOn,
        ));
        self::assertFalse($this->repo->existsByNaturalKey(
            Uuid::uuid4(),
            UserEventType::CardAssigned,
            $aggregateRootId,
            $occurredOn,
        ));
        self::assertFalse($this->repo->existsByNaturalKey(
            $userId,
            UserEventType::CardUnassigned,
            $aggregateRootId,
            $occurredOn,
        ));
        self::assertFalse($this->repo->existsByNaturalKey(
            $userId,
            UserEventType::CardAssigned,
            Uuid::uuid4(),
            $occurredOn,
        ));
        self::assertFalse($this->repo->existsByNaturalKey(
            $userId,
            UserEventType::CardAssigned,
            $aggregateRootId,
            $occurredOn->modify('+1 microsecond'),
        ));
    }

    public function testExistsByNaturalKeyIsPerRecipient(): void
    {
        $first = Uuid::uuid4();
        $second = Uuid::uuid4();
        $aggregateRootId = Uuid::uuid4();
        $occurredOn = new \DateTimeImmutable('2026-11-20 10:00:00.111111+00:00');
        $payload = ['actorName' => 'Alice', 'projectName' => 'Kanban', 'cardTitle' => 'Card'];

        $this->repo->save(UserEvent::record(Uuid::uuid4(), $first, UserEventType::CardAssigned, $aggregateRootId, $occurredOn, $payload));

        self::assertTrue($this->repo->existsByNaturalKey($first, UserEventType::CardAssigned, $aggregateRootId, $occurredOn));
        self::assertFalse($this->repo->existsByNaturalKey($second, UserEventType::CardAssigned, $aggregateRootId, $occurredOn));

        $this->repo->save(UserEvent::record(Uuid::uuid4(), $second, UserEventType::CardAssigned, $aggregateRootId, $occurredOn, $payload));

        self::assertTrue($this->repo->existsByNaturalKey($second, UserEventType::CardAssigned, $aggregateRootId, $occurredOn));
    }

    public function testFindByIdReturnsNullForUnknownId(): void
    {
        self::assertNull($this->repo->findById(Uuid::uuid4()));
    }

    public function testFindPageForUserIsNewestFirstAndResumesFromTheCursor(): void
    {
        $userId = Uuid::uuid4();
        $first = $this->saveRow($userId, '2026-11-20 10:00:00.000001+00:00');
        $second = $this->saveRow($userId, '2026-11-20 10:00:00.000002+00:00');
        $third = $this->saveRow($userId, '2026-11-20 10:00:00.000003+00:00');
        $this->saveRow(Uuid::uuid4(), '2026-11-20 10:00:00.000004+00:00');
        $this->em()->clear();

        $page = $this->repo->findPageForUser($userId, null, 2);

        self::assertCount(3, $page);
        self::assertTrue($third->equals($page[0]->id()));
        self::assertTrue($second->equals($page[1]->id()));
        self::assertTrue($first->equals($page[2]->id()));

        $cursor = new UserEventCursor($page[1]->occurredOn(), $page[1]->id());
        $rest = $this->repo->findPageForUser($userId, $cursor, 2);

        self::assertCount(1, $rest);
        self::assertTrue($first->equals($rest[0]->id()));
    }

    public function testFindPageForUserOrdersEqualTimestampsByCursorToo(): void
    {
        $userId = Uuid::uuid4();
        $this->saveRow($userId, '2026-11-20 10:00:00.000001+00:00');
        $this->saveRow($userId, '2026-11-20 10:00:00.000001+00:00');
        $this->em()->clear();

        $both = $this->repo->findPageForUser($userId, null, 1);
        self::assertCount(2, $both);

        $cursor = new UserEventCursor($both[0]->occurredOn(), $both[0]->id());
        $rest = $this->repo->findPageForUser($userId, $cursor, 1);

        self::assertCount(1, $rest);
        self::assertTrue($both[1]->id()->equals($rest[0]->id()));
    }

    public function testUnreadCountIsPerUser(): void
    {
        $userId = Uuid::uuid4();
        $other = Uuid::uuid4();
        $this->saveRow($userId, '2026-11-20 10:00:00.000001+00:00');
        $this->saveRow($userId, '2026-11-20 10:00:00.000002+00:00');
        $this->saveRow($other, '2026-11-20 10:00:00.000003+00:00');

        self::assertSame(2, $this->repo->unreadCount($userId));
        self::assertSame(1, $this->repo->unreadCount($other));
    }

    public function testMarkAllSeenStampsOnlyTheUsersUnseenRows(): void
    {
        $userId = Uuid::uuid4();
        $other = Uuid::uuid4();
        $first = $this->saveRow($userId, '2026-11-20 10:00:00.000001+00:00');
        $second = $this->saveRow($userId, '2026-11-20 10:00:00.000002+00:00');
        $otherRow = $this->saveRow($other, '2026-11-20 10:00:00.000003+00:00');

        $this->repo->markAllSeen($userId, new \DateTimeImmutable('2026-11-20 12:00:00.500000+00:00'));
        $this->em()->clear();

        $stamp = (new \DateTimeImmutable('2026-11-20 12:00:00.500000+00:00'))->format('U.u');
        self::assertSame($stamp, $this->seenStamp($first));
        self::assertSame($stamp, $this->seenStamp($second));
        self::assertNull($this->repo->findById($otherRow)->seenAt());
        self::assertSame(0, $this->repo->unreadCount($userId));

        $this->repo->markAllSeen($userId, new \DateTimeImmutable('2026-11-20 13:00:00+00:00'));
        $this->em()->clear();

        self::assertSame($stamp, $this->seenStamp($first));
    }

    private function saveRow(UuidInterface $userId, string $occurredOn): UuidInterface
    {
        $id = Uuid::uuid4();
        $this->repo->save(UserEvent::record(
            $id,
            $userId,
            UserEventType::CardAssigned,
            Uuid::uuid4(),
            new \DateTimeImmutable($occurredOn),
            ['actorName' => 'Alice', 'projectName' => 'Kanban', 'cardTitle' => 'Card'],
        ));

        return $id;
    }

    private function seenStamp(UuidInterface $id): ?string
    {
        return $this->repo->findById($id)->seenAt()?->format('U.u');
    }
}
