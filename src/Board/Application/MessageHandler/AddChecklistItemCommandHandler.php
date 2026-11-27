<?php

declare(strict_types=1);

namespace App\Board\Application\MessageHandler;

use App\Board\Application\Message\Command\AddChecklistItemCommand;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Repository\CardRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class AddChecklistItemCommandHandler
{
    public function __construct(
        private CardGuard $guard,
        private CardRepository $cardRepository,
    ) {
    }

    /** @return array{id: string, text: string, done: bool} */
    public function __invoke(AddChecklistItemCommand $command): array
    {
        $actorId = Uuid::fromString($command->actorId);
        $card = $this->guard->requireCard(Uuid::fromString($command->cardId), $actorId);

        $text = trim($command->text);
        $itemId = Uuid::uuid4();
        $card->addChecklistItem($itemId, $text, $actorId);
        $this->cardRepository->save($card);

        return ['id' => $itemId->toString(), 'text' => $text, 'done' => false];
    }
}
