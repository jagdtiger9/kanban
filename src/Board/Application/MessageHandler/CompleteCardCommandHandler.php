<?php

declare(strict_types=1);

namespace App\Board\Application\MessageHandler;

use App\Board\Application\Message\Command\CompleteCardCommand;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Entity\CardOccurrence;
use App\Board\Domain\Enum\ColumnKind;
use App\Board\Domain\Exception\InvalidRecurrenceException;
use App\Board\Domain\Repository\CardOccurrenceRepository;
use App\Board\Domain\Repository\CardRepository;
use App\Board\Domain\Service\OccurrenceCalculator;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CompleteCardCommandHandler
{
    public function __construct(
        private CardGuard $guard,
        private CardOccurrenceRepository $occurrenceRepository,
        private OccurrenceCalculator $calculator,
        private CardRepository $cardRepository,
    ) {
    }

    public function __invoke(CompleteCardCommand $command): void
    {
        $actorId = Uuid::fromString($command->actorId);

        $card = $this->guard->requireCard(Uuid::fromString($command->cardId), $actorId);
        $this->guard->requireCompletable($card, $actorId);

        if ($this->guard->column($card)->kind() === ColumnKind::Sticker) {
            $card->complete($actorId);
            $this->cardRepository->save($card);

            return;
        }

        $rule = $card->recurrence();
        if ($rule === null) {
            throw new InvalidRecurrenceException('The card is not recurring');
        }

        $period = new \DateTimeImmutable($this->calculator->currentPeriod($rule, new \DateTimeImmutable()));
        if ($this->occurrenceRepository->findByCardAndPeriod($card->id(), $period) !== null) {
            return;
        }

        $this->occurrenceRepository->save(
            CardOccurrence::create(Uuid::uuid4(), $card->id(), $period, $actorId),
        );
    }
}
