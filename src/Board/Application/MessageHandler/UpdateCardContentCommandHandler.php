<?php

declare(strict_types=1);

namespace App\Board\Application\MessageHandler;

use App\Board\Application\Message\Command\UpdateCardContentCommand;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Entity\Card;
use App\Board\Domain\Repository\CardRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsMessageHandler]
final class UpdateCardContentCommandHandler
{
    public function __construct(
        private CardGuard $guard,
        private CardRepository $cardRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(UpdateCardContentCommand $command): Card
    {
        $actorId = Uuid::fromString($command->actorId);

        $card = $this->guard->requireCardEditable(Uuid::fromString($command->cardId), $actorId);

        $events = array_filter([
            $card->changeTitle($command->title, $actorId),
            $card->changeDescription($command->description, $actorId),
        ]);
        $this->cardRepository->save($card);
        foreach ($events as $event) {
            $this->eventDispatcher->dispatch($event);
        }

        return $card;
    }
}
