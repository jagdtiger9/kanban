<?php

declare(strict_types=1);

namespace App\Tests\Unit\Project\Application;

use App\Project\Application\Message\Command\HardDeleteProjectCommand;
use App\Project\Application\MessageHandler\HardDeleteProjectCommandHandler;
use App\Project\Domain\Entity\Project;
use App\Project\Domain\Exception\ProjectNotFoundException;
use App\Project\Domain\Repository\ProjectRepository;
use App\Shared\Domain\Exception\AccessForbiddenException;
use App\User\Application\Service\GlobalAdminGuard;
use App\User\Domain\Entity\User;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class HardDeleteProjectCommandHandlerTest extends TestCase
{
    private ProjectRepository $projectRepository;
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        $this->projectRepository = $this->createMock(ProjectRepository::class);
        $this->userRepository = $this->createMock(UserRepository::class);
    }

    private function handler(?Project $project, ?User $actor): HardDeleteProjectCommandHandler
    {
        $this->projectRepository->method('findById')->willReturn($project);
        $this->userRepository->method('findById')->willReturn($actor);

        return new HardDeleteProjectCommandHandler($this->projectRepository, new GlobalAdminGuard($this->userRepository));
    }

    public function testRemovesWhenActorIsGlobalAdmin(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $actor = User::create(Uuid::uuid4(), 'admin@example.com', 'hash', 'Admin');
        $actor->promoteToGlobalAdmin();
        $this->projectRepository->expects($this->once())->method('remove')->with($project);

        $this->handler($project, $actor)
            ->__invoke(new HardDeleteProjectCommand((string) $project->id(), (string) $actor->id()));
    }

    public function testThrowsWhenActorIsNotGlobalAdmin(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $actor = User::create(Uuid::uuid4(), 'user@example.com', 'hash', 'User');
        $this->projectRepository->expects($this->never())->method('remove');

        $this->expectException(AccessForbiddenException::class);

        $this->handler($project, $actor)
            ->__invoke(new HardDeleteProjectCommand((string) $project->id(), (string) $actor->id()));
    }

    public function testThrowsWhenProjectMissing(): void
    {
        $actor = User::create(Uuid::uuid4(), 'admin@example.com', 'hash', 'Admin');
        $actor->promoteToGlobalAdmin();

        $this->expectException(ProjectNotFoundException::class);

        $this->handler(null, $actor)
            ->__invoke(new HardDeleteProjectCommand((string) Uuid::uuid4(), (string) $actor->id()));
    }
}
