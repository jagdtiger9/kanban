<?php

declare(strict_types=1);

namespace App\Board\Application\MessageHandler;

use App\Board\Application\Message\Command\SetChecklistItemDoneCommand;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Repository\CardRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SetChecklistItemDoneCommandHandler
{
    public function __construct(
        private CardGuard $guard,
        private CardRepository $cardRepository,
    ) {
    }

    public function __invoke(SetChecklistItemDoneCommand $command): void
    {
        $actorId = Uuid::fromString($command->actorId);
        $card = $this->guard->requireCard(Uuid::fromString($command->cardId), $actorId);

        $card->setChecklistItemDone(Uuid::fromString($command->itemId), $command->done, $actorId);
        $this->cardRepository->save($card);
    }
}
