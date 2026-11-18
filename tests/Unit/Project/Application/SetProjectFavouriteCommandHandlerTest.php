<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Application;

use App\Project\Application\Message\Command\SetProjectFavouriteCommand;
use App\Project\Application\MessageHandler\SetProjectFavouriteCommandHandler;
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

final class SetProjectFavouriteCommandHandlerTest extends TestCase
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

    private function handler(?Project $project, ?ProjectMember $actorMembership): SetProjectFavouriteCommandHandler
    {
        $this->projectRepository->method('findById')->willReturn($project);
        $this->memberRepository->method('findByProjectAndUser')->willReturn($actorMembership);

        return new SetProjectFavouriteCommandHandler(
            $this->projectRepository,
            $this->memberRepository,
            new MembershipGuard($this->memberRepository, $this->userRepository),
        );
    }

    public function testFavouritesProjectForMember(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $actorId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);

        $this->memberRepository->expects($this->once())->method('save')->with($member);

        $this->handler($project, $member)
            ->__invoke(new SetProjectFavouriteCommand((string) $project->id(), true, (string) $actorId));

        self::assertTrue($member->isFavourite());
    }

    public function testUnfavouritesProjectForMember(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $actorId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member, isFavourite: true);

        $this->memberRepository->expects($this->once())->method('save')->with($member);

        $this->handler($project, $member)
            ->__invoke(new SetProjectFavouriteCommand((string) $project->id(), false, (string) $actorId));

        self::assertFalse($member->isFavourite());
    }

    public function testThrowsWhenActorIsNotMember(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');

        $this->expectException(ProjectForbiddenException::class);

        $this->handler($project, null)
            ->__invoke(new SetProjectFavouriteCommand((string) $project->id(), true, (string) Uuid::uuid4()));
    }

    public function testFavouritesProjectForGlobalAdminMember(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $admin = User::create(Uuid::uuid4(), 'admin@example.com', 'hash', 'Admin');
        $admin->promoteToGlobalAdmin();
        $this->userRepository->method('findById')->willReturn($admin);
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $admin->id(), MemberRole::Member);

        $this->memberRepository->expects($this->once())->method('save')->with($member);

        $this->handler($project, $member)
            ->__invoke(new SetProjectFavouriteCommand((string) $project->id(), true, (string) $admin->id()));

        self::assertTrue($member->isFavourite());
    }

    public function testThrowsWhenGlobalAdminHasNoMembership(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $admin = User::create(Uuid::uuid4(), 'admin@example.com', 'hash', 'Admin');
        $admin->promoteToGlobalAdmin();
        $this->userRepository->method('findById')->willReturn($admin);

        $this->expectException(ProjectForbiddenException::class);

        $this->handler($project, null)
            ->__invoke(new SetProjectFavouriteCommand((string) $project->id(), true, (string) $admin->id()));
    }

    public function testThrowsWhenProjectDoesNotExist(): void
    {
        $this->expectException(ProjectNotFoundException::class);

        $this->handler(null, null)
            ->__invoke(new SetProjectFavouriteCommand((string) Uuid::uuid4(), true, (string) Uuid::uuid4()));
    }
}
