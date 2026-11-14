<?php

declare(strict_types=1);

namespace App\Board\Application\MessageHandler;

use App\Board\Application\Message\Command\RestoreCardCommand;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Repository\CardRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RestoreCardCommandHandler
{
    public function __construct(
        private CardGuard $guard,
        private CardRepository $cardRepository,
    ) {
    }

    public function __invoke(RestoreCardCommand $command): void
    {
        $actorId = Uuid::fromString($command->actorId);

        $card = $this->guard->requireCardEditable(Uuid::fromString($command->cardId), $actorId);

        $card->restore($actorId);
        $this->cardRepository->save($card);
    }
}
