<?php

declare(strict_types=1);

namespace App\Board\Application\MessageHandler;

use App\Board\Application\Message\Command\MarkCardSeenCommand;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Entity\CardView;
use App\Board\Domain\Repository\CardViewRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class MarkCardSeenCommandHandler
{
    public function __construct(
        private CardGuard $guard,
        private CardViewRepository $cardViewRepository,
    ) {
    }

    public function __invoke(MarkCardSeenCommand $command): void
    {
        $actorId = Uuid::fromString($command->actorId);
        $card = $this->guard->requireCard(Uuid::fromString($command->cardId), $actorId);

        $view = $this->cardViewRepository->findByUserAndCard($actorId, $card->id());
        if ($view === null) {
            $this->cardViewRepository->save(CardView::create(Uuid::uuid4(), $actorId, $card->id()));

            return;
        }

        $view->markSeen(new \DateTimeImmutable());
        $this->cardViewRepository->save($view);
    }
}
