<?php

declare(strict_types=1);

namespace App\Board\Application\MessageHandler;

use App\Board\Application\Message\Command\UncompleteCardCommand;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Enum\ColumnKind;
use App\Board\Domain\Exception\InvalidRecurrenceException;
use App\Board\Domain\Repository\CardOccurrenceRepository;
use App\Board\Domain\Repository\CardRepository;
use App\Board\Domain\Service\OccurrenceCalculator;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class UncompleteCardCommandHandler
{
    public function __construct(
        private CardGuard $guard,
        private CardOccurrenceRepository $occurrenceRepository,
        private OccurrenceCalculator $calculator,
        private CardRepository $cardRepository,
    ) {
    }

    public function __invoke(UncompleteCardCommand $command): void
    {
        $actorId = Uuid::fromString($command->actorId);

        $card = $this->guard->requireCard(Uuid::fromString($command->cardId), $actorId);
        $this->guard->requireCompletable($card, $actorId);

        if ($this->guard->column($card)->kind() === ColumnKind::Sticker) {
            $card->uncomplete($actorId);
            $this->cardRepository->save($card);

            return;
        }

        $rule = $card->recurrence();
        if ($rule === null) {
            throw new InvalidRecurrenceException('The card is not recurring');
        }

        $period = new \DateTimeImmutable($this->calculator->currentPeriod($rule, new \DateTimeImmutable()));
        $occurrence = $this->occurrenceRepository->findByCardAndPeriod($card->id(), $period);
        if ($occurrence === null) {
            return;
        }

        $occurrence->uncomplete($actorId);
        $this->occurrenceRepository->remove($occurrence);
    }
}
