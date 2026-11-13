<?php

declare(strict_types=1);

namespace App\Board\Application\MessageHandler;

use App\Board\Application\Message\Query\GetBoardQuery;
use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Entity\Label;
use App\Board\Domain\Repository\BoardRepository;
use App\Board\Domain\Repository\CardRepository;
use App\Board\Domain\Repository\ColumnRepository;
use App\Board\Domain\Repository\LabelRepository;
use App\Project\Application\Service\MembershipGuard;
use App\Project\Domain\Exception\ProjectNotFoundException;
use App\Project\Domain\Repository\ProjectRepository;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetBoardQueryHandler
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private BoardRepository $boardRepository,
        private ColumnRepository $columnRepository,
        private CardRepository $cardRepository,
        private LabelRepository $labelRepository,
        private MembershipGuard $guard,
    ) {
    }

    /**
     * @return array{boardId: UuidInterface, columns: list<Column>, cards: list<Card>, labels: list<Label>}
     */
    public function __invoke(GetBoardQuery $query): array
    {
        $projectId = Uuid::fromString($query->projectId);
        $actorId = Uuid::fromString($query->actorId);

        $project = $this->projectRepository->findById($projectId);
        if ($project === null) {
            throw new ProjectNotFoundException('Project not found');
        }
        $this->guard->requireMember($projectId, $actorId);

        $board = $this->boardRepository->findByProject($projectId);
        if ($board === null) {
            throw new \LogicException('Board must exist for every project');
        }

        $columns = $this->columnRepository->findByBoard($board->id());

        $cards = [];
        foreach ($columns as $column) {
            foreach ($this->cardRepository->findByColumn($column->id()) as $card) {
                if ($query->includeArchived || !$card->isArchived()) {
                    $cards[] = $card;
                }
            }
        }

        return [
            'boardId' => $board->id(),
            'columns' => $columns,
            'cards' => $cards,
            'labels' => $this->labelRepository->findByBoard($board->id()),
        ];
    }
}
