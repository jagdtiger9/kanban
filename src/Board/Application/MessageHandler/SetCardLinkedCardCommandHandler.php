<?php

declare(strict_types=1);

namespace App\Board\Application\MessageHandler;

use App\Board\Application\Message\Command\SetCardLinkedCardCommand;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Exception\CardLinkException;
use App\Board\Domain\Repository\CardRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SetCardLinkedCardCommandHandler
{
    public function __construct(
        private CardGuard $guard,
        private CardRepository $cardRepository,
    ) {
    }

    public function __invoke(SetCardLinkedCardCommand $command): void
    {
        $actorId = Uuid::fromString($command->actorId);

        $card = $this->guard->requireCard(Uuid::fromString($command->cardId), $actorId);

        $linkedCardId = $command->linkedCardId === null ? null : Uuid::fromString($command->linkedCardId);
        if ($linkedCardId !== null) {
            $linkedCard = $this->guard->requireCard($linkedCardId, $actorId);
            if (!$this->guard->resolveBoard($linkedCard)->projectId()->equals($this->guard->resolveBoard($card)->projectId())) {
                throw new CardLinkException('A card can only be linked to a card in the same project');
            }
        }

        $card->changeLinkedCard($linkedCardId, $actorId);
        $this->cardRepository->save($card);
    }
}
