<?php

declare(strict_types=1);

namespace App\Board\Application\MessageHandler;

use App\Board\Application\Message\Command\MoveCardCommand;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Exception\ColumnNotFoundException;
use App\Board\Domain\Repository\CardRepository;
use App\Board\Domain\Repository\ColumnRepository;
use App\Board\Domain\Service\CardMoveService;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class MoveCardCommandHandler
{
    public function __construct(
        private CardGuard $guard,
        private ColumnRepository $columnRepository,
        private CardRepository $cardRepository,
        private CardMoveService $moveService,
    ) {
    }

    public function __invoke(MoveCardCommand $command): void
    {
        $actorId = Uuid::fromString($command->actorId);

        $card = $this->guard->requireCard(Uuid::fromString($command->cardId), $actorId);
        $board = $this->guard->resolveBoard($card);

        $targetColumn = $this->columnRepository->findById(Uuid::fromString($command->targetColumnId));
        if ($targetColumn === null || !$targetColumn->boardId()->equals($board->id())) {
            throw new ColumnNotFoundException('Column not found');
        }

        $oldColumnId = $card->columnId();
        $oldColumnCards = $this->cardRepository->findByColumn($oldColumnId);
        $newColumnCards = $this->cardRepository->findByColumn($targetColumn->id());

        $this->moveService->move(
            $card,
            $targetColumn->id(),
            $command->targetIndex,
            $oldColumnCards,
            $newColumnCards,
            $actorId,
        );

        $touched = $oldColumnCards;
        if (!$targetColumn->id()->equals($oldColumnId)) {
            $touched = array_merge($touched, $newColumnCards);
        }
        foreach ($touched as $touchedCard) {
            $this->cardRepository->save($touchedCard);
        }
    }
}
