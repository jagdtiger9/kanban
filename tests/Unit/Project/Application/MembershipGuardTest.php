<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Application;

use App\Project\Application\Service\MembershipGuard;
use App\Project\Domain\Entity\ProjectMember;
use App\Project\Domain\Enum\MemberRole;
use App\Project\Domain\Exception\ProjectForbiddenException;
use App\Project\Domain\Repository\ProjectMemberRepository;
use App\User\Domain\Entity\User;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class MembershipGuardTest extends TestCase
{
    private function guardWith(?ProjectMember $member, ?User $actorUser = null): MembershipGuard
    {
        $memberRepository = $this->createMock(ProjectMemberRepository::class);
        $memberRepository->method('findByProjectAndUser')->willReturn($member);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findById')->willReturn($actorUser);

        return new MembershipGuard($memberRepository, $userRepository);
    }

    private function globalAdmin(): User
    {
        $user = User::create(Uuid::uuid4(), 'admin@example.com', 'hash', 'Admin');
        $user->promoteToGlobalAdmin();

        return $user;
    }

    public function testEffectiveRoleReturnsAdminForGlobalAdmin(): void
    {
        self::assertSame(
            MemberRole::Admin,
            $this->guardWith(null, $this->globalAdmin())->effectiveRole(Uuid::uuid4(), Uuid::uuid4()),
        );
    }

    public function testEffectiveRoleReturnsAdminForGlobalAdminWhoIsPlainMember(): void
    {
        $member = ProjectMember::create(Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4(), MemberRole::Member);

        self::assertSame(
            MemberRole::Admin,
            $this->guardWith($member, $this->globalAdmin())->effectiveRole($member->projectId(), $member->userId()),
        );
    }

    public function testEffectiveRoleReturnsMemberRoleForMember(): void
    {
        $member = ProjectMember::create(Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4(), MemberRole::Member);

        self::assertSame(
            MemberRole::Member,
            $this->guardWith($member)->effectiveRole($member->projectId(), $member->userId()),
        );
    }

    public function testEffectiveRoleReturnsAdminRoleForProjectAdmin(): void
    {
        $member = ProjectMember::create(Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4(), MemberRole::Admin);

        self::assertSame(
            MemberRole::Admin,
            $this->guardWith($member)->effectiveRole($member->projectId(), $member->userId()),
        );
    }

    public function testEffectiveRoleReturnsNullForNonMember(): void
    {
        self::assertNull($this->guardWith(null)->effectiveRole(Uuid::uuid4(), Uuid::uuid4()));
    }

    public function testRequireMemberReturnsMember(): void
    {
        $member = ProjectMember::create(Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4(), MemberRole::Admin);

        $result = $this->guardWith($member)->requireMember($member->projectId(), $member->userId());

        self::assertSame($member, $result);
    }

    public function testRequireMemberThrowsWhenNotMember(): void
    {
        $this->expectException(ProjectForbiddenException::class);

        $this->guardWith(null)->requireMember(Uuid::uuid4(), Uuid::uuid4());
    }

    public function testRequireMemberReturnsNullForGlobalAdmin(): void
    {
        self::assertNull(
            $this->guardWith(null, $this->globalAdmin())->requireMember(Uuid::uuid4(), Uuid::uuid4()),
        );
    }

    public function testRequireAdminReturnsAdmin(): void
    {
        $member = ProjectMember::create(Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4(), MemberRole::Admin);

        $result = $this->guardWith($member)->requireAdmin($member->projectId(), $member->userId());

        self::assertSame($member, $result);
    }

    public function testRequireAdminThrowsWhenNotMember(): void
    {
        $this->expectException(ProjectForbiddenException::class);

        $this->guardWith(null)->requireAdmin(Uuid::uuid4(), Uuid::uuid4());
    }

    public function testRequireAdminThrowsWhenPlainMember(): void
    {
        $member = ProjectMember::create(Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4(), MemberRole::Member);

        $this->expectException(ProjectForbiddenException::class);

        $this->guardWith($member)->requireAdmin($member->projectId(), $member->userId());
    }

    public function testRequireAdminReturnsNullForGlobalAdmin(): void
    {
        self::assertNull(
            $this->guardWith(null, $this->globalAdmin())->requireAdmin(Uuid::uuid4(), Uuid::uuid4()),
        );
    }

    public function testRequireAdminAllowsGlobalAdminWhoIsPlainMember(): void
    {
        $member = ProjectMember::create(Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4(), MemberRole::Member);

        self::assertNull(
            $this->guardWith($member, $this->globalAdmin())->requireAdmin($member->projectId(), $member->userId()),
        );
    }
}
