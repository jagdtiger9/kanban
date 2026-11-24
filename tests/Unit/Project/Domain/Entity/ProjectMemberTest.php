<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Domain\Entity;

use App\Project\Domain\Entity\ProjectMember;
use App\Project\Domain\Enum\MemberRole;
use App\Project\Domain\Event\ProjectMemberRoleChanged;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class ProjectMemberTest extends TestCase
{
    public function testCreate(): void
    {
        $projectId = Uuid::uuid4();
        $userId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $projectId, $userId, MemberRole::Admin);

        self::assertTrue($member->projectId()->equals($projectId));
        self::assertTrue($member->userId()->equals($userId));
        self::assertSame(MemberRole::Admin, $member->role());
    }

    public function testChangeRoleRecordsEvent(): void
    {
        $member = ProjectMember::create(Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4(), MemberRole::Member);
        $member->releaseEvents();
        $actorId = Uuid::uuid4();

        $member->changeRole(MemberRole::Admin, $actorId);

        $events = $member->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ProjectMemberRoleChanged::class, $events[0]);
        self::assertSame(MemberRole::Admin, $events[0]->newRole);
        self::assertSame($actorId->toString(), $events[0]->actorId);
        self::assertSame(MemberRole::Admin, $member->role());
    }

    public function testChangeRoleToSameRoleRecordsNoEvent(): void
    {
        $member = ProjectMember::create(Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4(), MemberRole::Member);
        $member->releaseEvents();

        $member->changeRole(MemberRole::Member, Uuid::uuid4());

        self::assertSame([], $member->releaseEvents());
    }

    public function testIsAdmin(): void
    {
        $admin = ProjectMember::create(Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4(), MemberRole::Admin);
        $member = ProjectMember::create(Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4(), MemberRole::Member);

        self::assertTrue($admin->isAdmin());
        self::assertFalse($member->isAdmin());
    }

    public function testNewMemberIsNotFavouriteByDefault(): void
    {
        $member = ProjectMember::create(Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4(), MemberRole::Member);

        self::assertFalse($member->isFavourite());
    }

    public function testCreateAsFavourite(): void
    {
        $member = ProjectMember::create(Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4(), MemberRole::Admin, isFavourite: true);

        self::assertTrue($member->isFavourite());
    }

    public function testFavouriteAndUnfavourite(): void
    {
        $member = ProjectMember::create(Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4(), MemberRole::Member);

        $member->favourite();
        self::assertTrue($member->isFavourite());

        $member->unfavourite();
        self::assertFalse($member->isFavourite());
    }

    public function testFavouriteRecordsNoDomainEvent(): void
    {
        $member = ProjectMember::create(Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4(), MemberRole::Member);
        $member->releaseEvents();

        $member->favourite();
        $member->unfavourite();

        self::assertSame([], $member->releaseEvents());
    }

    public function testNewMemberHasNoLastSeenAt(): void
    {
        $member = ProjectMember::create(Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4(), MemberRole::Member);

        self::assertNull($member->lastSeenAt());
    }

    public function testTouchPresenceSetsLastSeenAt(): void
    {
        $member = ProjectMember::create(Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4(), MemberRole::Member);
        $at = new \DateTimeImmutable('2026-11-24 10:00:00');

        $member->touchPresence($at);

        self::assertSame($at, $member->lastSeenAt());
    }

    public function testTouchPresenceIgnoresEarlierTimestamp(): void
    {
        $member = ProjectMember::create(Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4(), MemberRole::Member);
        $newer = new \DateTimeImmutable('2026-11-24 10:00:00');
        $earlier = new \DateTimeImmutable('2026-11-24 09:59:00');

        $member->touchPresence($newer);
        $member->touchPresence($earlier);

        self::assertSame($newer, $member->lastSeenAt());
    }

    public function testTouchPresenceRecordsNoDomainEvent(): void
    {
        $member = ProjectMember::create(Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4(), MemberRole::Member);
        $member->releaseEvents();

        $member->touchPresence(new \DateTimeImmutable());

        self::assertSame([], $member->releaseEvents());
    }
}
