<?php

declare(strict_types=1);

namespace App\Board\Application\MessageHandler;

use App\Board\Application\Message\Command\UpdateLabelCommand;
use App\Board\Domain\Entity\Label;
use App\Board\Domain\Exception\LabelNotFoundException;
use App\Board\Domain\Repository\BoardRepository;
use App\Board\Domain\Repository\LabelRepository;
use App\Project\Application\Service\MembershipGuard;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class UpdateLabelCommandHandler
{
    public function __construct(
        private LabelRepository $labelRepository,
        private BoardRepository $boardRepository,
        private MembershipGuard $guard,
    ) {
    }

    public function __invoke(UpdateLabelCommand $command): Label
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
        $actorId = Uuid::fromString($command->actorId);
        $this->guard->requireMember($board->projectId(), $actorId);

        $label->rename($command->name, $actorId);
        $label->changeColor($command->color, $actorId);
        $this->labelRepository->save($label);

        return $label;
    }
}
