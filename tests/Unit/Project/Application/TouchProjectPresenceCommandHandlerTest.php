<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Application;

use App\Project\Application\Message\Command\TouchProjectPresenceCommand;
use App\Project\Application\MessageHandler\TouchProjectPresenceCommandHandler;
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

final class TouchProjectPresenceCommandHandlerTest extends TestCase
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

    private function handler(?Project $project, ?ProjectMember $actorMembership): TouchProjectPresenceCommandHandler
    {
        $this->projectRepository->method('findById')->willReturn($project);
        $this->memberRepository->method('findByProjectAndUser')->willReturn($actorMembership);

        return new TouchProjectPresenceCommandHandler(
            $this->projectRepository,
            $this->memberRepository,
            new MembershipGuard($this->memberRepository, $this->userRepository),
        );
    }

    public function testTouchesPresenceForMember(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $actorId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);

        $this->memberRepository->expects($this->once())->method('save')->with($member);

        $this->handler($project, $member)
            ->__invoke(new TouchProjectPresenceCommand((string) $project->id(), (string) $actorId));

        self::assertNotNull($member->lastSeenAt());
    }

    public function testGlobalAdminWithoutMembershipIsAcceptedWithoutWriting(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $admin = User::create(Uuid::uuid4(), 'admin@example.com', 'hash', 'Admin');
        $admin->promoteToGlobalAdmin();
        $this->userRepository->method('findById')->willReturn($admin);

        $this->memberRepository->expects($this->never())->method('save');

        $this->handler($project, null)
            ->__invoke(new TouchProjectPresenceCommand((string) $project->id(), (string) $admin->id()));
    }

    public function testThrowsWhenActorIsNotMember(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');

        $this->memberRepository->expects($this->never())->method('save');

        $this->expectException(ProjectForbiddenException::class);

        $this->handler($project, null)
            ->__invoke(new TouchProjectPresenceCommand((string) $project->id(), (string) Uuid::uuid4()));
    }

    public function testThrowsWhenProjectDoesNotExist(): void
    {
        $this->expectException(ProjectNotFoundException::class);

        $this->handler(null, null)
            ->__invoke(new TouchProjectPresenceCommand((string) Uuid::uuid4(), (string) Uuid::uuid4()));
    }
}
