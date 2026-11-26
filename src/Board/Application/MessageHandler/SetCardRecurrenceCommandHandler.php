<?php

declare(strict_types=1);

namespace App\Board\Application\MessageHandler;

use App\Board\Application\Message\Command\SetCardRecurrenceCommand;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Exception\InvalidRecurrenceException;
use App\Board\Domain\Repository\CardRepository;
use App\Board\Domain\ValueObject\RecurrenceRule;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SetCardRecurrenceCommandHandler
{
    public function __construct(
        private CardGuard $guard,
        private CardRepository $cardRepository,
    ) {
    }

    public function __invoke(SetCardRecurrenceCommand $command): void
    {
        $actorId = Uuid::fromString($command->actorId);

        $card = $this->guard->requireCard(Uuid::fromString($command->cardId), $actorId);
        $this->guard->requireTaskCard($card);

        try {
            $rule = $command->recurrence === null
                ? null
                : RecurrenceRule::fromArray($command->recurrence->toArray());
        } catch (\InvalidArgumentException $e) {
            throw new InvalidRecurrenceException($e->getMessage(), 0, $e);
        }

        $card->changeRecurrence($rule, $actorId);
        $this->cardRepository->save($card);
    }
}
