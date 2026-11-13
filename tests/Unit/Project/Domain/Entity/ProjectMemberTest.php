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

    public function testChangeRoleEmitsEvent(): void
    {
        $member = ProjectMember::create(Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4(), MemberRole::Member);
        $actorId = Uuid::uuid4();

        $event = $member->changeRole(MemberRole::Admin, $actorId);

        self::assertInstanceOf(ProjectMemberRoleChanged::class, $event);
        self::assertSame(MemberRole::Admin, $event->newRole);
        self::assertTrue($event->actorId->equals($actorId));
        self::assertSame(MemberRole::Admin, $member->role());
    }

    public function testChangeRoleToSameRoleReturnsNull(): void
    {
        $member = ProjectMember::create(Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4(), MemberRole::Member);

        self::assertNull($member->changeRole(MemberRole::Member, Uuid::uuid4()));
    }

    public function testIsAdmin(): void
    {
        $admin = ProjectMember::create(Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4(), MemberRole::Admin);
        $member = ProjectMember::create(Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4(), MemberRole::Member);

        self::assertTrue($admin->isAdmin());
        self::assertFalse($member->isAdmin());
    }
}
