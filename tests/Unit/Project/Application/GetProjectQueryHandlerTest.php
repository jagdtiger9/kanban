<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Application;

use App\Board\Domain\Entity\Board;
use App\Board\Domain\Repository\BoardRepository;
use App\Project\Application\Message\Query\GetProjectQuery;
use App\Project\Application\MessageHandler\GetProjectQueryHandler;
use App\Project\Application\Service\MembershipGuard;
use App\Project\Domain\Entity\Project;
use App\Project\Domain\Entity\ProjectMember;
use App\Project\Domain\Enum\MemberRole;
use App\Project\Domain\Exception\ProjectForbiddenException;
use App\Project\Domain\Exception\ProjectNotFoundException;
use App\Project\Domain\Repository\ProjectMemberRepository;
use App\Project\Domain\Repository\ProjectRepository;
use App\User\Domain\Entity\User;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class GetProjectQueryHandlerTest extends TestCase
{
    private ProjectRepository $projectRepository;
    private BoardRepository $boardRepository;
    private ProjectMemberRepository $memberRepository;
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        $this->projectRepository = $this->createMock(ProjectRepository::class);
        $this->boardRepository = $this->createMock(BoardRepository::class);
        $this->memberRepository = $this->createMock(ProjectMemberRepository::class);
        $this->userRepository = $this->createMock(UserRepository::class);
    }

    private function handler(?Project $project, ?ProjectMember $actorMembership): GetProjectQueryHandler
    {
        $this->projectRepository->method('findById')->willReturn($project);
        $this->memberRepository->method('findByProjectAndUser')->willReturn($actorMembership);

        return new GetProjectQueryHandler(
            $this->projectRepository,
            $this->boardRepository,
            $this->memberRepository,
            $this->userRepository,
            new MembershipGuard($this->memberRepository, $this->userRepository),
        );
    }

    public function testReturnsProjectBoardAndMembers(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $this->boardRepository->method('findByProject')->willReturn($board);

        $alice = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');
        $bob = User::create(Uuid::uuid4(), 'bob@example.com', 'hash', 'Bob');
        $memberAlice = ProjectMember::create(Uuid::uuid4(), $project->id(), $alice->id(), MemberRole::Admin);
        $memberBob = ProjectMember::create(Uuid::uuid4(), $project->id(), $bob->id(), MemberRole::Member);
        $this->memberRepository->method('findByProject')->willReturn([$memberAlice, $memberBob]);
        $this->userRepository->method('findById')->willReturnCallback(
            static fn (UuidInterface $userId): ?User => match (true) {
                $userId->equals($alice->id()) => $alice,
                default => $bob,
            },
        );

        $result = $this->handler($project, $memberAlice)
            ->__invoke(new GetProjectQuery((string) $project->id(), (string) $alice->id()));

        self::assertSame($project, $result['project']);
        self::assertTrue($result['boardId']->equals($board->id()));
        self::assertSame(MemberRole::Admin, $result['role']);
        self::assertCount(2, $result['members']);
        self::assertSame('alice@example.com', $result['members'][0]['email']);
        self::assertSame(MemberRole::Admin, $result['members'][0]['role']);
        self::assertSame('Bob', $result['members'][1]['displayName']);
        self::assertSame(MemberRole::Member, $result['members'][1]['role']);
    }

    public function testGlobalAdminGetsAdminRoleWithoutMembership(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $admin = User::create(Uuid::uuid4(), 'admin@example.com', 'hash', 'Admin');
        $admin->promoteToGlobalAdmin();
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $this->boardRepository->method('findByProject')->willReturn($board);
        $this->memberRepository->method('findByProject')->willReturn([]);
        $this->userRepository->method('findById')->willReturn($admin);

        $result = $this->handler($project, null)
            ->__invoke(new GetProjectQuery((string) $project->id(), (string) $admin->id()));

        self::assertSame(MemberRole::Admin, $result['role']);
        self::assertCount(0, $result['members']);
    }

    public function testThrowsWhenProjectMissing(): void
    {
        $this->expectException(ProjectNotFoundException::class);

        $this->handler(null, null)
            ->__invoke(new GetProjectQuery((string) Uuid::uuid4(), (string) Uuid::uuid4()));
    }

    public function testThrowsWhenActorIsNotMember(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');

        $this->expectException(ProjectForbiddenException::class);

        $this->handler($project, null)
            ->__invoke(new GetProjectQuery((string) $project->id(), (string) Uuid::uuid4()));
    }
}

