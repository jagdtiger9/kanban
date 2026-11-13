<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Application;

use App\Project\Application\Message\Command\AddProjectMemberCommand;
use App\Project\Application\MessageHandler\AddProjectMemberCommandHandler;
use App\Project\Application\Service\MembershipGuard;
use App\Project\Domain\Entity\Project;
use App\Project\Domain\Entity\ProjectMember;
use App\Project\Domain\Enum\MemberRole;
use App\Project\Domain\Exception\ProjectForbiddenException;
use App\Project\Domain\Exception\ProjectMemberAlreadyExistsException;
use App\Project\Domain\Repository\ProjectMemberRepository;
use App\Project\Domain\Repository\ProjectRepository;
use App\User\Domain\Entity\User;
use App\User\Domain\Exception\UserNotFoundException;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class AddProjectMemberCommandHandlerTest extends TestCase
{
    private ProjectRepository $projectRepository;
    private ProjectMemberRepository $memberRepository;
    private UserRepository $userRepository;
    private Project $project;
    private ProjectMember $actorMembership;
    private string $actorId;
    private string $targetUserId;
    private ?ProjectMember $existingMembership = null;
    private ?User $targetUser = null;

    protected function setUp(): void
    {
        $this->projectRepository = $this->createMock(ProjectRepository::class);
        $this->memberRepository = $this->createMock(ProjectMemberRepository::class);
        $this->userRepository = $this->createMock(UserRepository::class);

        $this->project = Project::create(Uuid::uuid4(), 'P');
        $actorId = Uuid::uuid4();
        $this->actorId = (string) $actorId;
        $this->actorMembership = ProjectMember::create(Uuid::uuid4(), $this->project->id(), $actorId, MemberRole::Admin);
        $this->targetUserId = (string) Uuid::uuid4();
        $this->targetUser = User::create(Uuid::uuid4(), 'target@example.com', 'hash', 'Target');

        $this->projectRepository->method('findById')->willReturn($this->project);
        $this->memberRepository->method('findByProjectAndUser')->willReturnCallback(
            fn (UuidInterface $projectId, UuidInterface $userId): ?ProjectMember =>
                (string) $userId === $this->actorId ? $this->actorMembership : $this->existingMembership,
        );
        $this->userRepository->method('findById')->willReturnCallback(
            fn (UuidInterface $id): ?User => $this->targetUser,
        );
    }

    private function handler(): AddProjectMemberCommandHandler
    {
        return new AddProjectMemberCommandHandler(
            $this->projectRepository,
            $this->memberRepository,
            $this->userRepository,
            new MembershipGuard($this->memberRepository, $this->userRepository),
        );
    }

    public function testAddsMember(): void
    {
        $saved = null;
        $this->memberRepository->expects($this->once())->method('save')
            ->with($this->callback(static function (ProjectMember $member) use (&$saved): bool {
                $saved = $member;

                return true;
            }));

        $result = $this->handler()->__invoke(
            new AddProjectMemberCommand((string) $this->project->id(), $this->targetUserId, 'member', $this->actorId),
        );

        self::assertSame($saved, $result);
        self::assertSame(MemberRole::Member, $result->role());
        self::assertTrue($result->projectId()->equals($this->project->id()));
        self::assertSame($this->targetUserId, (string) $result->userId());
    }

    public function testThrowsWhenUserIsAlreadyMember(): void
    {
        $this->existingMembership = ProjectMember::create(
            Uuid::uuid4(),
            $this->project->id(),
            Uuid::uuid4(),
            MemberRole::Member,
        );
        $this->memberRepository->expects($this->never())->method('save');

        $this->expectException(ProjectMemberAlreadyExistsException::class);

        $this->handler()->__invoke(
            new AddProjectMemberCommand((string) $this->project->id(), $this->targetUserId, 'member', $this->actorId),
        );
    }

    public function testThrowsWhenUserNotFound(): void
    {
        $this->targetUser = null;
        $this->memberRepository->expects($this->never())->method('save');

        $this->expectException(UserNotFoundException::class);

        $this->handler()->__invoke(
            new AddProjectMemberCommand((string) $this->project->id(), $this->targetUserId, 'member', $this->actorId),
        );
    }

    public function testThrowsWhenActorIsNotAdmin(): void
    {
        $actorId = Uuid::uuid4();
        $this->actorId = (string) $actorId;
        $this->actorMembership = ProjectMember::create(Uuid::uuid4(), $this->project->id(), $actorId, MemberRole::Member);

        $this->expectException(ProjectForbiddenException::class);

        $this->handler()->__invoke(
            new AddProjectMemberCommand((string) $this->project->id(), $this->targetUserId, 'member', (string) $actorId),
        );
    }
}

