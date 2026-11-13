<?php

declare(strict_types=1);

namespace App\Board\Application\MessageHandler;

use App\Board\Application\Message\Command\DeleteLabelCommand;
use App\Board\Domain\Exception\LabelNotFoundException;
use App\Board\Domain\Repository\BoardRepository;
use App\Board\Domain\Repository\LabelRepository;
use App\Project\Application\Service\MembershipGuard;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class DeleteLabelCommandHandler
{
    public function __construct(
        private LabelRepository $labelRepository,
        private BoardRepository $boardRepository,
        private MembershipGuard $guard,
    ) {
    }

    public function __invoke(DeleteLabelCommand $command): void
    {
        $labelId = Uuid::fromString($command->labelId);

        $label = $this->labelRepository->findById($labelId);
        if ($label === null) {
            throw new LabelNotFoundException('Label not found');
        }
        $board = $this->boardRepository->findById($label->boardId());
        if ($board === null) {
            throw new \LogicException('Board must exist for every label');
        }
        $this->guard->requireAdmin($board->projectId(), Uuid::fromString($command->actorId));

        $this->labelRepository->remove($label);
    }
}
