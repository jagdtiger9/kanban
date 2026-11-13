<?php

declare(strict_types=1);

namespace App\Board\Application\MessageHandler;

use App\Board\Application\Message\Command\SetCardLabelsCommand;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Entity\Label;
use App\Board\Domain\Exception\LabelNotFoundException;
use App\Board\Domain\Repository\CardRepository;
use App\Board\Domain\Repository\LabelRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsMessageHandler]
final class SetCardLabelsCommandHandler
{
    public function __construct(
        private CardGuard $guard,
        private LabelRepository $labelRepository,
        private CardRepository $cardRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(SetCardLabelsCommand $command): void
    {
        $actorId = Uuid::fromString($command->actorId);

        $card = $this->guard->requireCard(Uuid::fromString($command->cardId), $actorId);
        $board = $this->guard->resolveBoard($card);

        $labels = [];
        foreach ($command->labelIds as $labelId) {
            $label = $this->labelRepository->findById(Uuid::fromString($labelId));
            if ($label === null || !$label->boardId()->equals($board->id())) {
                throw new LabelNotFoundException('Label not found');
            }
            $labels[] = $label;
        }

        $events = [];
        foreach ($card->labels() as $existing) {
            if (!\in_array((string) $existing->id(), $command->labelIds, true)) {
                $event = $card->removeLabel($existing, $actorId);
                if ($event !== null) {
                    $events[] = $event;
                }
            }
        }
        foreach ($labels as $label) {
            $event = $card->addLabel($label, $actorId);
            if ($event !== null) {
                $events[] = $event;
            }
        }

        $this->cardRepository->save($card);
        foreach ($events as $event) {
            $this->eventDispatcher->dispatch($event);
        }
    }
}
