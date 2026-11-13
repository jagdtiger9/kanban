<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Application;

use App\Project\Application\Message\Command\ChangeProjectMemberRoleCommand;
use App\Project\Application\MessageHandler\ChangeProjectMemberRoleCommandHandler;
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

final class ChangeProjectMemberRoleCommandHandlerTest extends TestCase
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

    private function handler(): ChangeProjectMemberRoleCommandHandler
    {
        return new ChangeProjectMemberRoleCommandHandler(
            $this->projectRepository,
            $this->memberRepository,
            new MembershipGuard($this->memberRepository, $this->userRepository),
        );
    }

    public function testPromotesMemberToAdmin(): void
    {
        $target = ProjectMember::create(Uuid::uuid4(), $this->project->id(), Uuid::uuid4(), MemberRole::Member);
        $this->targetMembership = $target;
        $this->memberRepository->expects($this->once())->method('save')->with($target);

        $result = $this->handler()->__invoke(
            new ChangeProjectMemberRoleCommand(
                (string) $this->project->id(),
                (string) $target->userId(),
                'admin',
                $this->actorId,
            ),
        );

        self::assertSame(MemberRole::Admin, $result->role());
    }

    public function testDemotesAdminWhenAnotherAdminExists(): void
    {
        $target = ProjectMember::create(Uuid::uuid4(), $this->project->id(), Uuid::uuid4(), MemberRole::Admin);
        $otherAdmin = ProjectMember::create(Uuid::uuid4(), $this->project->id(), Uuid::uuid4(), MemberRole::Admin);
        $this->targetMembership = $target;
        $this->memberRepository->method('findByProject')->willReturn([$target, $otherAdmin]);

        $result = $this->handler()->__invoke(
            new ChangeProjectMemberRoleCommand(
                (string) $this->project->id(),
                (string) $target->userId(),
                'member',
                $this->actorId,
            ),
        );

        self::assertSame(MemberRole::Member, $result->role());
    }

    public function testThrowsWhenDemotingLastAdmin(): void
    {
        $target = ProjectMember::create(Uuid::uuid4(), $this->project->id(), Uuid::uuid4(), MemberRole::Admin);
        $this->targetMembership = $target;
        $this->memberRepository->method('findByProject')->willReturn([$target]);
        $this->memberRepository->expects($this->never())->method('save');

        $this->expectException(LastProjectAdminException::class);

        $this->handler()->__invoke(
            new ChangeProjectMemberRoleCommand(
                (string) $this->project->id(),
                (string) $target->userId(),
                'member',
                $this->actorId,
            ),
        );
    }

    public function testThrowsWhenTargetIsNotMember(): void
    {
        $this->expectException(ProjectMemberNotFoundException::class);

        $this->handler()->__invoke(
            new ChangeProjectMemberRoleCommand(
                (string) $this->project->id(),
                (string) Uuid::uuid4(),
                'admin',
                $this->actorId,
            ),
        );
    }

    public function testThrowsWhenActorIsNotAdmin(): void
    {
        $actorId = Uuid::uuid4();
        $this->actorId = (string) $actorId;
        $this->actorMembership = ProjectMember::create(Uuid::uuid4(), $this->project->id(), $actorId, MemberRole::Member);

        $this->expectException(ProjectForbiddenException::class);

        $this->handler()->__invoke(
            new ChangeProjectMemberRoleCommand(
                (string) $this->project->id(),
                (string) Uuid::uuid4(),
                'admin',
                (string) $actorId,
            ),
        );
    }
}
