<?php

declare(strict_types=1);

namespace App\Board\Application\MessageHandler;

use App\Board\Application\Message\Command\SetCardDueDateCommand;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Repository\CardRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsMessageHandler]
final class SetCardDueDateCommandHandler
{
    public function __construct(
        private CardGuard $guard,
        private CardRepository $cardRepository,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(SetCardDueDateCommand $command): void
    {
        $actorId = Uuid::fromString($command->actorId);

        $card = $this->guard->requireCard(Uuid::fromString($command->cardId), $actorId);

        $dueDate = $command->dueDate === null ? null : new \DateTimeImmutable($command->dueDate);
        $event = $card->changeDueDate($dueDate, $actorId);
        $this->cardRepository->save($card);
        if ($event !== null) {
            $this->eventDispatcher->dispatch($event);
        }
    }
}
