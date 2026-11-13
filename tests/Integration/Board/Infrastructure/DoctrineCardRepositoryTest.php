<?php

declare(strict_types=1);

namespace App\Tests\Integration\Board\Infrastructure;

use App\Board\Domain\Entity\Board;
use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Entity\Label;
use App\Board\Domain\Repository\BoardRepository;
use App\Board\Domain\Repository\CardRepository;
use App\Board\Domain\Repository\ColumnRepository;
use App\Board\Domain\Repository\LabelRepository;
use App\Tests\Integration\DatabaseTestCase;
use App\User\Domain\Entity\User;
use App\User\Domain\Repository\UserRepository;
use Ramsey\Uuid\Uuid;

final class DoctrineCardRepositoryTest extends DatabaseTestCase
{
    private BoardRepository $boards;
    private ColumnRepository $columns;
    private CardRepository $cards;
    private LabelRepository $labels;
    private UserRepository $users;

    protected function setUp(): void
    {
        parent::setUp();
        $this->boards = $this->repo(BoardRepository::class);
        $this->columns = $this->repo(ColumnRepository::class);
        $this->cards = $this->repo(CardRepository::class);
        $this->labels = $this->repo(LabelRepository::class);
        $this->users = $this->repo(UserRepository::class);
    }

    public function testFindByColumnOrdersByPosition(): void
    {
        $column = $this->column();

        $c2 = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'Third', '', 2);
        $c0 = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'First', '', 0);
        $c1 = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'Second', '', 1);
        foreach ([$c2, $c0, $c1] as $card) {
            $this->cards->save($card);
        }
        $this->em()->clear();

        $cards = $this->cards->findByColumn($column->id());

        self::assertSame(['First', 'Second', 'Third'], array_map(
            static fn (Card $c): string => $c->title(),
            $cards,
        ));
    }

    public function testCardRelationsPersistThroughJoinTables(): void
    {
        $column = $this->column();
        $board = $this->boards->findById($column->boardId());
        $label = Label::create(Uuid::uuid4(), $board->id(), 'Bug', '#ff0000');
        $this->labels->save($label);
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');
        $this->users->save($user);

        $card = Card::create(Uuid::uuid4(), $column->id(), $user->id(), 'Card', '', 0, new \DateTimeImmutable('2026-12-01'));
        $card->addLabel($label);
        $card->assign($user);
        $this->cards->save($card);
        $this->em()->clear();

        $reloaded = $this->cards->findById($card->id());

        self::assertNotNull($reloaded);
        self::assertCount(1, $reloaded->labels());
        self::assertTrue($reloaded->labels()->first()->id()->equals($label->id()));
        self::assertCount(1, $reloaded->assignees());
        self::assertTrue($reloaded->assignees()->first()->id()->equals($user->id()));
        self::assertSame('2026-12-01', $reloaded->dueDate()->format('Y-m-d'));
    }

    public function testCountByColumnCountsOnlyThatColumnsCards(): void
    {
        $column = $this->column();
        $otherColumn = Column::create(Uuid::uuid4(), $column->boardId(), 'Done', 1);
        $this->columns->save($otherColumn);

        $this->cards->save(Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'A', '', 0));
        $this->cards->save(Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'B', '', 1));
        $this->cards->save(Card::create(Uuid::uuid4(), $otherColumn->id(), Uuid::uuid4(), 'C', '', 0));
        $this->em()->clear();

        self::assertSame(2, $this->cards->countByColumn($column->id()));
        self::assertSame(1, $this->cards->countByColumn($otherColumn->id()));
        self::assertSame(0, $this->cards->countByColumn(Uuid::uuid4()));
    }

    private function column(): Column
    {
        $board = Board::create(Uuid::uuid4(), Uuid::uuid4(), 'Board');
        $this->boards->save($board);
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $this->columns->save($column);

        return $column;
    }
}
