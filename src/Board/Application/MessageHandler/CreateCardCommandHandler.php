<?php

declare(strict_types=1);

namespace App\Board\Application\MessageHandler;

use App\Board\Application\Message\Command\CreateCardCommand;
use App\Board\Domain\Entity\Card;
use App\Board\Domain\Event\CardCreated;
use App\Board\Domain\Exception\ColumnNotFoundException;
use App\Board\Domain\Repository\BoardRepository;
use App\Board\Domain\Repository\CardRepository;
use App\Board\Domain\Repository\ColumnRepository;
use App\Project\Application\Service\MembershipGuard;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[AsMessageHandler]
final class CreateCardCommandHandler
{
    public function __construct(
        private ColumnRepository $columnRepository,
        private BoardRepository $boardRepository,
        private CardRepository $cardRepository,
        private MembershipGuard $guard,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(CreateCardCommand $command): Card
    {
        $columnId = Uuid::fromString($command->columnId);
        $actorId = Uuid::fromString($command->actorId);

        $column = $this->columnRepository->findById($columnId);
        if ($column === null) {
            throw new ColumnNotFoundException('Column not found');
        }
        $board = $this->boardRepository->findById($column->boardId());
        if ($board === null) {
            throw new \LogicException('Board must exist for every column');
        }
        $this->guard->requireMember($board->projectId(), $actorId);

        $card = Card::create(
            Uuid::uuid4(),
            $columnId,
            $actorId,
            $command->title,
            $command->description,
            count($this->cardRepository->findByColumn($columnId)),
        );
        $this->cardRepository->save($card);
        $this->eventDispatcher->dispatch(new CardCreated($card->id(), $actorId));

        return $card;
    }
}
