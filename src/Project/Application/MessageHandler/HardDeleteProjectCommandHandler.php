<?php

declare(strict_types=1);

namespace App\Project\Application\MessageHandler;

use App\Project\Application\Message\Command\HardDeleteProjectCommand;
use App\Project\Domain\Exception\ProjectNotFoundException;
use App\Project\Domain\Repository\ProjectRepository;
use App\User\Application\Service\GlobalAdminGuard;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class HardDeleteProjectCommandHandler
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private GlobalAdminGuard $guard,
    ) {
    }

    public function __invoke(HardDeleteProjectCommand $command): void
    {
        $projectId = Uuid::fromString($command->projectId);

        $project = $this->projectRepository->findById($projectId);
        if ($project === null) {
            throw new ProjectNotFoundException('Project not found');
        }

        $this->guard->requireGlobalAdmin(Uuid::fromString($command->actorId));

        $this->projectRepository->remove($project);
    }
}
