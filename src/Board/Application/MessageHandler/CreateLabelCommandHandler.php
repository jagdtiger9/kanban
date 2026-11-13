<?php

declare(strict_types=1);

namespace App\Board\Application\MessageHandler;

use App\Board\Application\Message\Command\CreateLabelCommand;
use App\Board\Domain\Entity\Label;
use App\Board\Domain\Repository\BoardRepository;
use App\Board\Domain\Repository\LabelRepository;
use App\Project\Application\Service\MembershipGuard;
use App\Project\Domain\Exception\ProjectNotFoundException;
use App\Project\Domain\Repository\ProjectRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CreateLabelCommandHandler
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private BoardRepository $boardRepository,
        private LabelRepository $labelRepository,
        private MembershipGuard $guard,
    ) {
    }

    public function __invoke(CreateLabelCommand $command): Label
    {
        $projectId = Uuid::fromString($command->projectId);
        $actorId = Uuid::fromString($command->actorId);

        $project = $this->projectRepository->findById($projectId);
        if ($project === null) {
            throw new ProjectNotFoundException('Project not found');
        }
        $this->guard->requireMember($projectId, $actorId);

        $board = $this->boardRepository->findByProject($projectId);
        if ($board === null) {
            throw new \LogicException('Board must exist for every project');
        }

        $label = Label::create(Uuid::uuid4(), $board->id(), $command->name, $command->color);
        $this->labelRepository->save($label);

        return $label;
    }
}
