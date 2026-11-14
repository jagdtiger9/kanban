<?php

declare(strict_types=1);

namespace App\Board\Application\MessageHandler;

use App\Board\Application\Message\Command\UpdateCardContentCommand;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Entity\Card;
use App\Board\Domain\Repository\CardRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class UpdateCardContentCommandHandler
{
    public function __construct(
        private CardGuard $guard,
        private CardRepository $cardRepository,
    ) {
    }

    public function __invoke(UpdateCardContentCommand $command): Card
    {
        $actorId = Uuid::fromString($command->actorId);

        $card = $this->guard->requireCardEditable(Uuid::fromString($command->cardId), $actorId);

        $card->changeTitle($command->title, $actorId);
        $card->changeDescription($command->description, $actorId);
        $this->cardRepository->save($card);

        return $card;
    }
}
