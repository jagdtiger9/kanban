<?php

declare(strict_types=1);

namespace App\Project\Application\MessageHandler;

use App\Project\Application\Message\Command\HardDeleteProjectCommand;
use App\Project\Domain\Exception\ProjectForbiddenException;
use App\Project\Domain\Exception\ProjectNotFoundException;
use App\Project\Domain\Repository\ProjectRepository;
use App\User\Domain\Repository\UserRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class HardDeleteProjectCommandHandler
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private UserRepository $userRepository,
    ) {
    }

    public function __invoke(HardDeleteProjectCommand $command): void
    {
        $projectId = Uuid::fromString($command->projectId);

        $project = $this->projectRepository->findById($projectId);
        if ($project === null) {
            throw new ProjectNotFoundException('Project not found');
        }

        $actor = $this->userRepository->findById(Uuid::fromString($command->actorId));
        if ($actor === null) {
            throw new \LogicException('Authenticated actor not found');
        }
        if (!$actor->isGlobalAdmin()) {
            throw new ProjectForbiddenException('Only global admins can permanently delete projects');
        }

        $this->projectRepository->remove($project);
    }
}
