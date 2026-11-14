<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Application;

use App\Board\Application\Service\BoardChannelResolver;
use App\Board\Domain\Entity\Board;
use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Repository\BoardRepository;
use App\Board\Domain\Repository\CardRepository;
use App\Board\Domain\Repository\ColumnRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class BoardChannelResolverTest extends TestCase
{
    private BoardRepository $boardRepository;
    private ColumnRepository $columnRepository;
    private CardRepository $cardRepository;

    protected function setUp(): void
    {
        $this->boardRepository = $this->createMock(BoardRepository::class);
        $this->columnRepository = $this->createMock(ColumnRepository::class);
        $this->cardRepository = $this->createMock(CardRepository::class);
    }

    private function resolver(): BoardChannelResolver
    {
        return new BoardChannelResolver(
            $this->boardRepository,
            $this->columnRepository,
            $this->cardRepository,
        );
    }

    public function testProjectChannelIsPrefixedProjectId(): void
    {
        $projectId = Uuid::uuid4()->toString();

        self::assertSame('project:' . $projectId, $this->resolver()->forProject($projectId));
    }

    public function testResolvesCardChannelThroughColumnAndBoard(): void
    {
        [$projectId, , , $card] = $this->context();

        self::assertSame('project:' . $projectId, $this->resolver()->forCard($card->id()->toString()));
    }

    public function testResolvesBoardChannelDirectly(): void
    {
        [$projectId, $board] = $this->context();

        self::assertSame('project:' . $projectId, $this->resolver()->forBoard($board->id()->toString()));
    }

    public function testReturnsNullWhenCardMissing(): void
    {
        $this->cardRepository->method('findById')->willReturn(null);

        self::assertNull($this->resolver()->forCard(Uuid::uuid4()->toString()));
    }

    public function testReturnsNullWhenCardColumnMissing(): void
    {
        $card = Card::create(Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4(), 'Card', '', 0);
        $this->cardRepository->method('findById')->willReturn($card);
        $this->columnRepository->method('findById')->willReturn(null);

        self::assertNull($this->resolver()->forCard($card->id()->toString()));
    }

    public function testReturnsNullWhenBoardMissingForCard(): void
    {
        $board = Board::create(Uuid::uuid4(), Uuid::uuid4(), 'Board');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'Card', '', 0);
        $this->cardRepository->method('findById')->willReturn($card);
        $this->columnRepository->method('findById')->willReturn($column);

        self::assertNull($this->resolver()->forCard($card->id()->toString()));
    }

    public function testReturnsNullWhenBoardMissing(): void
    {
        $this->boardRepository->method('findById')->willReturn(null);

        self::assertNull($this->resolver()->forBoard(Uuid::uuid4()->toString()));
    }

    private function context(): array
    {
        $projectId = Uuid::uuid4();
        $board = Board::create(Uuid::uuid4(), $projectId, 'Board');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'Card', '', 0);

        $this->boardRepository->method('findById')->willReturn($board);
        $this->columnRepository->method('findById')->willReturn($column);
        $this->cardRepository->method('findById')->willReturn($card);

        return [$projectId, $board, $column, $card];
    }
}
