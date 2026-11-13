<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Application;

use App\Project\Application\Message\Query\ListProjectsQuery;
use App\Project\Application\MessageHandler\ListProjectsQueryHandler;
use App\Project\Domain\Entity\Project;
use App\Project\Domain\Entity\ProjectMember;
use App\Project\Domain\Enum\MemberRole;
use App\Project\Domain\Repository\ProjectMemberRepository;
use App\Project\Domain\Repository\ProjectRepository;
use App\User\Domain\Entity\User;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class ListProjectsQueryHandlerTest extends TestCase
{
    public function testReturnsProjectsWithActorRoles(): void
    {
        $first = Project::create(Uuid::uuid4(), 'First');
        $second = Project::create(Uuid::uuid4(), 'Second');
        $actorId = Uuid::uuid4();

        $firstMembership = ProjectMember::create(Uuid::uuid4(), $first->id(), $actorId, MemberRole::Admin);
        $secondMembership = ProjectMember::create(Uuid::uuid4(), $second->id(), $actorId, MemberRole::Member);

        $projectRepository = $this->createMock(ProjectRepository::class);
        $projectRepository->method('findByMember')->willReturn([$first, $second]);

        $memberRepository = $this->createMock(ProjectMemberRepository::class);
        $memberRepository->method('findByProjectAndUser')->willReturnCallback(
            static fn (UuidInterface $projectId): ?ProjectMember => match (true) {
                $projectId->equals($first->id()) => $firstMembership,
                default => $secondMembership,
            },
        );

        $userRepository = $this->createMock(UserRepository::class);

        $result = (new ListProjectsQueryHandler($projectRepository, $memberRepository, $userRepository))
            ->__invoke(new ListProjectsQuery((string) $actorId));

        self::assertCount(2, $result);
        self::assertSame($first, $result[0]['project']);
        self::assertSame(MemberRole::Admin, $result[0]['role']);
        self::assertSame($second, $result[1]['project']);
        self::assertSame(MemberRole::Member, $result[1]['role']);
    }

    public function testGlobalAdminGetsAllProjectsWithAdminRole(): void
    {
        $first = Project::create(Uuid::uuid4(), 'First');
        $second = Project::create(Uuid::uuid4(), 'Second');
        $admin = User::create(Uuid::uuid4(), 'admin@example.com', 'hash', 'Admin');
        $admin->promoteToGlobalAdmin();

        $projectRepository = $this->createMock(ProjectRepository::class);
        $projectRepository->method('findAll')->willReturn([$first, $second]);
        $projectRepository->expects($this->never())->method('findByMember');

        $memberRepository = $this->createMock(ProjectMemberRepository::class);
        $memberRepository->expects($this->never())->method('findByProjectAndUser');

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('findById')->willReturn($admin);

        $result = (new ListProjectsQueryHandler($projectRepository, $memberRepository, $userRepository))
            ->__invoke(new ListProjectsQuery((string) $admin->id()));

        self::assertCount(2, $result);
        self::assertSame($first, $result[0]['project']);
        self::assertSame(MemberRole::Admin, $result[0]['role']);
        self::assertSame($second, $result[1]['project']);
        self::assertSame(MemberRole::Admin, $result[1]['role']);
    }
}
