<?php

declare(strict_types=1);

namespace App\Tests\Integration\Board\Infrastructure;

use App\Board\Domain\Entity\Board;
use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Entity\Comment;
use App\Board\Domain\Repository\BoardRepository;
use App\Board\Domain\Repository\CardRepository;
use App\Board\Domain\Repository\ColumnRepository;
use App\Board\Domain\Repository\CommentRepository;
use App\Tests\Integration\DatabaseTestCase;
use Ramsey\Uuid\Uuid;

final class DoctrineCommentRepositoryTest extends DatabaseTestCase
{
    private BoardRepository $boards;
    private ColumnRepository $columns;
    private CardRepository $cards;
    private CommentRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->boards = $this->repo(BoardRepository::class);
        $this->columns = $this->repo(ColumnRepository::class);
        $this->cards = $this->repo(CardRepository::class);
        $this->repo = $this->repo(CommentRepository::class);
    }

    public function testFindByCardOrdersByCreationTimeAndEditPersists(): void
    {
        $board = Board::create(Uuid::uuid4(), Uuid::uuid4(), 'Board');
        $this->boards->save($board);
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $this->columns->save($column);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'Card', '', 0);
        $this->cards->save($card);

        $first = Comment::create(Uuid::uuid4(), $card->id(), Uuid::uuid4(), 'First');
        $this->repo->save($first);
        usleep(2000);
        $second = Comment::create(Uuid::uuid4(), $card->id(), Uuid::uuid4(), 'Second');
        $this->repo->save($second);
        $this->em()->clear();

        $comments = $this->repo->findByCard($card->id());
        self::assertSame(['First', 'Second'], array_map(
            static fn (Comment $c): string => $c->body(),
            $comments,
        ));

        $comments[1]->edit('Edited');
        $this->repo->save($comments[1]);
        $this->em()->clear();

        $comments = $this->repo->findByCard($card->id());
        self::assertSame(['First', 'Edited'], array_map(
            static fn (Comment $c): string => $c->body(),
            $comments,
        ));
    }

    public function testRemove(): void
    {
        $cardId = Uuid::uuid4();
        $comment = Comment::create(Uuid::uuid4(), $cardId, Uuid::uuid4(), 'Doomed');
        $this->repo->save($comment);
        $this->em()->clear();

        $loaded = $this->repo->findById($comment->id());
        $this->repo->remove($loaded);
        $this->em()->clear();

        self::assertNull($this->repo->findById($comment->id()));
        self::assertSame([], $this->repo->findByCard($cardId));
    }
}
