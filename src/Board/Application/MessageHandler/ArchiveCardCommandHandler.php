<?php

declare(strict_types=1);

namespace App\Board\Application\MessageHandler;

use App\Board\Application\Message\Command\ArchiveCardCommand;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Repository\CardRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ArchiveCardCommandHandler
{
    public function __construct(
        private CardGuard $guard,
        private CardRepository $cardRepository,
    ) {
    }

    public function __invoke(ArchiveCardCommand $command): void
    {
        $actorId = Uuid::fromString($command->actorId);

        $card = $this->guard->requireCardEditable(Uuid::fromString($command->cardId), $actorId);

        $card->archive($actorId);
        $this->cardRepository->save($card);
    }
}
