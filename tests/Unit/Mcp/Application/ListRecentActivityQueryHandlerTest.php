<?php

declare(strict_types=1);

namespace App\Tests\Unit\Mcp\Application;

use App\Mcp\Application\Message\Query\ListRecentActivityQuery;
use App\Mcp\Application\MessageHandler\ListRecentActivityQueryHandler;
use App\Mcp\Domain\ReadModel\RecentActivity;
use App\Mcp\Domain\Repository\RecentActivityReader;
use App\Shared\Domain\Exception\AccessForbiddenException;
use App\User\Domain\Entity\User;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Clock\MockClock;

final class ListRecentActivityQueryHandlerTest extends TestCase
{
    /** @var list<array{\DateTimeImmutable, ?UuidInterface, int}> */
    private array $calls = [];

    private function reader(): RecentActivityReader
    {
        $test = $this;

        return new class ($test) implements RecentActivityReader {
            public function __construct(private ListRecentActivityQueryHandlerTest $test)
            {
            }

            public function findSince(\DateTimeImmutable $since, ?UuidInterface $memberId, int $limit): array
            {
                $this->test->record($since, $memberId, $limit);

                return [new RecentActivity(Uuid::uuid4(), 'P', Uuid::uuid4(), 'C', 'Actor', 'card_created', [], $since)];
            }
        };
    }

    public function record(\DateTimeImmutable $since, ?UuidInterface $memberId, int $limit): void
    {
        $this->calls[] = [$since, $memberId, $limit];
    }

    private function handler(?User $actor): ListRecentActivityQueryHandler
    {
        $users = $this->createMock(UserRepository::class);
        $users->method('findById')->willReturn($actor);

        return new ListRecentActivityQueryHandler($this->reader(), $users, new MockClock('2026-11-17 10:00:00+00:00'));
    }

    public function testMemberGetsOwnProjectsForTheLast24HoursByDefault(): void
    {
        $actor = User::create(Uuid::uuid4(), 'member@example.com', 'hash', 'Member');

        $result = $this->handler($actor)(new ListRecentActivityQuery((string)$actor->id()));

        self::assertCount(1, $result);
        [$since, $memberId, $limit] = $this->calls[0];
        self::assertSame('2026-11-16T10:00:00+00:00', $since->format(\DateTimeInterface::ATOM));
        self::assertNotNull($memberId);
        self::assertTrue($actor->id()->equals($memberId));
        self::assertSame(200, $limit);
    }

    public function testGlobalAdminSeesAllProjects(): void
    {
        $actor = User::create(Uuid::uuid4(), 'admin@example.com', 'hash', 'Admin');
        $actor->promoteToGlobalAdmin();

        $this->handler($actor)(new ListRecentActivityQuery((string)$actor->id(), '2026-11-10T00:00:00+00:00'));

        [$since, $memberId] = $this->calls[0];
        self::assertSame('2026-11-10T00:00:00+00:00', $since->format(\DateTimeInterface::ATOM));
        self::assertNull($memberId);
    }

    public function testRejectsInvalidSince(): void
    {
        $actor = User::create(Uuid::uuid4(), 'member@example.com', 'hash', 'Member');

        $this->expectException(\InvalidArgumentException::class);

        $this->handler($actor)(new ListRecentActivityQuery((string)$actor->id(), 'garbage'));
    }

    public function testRejectsUnknownActor(): void
    {
        $this->expectException(AccessForbiddenException::class);

        $this->handler(null)(new ListRecentActivityQuery((string)Uuid::uuid4()));
    }
}
