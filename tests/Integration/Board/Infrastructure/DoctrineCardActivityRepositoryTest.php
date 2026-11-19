<?php

declare(strict_types=1);

namespace App\Tests\Integration\Board\Infrastructure;

use App\Board\Domain\Entity\Board;
use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\CardActivity;
use App\Board\Domain\Entity\CardView;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Entity\ProjectView;
use App\Board\Domain\Repository\BoardRepository;
use App\Board\Domain\Repository\CardActivityRepository;
use App\Board\Domain\Repository\CardRepository;
use App\Board\Domain\Repository\CardViewRepository;
use App\Board\Domain\Repository\ColumnRepository;
use App\Board\Domain\Repository\ProjectViewRepository;
use App\Tests\Integration\DatabaseTestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class DoctrineCardActivityRepositoryTest extends DatabaseTestCase
{
    private CardActivityRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = $this->repo(CardActivityRepository::class);
    }

    public function testSaveAndFindByCardWithJsonPayloadRoundtrip(): void
    {
        $cardId = Uuid::uuid4();
        $activity = CardActivity::create(
            Uuid::uuid4(),
            $cardId,
            Uuid::uuid4(),
            'card.moved',
            ['old_column' => 'todo', 'new_column' => 'done', 'position' => 3],
        );
        $this->repo->save($activity);
        $this->em()->clear();

        $activities = $this->repo->findByCard($cardId);

        self::assertCount(1, $activities);
        self::assertSame('card.moved', $activities[0]->type());
        self::assertSame(
            ['old_column' => 'todo', 'new_column' => 'done', 'position' => 3],
            $activities[0]->payload(),
        );
        self::assertTrue($activities[0]->actorId()->equals($activity->actorId()));
    }

    public function testFindByCardReturnsEmptyForUnknownCard(): void
    {
        self::assertSame([], $this->repo->findByCard(Uuid::uuid4()));
    }

    public function testCountUnseenPerCardRespectsBaselineAndCardViews(): void
    {
        $board = $this->seedBoard();
        $column = $this->seedColumn($board);
        $cardA = $this->seedCard($column);
        $cardB = $this->seedCard($column);
        $actorId = Uuid::uuid4();
        $otherId = Uuid::uuid4();
        $baselineAt = new \DateTimeImmutable('-2 hours');

        $this->seedProjectView($actorId, $board->projectId(), $baselineAt);
        $this->seedCardView($actorId, $cardA->id(), new \DateTimeImmutable('-1 hour'));

        $this->seedActivity($cardA->id(), $otherId, new \DateTimeImmutable('-3 hours'));
        $this->seedActivity($cardA->id(), $otherId, new \DateTimeImmutable('-90 minutes'));
        $this->seedActivity($cardA->id(), $otherId, new \DateTimeImmutable('-30 minutes'));
        $this->seedActivity($cardB->id(), $otherId, new \DateTimeImmutable('-30 minutes'));
        $this->seedActivity($cardB->id(), $actorId, new \DateTimeImmutable('-10 minutes'));
        $this->em()->clear();

        $counts = $this->repo->countUnseenByBoard($board->id(), $actorId, $baselineAt);

        self::assertCount(2, $counts);
        self::assertSame(1, $counts[(string) $cardA->id()]);
        self::assertSame(1, $counts[(string) $cardB->id()]);
    }

    public function testBaselineFloorWinsOverOlderCardView(): void
    {
        $board = $this->seedBoard();
        $column = $this->seedColumn($board);
        $card = $this->seedCard($column);
        $actorId = Uuid::uuid4();
        $otherId = Uuid::uuid4();
        $baselineAt = new \DateTimeImmutable('-1 hour');

        $this->seedProjectView($actorId, $board->projectId(), $baselineAt);
        $this->seedCardView($actorId, $card->id(), new \DateTimeImmutable('-2 hours'));

        $this->seedActivity($card->id(), $otherId, new \DateTimeImmutable('-90 minutes'));
        $this->seedActivity($card->id(), $otherId, new \DateTimeImmutable('-30 minutes'));
        $this->em()->clear();

        $counts = $this->repo->countUnseenByBoard($board->id(), $actorId, $baselineAt);

        self::assertSame([(string) $card->id() => 1], $counts);
    }

    public function testCountUnseenIgnoresOtherBoards(): void
    {
        $board = $this->seedBoard();
        $otherBoard = $this->seedBoard();
        $column = $this->seedColumn($board);
        $otherColumn = $this->seedColumn($otherBoard);
        $card = $this->seedCard($column);
        $otherCard = $this->seedCard($otherColumn);
        $actorId = Uuid::uuid4();
        $baselineAt = new \DateTimeImmutable('-1 hour');

        $this->seedProjectView($actorId, $board->projectId(), $baselineAt);
        $this->seedActivity($card->id(), Uuid::uuid4(), new \DateTimeImmutable('-30 minutes'));
        $this->seedActivity($otherCard->id(), Uuid::uuid4(), new \DateTimeImmutable('-30 minutes'));
        $this->em()->clear();

        $counts = $this->repo->countUnseenByBoard($board->id(), $actorId, $baselineAt);

        self::assertSame([(string) $card->id() => 1], $counts);
    }

    public function testCountUnseenReturnsEmptyForBoardWithoutActivities(): void
    {
        $board = $this->seedBoard();

        self::assertSame(
            [],
            $this->repo->countUnseenByBoard($board->id(), Uuid::uuid4(), new \DateTimeImmutable('-1 hour')),
        );
    }

    private function seedBoard(): Board
    {
        $board = Board::create(Uuid::uuid4(), Uuid::uuid4(), 'Board');
        $this->repo(BoardRepository::class)->save($board);

        return $board;
    }

    private function seedColumn(Board $board): Column
    {
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $this->repo(ColumnRepository::class)->save($column);

        return $column;
    }

    private function seedCard(Column $column): Card
    {
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'Card', '', 0);
        $this->repo(CardRepository::class)->save($card);

        return $card;
    }

    private function seedProjectView(UuidInterface $userId, UuidInterface $projectId, \DateTimeImmutable $firstSeenAt): void
    {
        $view = ProjectView::create(Uuid::uuid4(), $userId, $projectId);
        $this->repo(ProjectViewRepository::class)->save($view);
        $this->setTimestamp('project_views', 'first_seen_at', $view->id(), $firstSeenAt);
    }

    private function seedCardView(UuidInterface $userId, UuidInterface $cardId, \DateTimeImmutable $lastSeenAt): void
    {
        $view = CardView::create(Uuid::uuid4(), $userId, $cardId);
        $this->repo(CardViewRepository::class)->save($view);
        $this->setTimestamp('card_views', 'last_seen_at', $view->id(), $lastSeenAt);
    }

    private function seedActivity(UuidInterface $cardId, UuidInterface $actorId, \DateTimeImmutable $createdAt): void
    {
        $activity = CardActivity::create(Uuid::uuid4(), $cardId, $actorId, 'card.updated', []);
        $this->repo->save($activity);
        $this->setTimestamp('card_activities', 'created_at', $activity->id(), $createdAt);
    }

    private function setTimestamp(string $table, string $column, UuidInterface $id, \DateTimeImmutable $at): void
    {
        $this->em()->getConnection()->executeStatement(
            sprintf('UPDATE %s SET %s = :at WHERE id = :id', $table, $column),
            ['at' => $at->format('Y-m-d H:i:s.uP'), 'id' => $id->toString()],
        );
    }
}
