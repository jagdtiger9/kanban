<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Application;

use App\Project\Application\Message\Command\RemoveProjectMemberCommand;
use App\Project\Application\MessageHandler\RemoveProjectMemberCommandHandler;
use App\Project\Application\Service\MembershipGuard;
use App\Project\Domain\Entity\Project;
use App\Project\Domain\Entity\ProjectMember;
use App\Project\Domain\Enum\MemberRole;
use App\Project\Domain\Exception\LastProjectAdminException;
use App\Project\Domain\Exception\ProjectForbiddenException;
use App\Project\Domain\Exception\ProjectMemberNotFoundException;
use App\Project\Domain\Repository\ProjectMemberRepository;
use App\Project\Domain\Repository\ProjectRepository;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class RemoveProjectMemberCommandHandlerTest extends TestCase
{
    private ProjectRepository $projectRepository;
    private ProjectMemberRepository $memberRepository;
    private UserRepository $userRepository;
    private Project $project;
    private ProjectMember $actorMembership;
    private string $actorId;
    private ?ProjectMember $targetMembership = null;

    protected function setUp(): void
    {
        $this->projectRepository = $this->createMock(ProjectRepository::class);
        $this->memberRepository = $this->createMock(ProjectMemberRepository::class);
        $this->userRepository = $this->createMock(UserRepository::class);

        $this->project = Project::create(Uuid::uuid4(), 'P');
        $actorId = Uuid::uuid4();
        $this->actorId = (string) $actorId;
        $this->actorMembership = ProjectMember::create(Uuid::uuid4(), $this->project->id(), $actorId, MemberRole::Admin);

        $this->projectRepository->method('findById')->willReturn($this->project);
        $this->memberRepository->method('findByProjectAndUser')->willReturnCallback(
            fn (UuidInterface $projectId, UuidInterface $userId): ?ProjectMember =>
                (string) $userId === $this->actorId ? $this->actorMembership : $this->targetMembership,
        );
    }

    private function handler(): RemoveProjectMemberCommandHandler
    {
        return new RemoveProjectMemberCommandHandler(
            $this->projectRepository,
            $this->memberRepository,
            new MembershipGuard($this->memberRepository, $this->userRepository),
        );
    }

    public function testRemovesPlainMember(): void
    {
        $target = ProjectMember::create(Uuid::uuid4(), $this->project->id(), Uuid::uuid4(), MemberRole::Member);
        $this->targetMembership = $target;
        $this->memberRepository->method('findByProject')->willReturn([$this->actorMembership, $target]);
        $this->memberRepository->expects($this->once())->method('remove')->with($target);

        $this->handler()->__invoke(
            new RemoveProjectMemberCommand((string) $this->project->id(), (string) $target->userId(), $this->actorId),
        );
    }

    public function testThrowsWhenRemovingLastAdmin(): void
    {
        $target = ProjectMember::create(Uuid::uuid4(), $this->project->id(), Uuid::uuid4(), MemberRole::Admin);
        $this->targetMembership = $target;
        $this->memberRepository->method('findByProject')->willReturn([$target]);
        $this->memberRepository->expects($this->never())->method('remove');

        $this->expectException(LastProjectAdminException::class);

        $this->handler()->__invoke(
            new RemoveProjectMemberCommand((string) $this->project->id(), (string) $target->userId(), $this->actorId),
        );
    }

    public function testThrowsWhenTargetIsNotMember(): void
    {
        $this->expectException(ProjectMemberNotFoundException::class);

        $this->handler()->__invoke(
            new RemoveProjectMemberCommand((string) $this->project->id(), (string) Uuid::uuid4(), $this->actorId),
        );
    }

    public function testThrowsWhenActorIsNotAdmin(): void
    {
        $actorId = Uuid::uuid4();
        $this->actorId = (string) $actorId;
        $this->actorMembership = ProjectMember::create(Uuid::uuid4(), $this->project->id(), $actorId, MemberRole::Member);

        $this->expectException(ProjectForbiddenException::class);

        $this->handler()->__invoke(
            new RemoveProjectMemberCommand((string) $this->project->id(), (string) Uuid::uuid4(), (string) $actorId),
        );
    }
}
