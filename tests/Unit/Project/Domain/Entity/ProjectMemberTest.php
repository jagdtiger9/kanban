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
}
