<?php

declare(strict_types=1);

namespace App\Project\Application\MessageHandler;

use App\Project\Application\Message\Command\RestoreProjectCommand;
use App\Project\Application\Service\MembershipGuard;
use App\Project\Domain\Entity\Project;
use App\Project\Domain\Exception\ProjectNotFoundException;
use App\Project\Domain\Repository\ProjectRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RestoreProjectCommandHandler
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private MembershipGuard $guard,
    ) {
    }

    public function __invoke(RestoreProjectCommand $command): Project
    {
        $projectId = Uuid::fromString($command->projectId);
        $actorId = Uuid::fromString($command->actorId);

        $project = $this->projectRepository->findById($projectId);
        if ($project === null) {
            throw new ProjectNotFoundException('Project not found');
        }
        $this->guard->requireAdmin($projectId, $actorId);

        $project->restore($actorId);
        $this->projectRepository->save($project);

        return $project;
    }
}
