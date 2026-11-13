<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Application;

use App\Project\Application\Message\Command\ArchiveProjectCommand;
use App\Project\Application\MessageHandler\ArchiveProjectCommandHandler;
use App\Project\Application\Service\MembershipGuard;
use App\Project\Domain\Entity\Project;
use App\Project\Domain\Entity\ProjectMember;
use App\Project\Domain\Enum\MemberRole;
use App\Project\Domain\Exception\ProjectForbiddenException;
use App\Project\Domain\Repository\ProjectMemberRepository;
use App\Project\Domain\Repository\ProjectRepository;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class ArchiveProjectCommandHandlerTest extends TestCase
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

    private function handler(?Project $project, ?ProjectMember $actorMembership): ArchiveProjectCommandHandler
    {
        $this->projectRepository->method('findById')->willReturn($project);
        $this->memberRepository->method('findByProjectAndUser')->willReturn($actorMembership);

        return new ArchiveProjectCommandHandler($this->projectRepository, new MembershipGuard($this->memberRepository, $this->userRepository));
    }

    public function testArchivesWhenActorIsAdmin(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $actorId = Uuid::uuid4();
        $admin = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Admin);

        $result = $this->handler($project, $admin)
            ->__invoke(new ArchiveProjectCommand((string) $project->id(), (string) $actorId));

        self::assertTrue($result->isArchived());
        self::assertNotNull($result->archivedAt());
    }

    public function testThrowsWhenActorIsNotAdmin(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $actorId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);

        $this->expectException(ProjectForbiddenException::class);

        $this->handler($project, $member)
            ->__invoke(new ArchiveProjectCommand((string) $project->id(), (string) $actorId));
    }
}
