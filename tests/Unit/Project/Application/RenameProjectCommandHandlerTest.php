<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Application;

use App\Project\Application\Message\Command\RenameProjectCommand;
use App\Project\Application\MessageHandler\RenameProjectCommandHandler;
use App\Project\Application\Service\MembershipGuard;
use App\Project\Domain\Entity\Project;
use App\Project\Domain\Entity\ProjectMember;
use App\Project\Domain\Enum\MemberRole;
use App\Project\Domain\Exception\ProjectForbiddenException;
use App\Project\Domain\Exception\ProjectNotFoundException;
use App\Project\Domain\Repository\ProjectMemberRepository;
use App\Project\Domain\Repository\ProjectRepository;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class RenameProjectCommandHandlerTest extends TestCase
{
    private ProjectRepository $projectRepository;
    private ProjectMemberRepository $memberRepository;
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        $this->projectRepository = $this->createMock(ProjectRepository::class);
        $this->memberRepository = $this->createMock(ProjectMemberRepository::class);
        $this->userRepository = $this->createMock(UserRepository::class);
    }

    private function handler(?Project $project, ?ProjectMember $actorMembership): RenameProjectCommandHandler
    {
        $this->projectRepository->method('findById')->willReturn($project);
        $this->memberRepository->method('findByProjectAndUser')->willReturn($actorMembership);

        return new RenameProjectCommandHandler($this->projectRepository, new MembershipGuard($this->memberRepository, $this->userRepository));
    }

    public function testRenamesWhenActorIsAdmin(): void
    {
        $project = Project::create(Uuid::uuid4(), 'Old Name');
        $actorId = Uuid::uuid4();
        $admin = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Admin);
        $this->projectRepository->expects($this->once())->method('save')->with($project);

        $result = $this->handler($project, $admin)
            ->__invoke(new RenameProjectCommand((string) $project->id(), 'New Name', (string) $actorId));

        self::assertSame('New Name', $result->name());
    }

    public function testThrowsWhenActorIsPlainMember(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $actorId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);

        $this->expectException(ProjectForbiddenException::class);

        $this->handler($project, $member)
            ->__invoke(new RenameProjectCommand((string) $project->id(), 'X', (string) $actorId));
    }

    public function testThrowsWhenActorIsNotMember(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $actorId = Uuid::uuid4();

        $this->expectException(ProjectForbiddenException::class);

        $this->handler($project, null)
            ->__invoke(new RenameProjectCommand((string) $project->id(), 'X', (string) $actorId));
    }

    public function testThrowsWhenProjectMissing(): void
    {
        $this->expectException(ProjectNotFoundException::class);

        $this->handler(null, null)
            ->__invoke(new RenameProjectCommand((string) Uuid::uuid4(), 'X', (string) Uuid::uuid4()));
    }
}
