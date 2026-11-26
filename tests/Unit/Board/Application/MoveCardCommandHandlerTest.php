<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Application;

use App\Board\Application\Message\Command\MoveCardCommand;
use App\Board\Application\MessageHandler\MoveCardCommandHandler;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Entity\Board;
use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Enum\ColumnKind;
use App\Board\Domain\Event\CardMoved;
use App\Board\Domain\Exception\CardKindMismatchException;
use App\Board\Domain\Exception\CardNotFoundException;
use App\Board\Domain\Exception\ColumnNotFoundException;
use App\Board\Domain\Repository\BoardRepository;
use App\Board\Domain\Repository\CardRepository;
use App\Board\Domain\Repository\ColumnRepository;
use App\Board\Domain\Service\CardMoveService;
use App\Project\Application\Service\MembershipGuard;
use App\Project\Domain\Entity\Project;
use App\Project\Domain\Entity\ProjectMember;
use App\Project\Domain\Enum\MemberRole;
use App\Project\Domain\Exception\ProjectForbiddenException;
use App\Project\Domain\Repository\ProjectMemberRepository;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class MoveCardCommandHandlerTest extends TestCase
{
    private CardRepository $cardRepository;
    private ColumnRepository $columnRepository;
    private BoardRepository $boardRepository;
    private ProjectMemberRepository $memberRepository;
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        $this->cardRepository = $this->createMock(CardRepository::class);
        $this->columnRepository = $this->createMock(ColumnRepository::class);
        $this->boardRepository = $this->createMock(BoardRepository::class);
        $this->memberRepository = $this->createMock(ProjectMemberRepository::class);
        $this->userRepository = $this->createMock(UserRepository::class);
    }

    /**
     * @param list<Card> $oldColumnCards
     * @param list<Card> $newColumnCards
     */
    private function handler(
        ?Card $card,
        Column $sourceColumn,
        Board $board,
        ?ProjectMember $actorMembership,
        ?Column $targetColumn,
        array $oldColumnCards,
        array $newColumnCards,
    ): MoveCardCommandHandler {
        $this->cardRepository->method('findById')->willReturn($card);
        $this->boardRepository->method('findById')->willReturn($board);
        $this->memberRepository->method('findByProjectAndUser')->willReturn($actorMembership);
        $this->columnRepository->method('findById')->willReturnCallback(
            static function (UuidInterface $id) use ($sourceColumn, $targetColumn): ?Column {
                if ($sourceColumn->id()->equals($id)) {
                    return $sourceColumn;
                }
                if ($targetColumn !== null && $targetColumn->id()->equals($id)) {
                    return $targetColumn;
                }

                return null;
            },
        );
        $this->cardRepository->method('findByColumn')->willReturnCallback(
            static function (UuidInterface $columnId) use ($sourceColumn, $targetColumn, $oldColumnCards, $newColumnCards): array {
                if ($sourceColumn->id()->equals($columnId)) {
                    return $oldColumnCards;
                }
                if ($targetColumn !== null && $targetColumn->id()->equals($columnId)) {
                    return $newColumnCards;
                }

                return [];
            },
        );

        $guard = new CardGuard(
            $this->cardRepository,
            $this->columnRepository,
            $this->boardRepository,
            new MembershipGuard($this->memberRepository, $this->userRepository),
        );

        return new MoveCardCommandHandler($guard, $this->columnRepository, $this->cardRepository, new CardMoveService());
    }

    public function testMovesCardAcrossColumnsAndRenumbersBoth(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $source = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $target = Column::create(Uuid::uuid4(), $board->id(), 'Done', 1);
        $actorId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);

        $moved = Card::create(Uuid::uuid4(), $source->id(), $actorId, 'A', '', 0);
        $b = Card::create(Uuid::uuid4(), $source->id(), $actorId, 'B', '', 1);
        $c = Card::create(Uuid::uuid4(), $target->id(), $actorId, 'C', '', 0);

        $this->cardRepository->expects($this->exactly(3))->method('save');

        $this->handler($moved, $source, $board, $member, $target, [$moved, $b], [$c])
            ->__invoke(new MoveCardCommand((string)$moved->id(), (string)$target->id(), 0, (string)$actorId));

        $events = array_values(
            array_filter(
                $moved->releaseEvents(),
                static fn(object $event): bool => $event instanceof CardMoved,
            )
        );
        self::assertCount(1, $events);
        self::assertSame($actorId->toString(), $events[0]->actorId);
        self::assertSame($source->id()->toString(), $events[0]->oldColumnId);
        self::assertSame($target->id()->toString(), $events[0]->newColumnId);
        self::assertTrue($moved->columnId()->equals($target->id()));
        self::assertSame(0, $moved->position());
        self::assertSame(1, $c->position());
        self::assertSame(0, $b->position());
    }

    public function testReordersWithinSameColumn(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $source = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $actorId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);

        $moved = Card::create(Uuid::uuid4(), $source->id(), $actorId, 'A', '', 0);
        $b = Card::create(Uuid::uuid4(), $source->id(), $actorId, 'B', '', 1);
        $c = Card::create(Uuid::uuid4(), $source->id(), $actorId, 'C', '', 2);

        $this->cardRepository->expects($this->exactly(3))->method('save');

        $this->handler($moved, $source, $board, $member, $source, [$moved, $b, $c], [$moved, $b, $c])
            ->__invoke(new MoveCardCommand((string)$moved->id(), (string)$source->id(), 1, (string)$actorId));

        $events = array_values(
            array_filter(
                $moved->releaseEvents(),
                static fn(object $event): bool => $event instanceof CardMoved,
            )
        );
        self::assertCount(1, $events);
        self::assertSame(1, $events[0]->newPosition);
        self::assertSame(0, $b->position());
        self::assertSame(1, $moved->position());
        self::assertSame(2, $c->position());
    }

    public function testThrowsWhenTargetColumnMissing(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $source = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $actorId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);
        $card = Card::create(Uuid::uuid4(), $source->id(), $actorId, 'A', '', 0);

        $this->expectException(ColumnNotFoundException::class);

        $this->handler($card, $source, $board, $member, null, [$card], [])
            ->__invoke(new MoveCardCommand((string)$card->id(), (string)Uuid::uuid4(), 0, (string)$actorId));
    }

    public function testThrowsWhenTargetColumnBelongsToAnotherBoard(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $otherBoard = Board::create(Uuid::uuid4(), $project->id(), 'Other');
        $source = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $foreign = Column::create(Uuid::uuid4(), $otherBoard->id(), 'Foreign', 0);
        $actorId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);
        $card = Card::create(Uuid::uuid4(), $source->id(), $actorId, 'A', '', 0);

        $this->cardRepository->expects($this->never())->method('save');
        $this->expectException(ColumnNotFoundException::class);

        $this->handler($card, $source, $board, $member, $foreign, [$card], [])
            ->__invoke(new MoveCardCommand((string)$card->id(), (string)$foreign->id(), 0, (string)$actorId));
    }

    public function testThrowsWhenActorIsNotMember(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $source = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $card = Card::create(Uuid::uuid4(), $source->id(), Uuid::uuid4(), 'A', '', 0);

        $this->expectException(ProjectForbiddenException::class);

        $this->handler($card, $source, $board, null, $source, [$card], [$card])
            ->__invoke(new MoveCardCommand((string)$card->id(), (string)$source->id(), 0, (string)Uuid::uuid4()));
    }

    public function testThrowsWhenCardMissing(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $source = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);

        $this->expectException(CardNotFoundException::class);

        $this->handler(null, $source, $board, null, $source, [], [])
            ->__invoke(new MoveCardCommand((string)Uuid::uuid4(), (string)$source->id(), 0, (string)Uuid::uuid4()));
    }

    public function testThrowsWhenMovingBetweenStickerAndTaskColumns(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $source = Column::create(Uuid::uuid4(), $board->id(), 'Stickers', 0, ColumnKind::Sticker);
        $target = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 1);
        $actorId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);
        $card = Card::create(Uuid::uuid4(), $source->id(), $actorId, 'A', '', 0);

        $this->cardRepository->expects($this->never())->method('save');
        $this->expectException(CardKindMismatchException::class);

        $this->handler($card, $source, $board, $member, $target, [$card], [])
            ->__invoke(new MoveCardCommand((string)$card->id(), (string)$target->id(), 0, (string)$actorId));
    }
}
