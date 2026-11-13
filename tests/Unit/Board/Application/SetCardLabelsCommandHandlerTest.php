<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Application;

use App\Board\Application\Message\Command\SetCardLabelsCommand;
use App\Board\Application\MessageHandler\SetCardLabelsCommandHandler;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Entity\Board;
use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Entity\Label;
use App\Board\Domain\Event\CardLabelAdded;
use App\Board\Domain\Event\CardLabelRemoved;
use App\Board\Domain\Exception\CardNotFoundException;
use App\Board\Domain\Exception\LabelNotFoundException;
use App\Board\Domain\Repository\BoardRepository;
use App\Board\Domain\Repository\CardRepository;
use App\Board\Domain\Repository\ColumnRepository;
use App\Board\Domain\Repository\LabelRepository;
use App\Project\Application\Service\MembershipGuard;
use App\Project\Domain\Entity\Project;
use App\Project\Domain\Entity\ProjectMember;
use App\Project\Domain\Enum\MemberRole;
use App\Project\Domain\Repository\ProjectMemberRepository;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class SetCardLabelsCommandHandlerTest extends TestCase
{
    private CardRepository $cardRepository;
    private ColumnRepository $columnRepository;
    private BoardRepository $boardRepository;
    private ProjectMemberRepository $memberRepository;
    private UserRepository $userRepository;
    private LabelRepository $labelRepository;
    private EventDispatcherInterface $eventDispatcher;

    protected function setUp(): void
    {
        $this->cardRepository = $this->createMock(CardRepository::class);
        $this->columnRepository = $this->createMock(ColumnRepository::class);
        $this->boardRepository = $this->createMock(BoardRepository::class);
        $this->memberRepository = $this->createMock(ProjectMemberRepository::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->labelRepository = $this->createMock(LabelRepository::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
    }

    private function handler(
        ?Card $card,
        ?Column $column,
        ?Board $board,
        ?ProjectMember $actorMembership,
        array $labelsById,
    ): SetCardLabelsCommandHandler {
        $this->cardRepository->method('findById')->willReturn($card);
        $this->columnRepository->method('findById')->willReturn($column);
        $this->boardRepository->method('findById')->willReturn($board);
        $this->memberRepository->method('findByProjectAndUser')->willReturn($actorMembership);
        $this->labelRepository->method('findById')->willReturnCallback(
            static fn (UuidInterface $id): ?Label => $labelsById[(string) $id] ?? null,
        );

        $guard = new CardGuard(
            $this->cardRepository,
            $this->columnRepository,
            $this->boardRepository,
            new MembershipGuard($this->memberRepository, $this->userRepository),
        );

        return new SetCardLabelsCommandHandler($guard, $this->labelRepository, $this->cardRepository, $this->eventDispatcher);
    }

    public function testReplacesLabelSet(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $actorId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'A', '', 0);

        $oldLabel = Label::create(Uuid::uuid4(), $board->id(), 'Old', '#ff0000');
        $newLabel = Label::create(Uuid::uuid4(), $board->id(), 'New', '#00ff00');
        $card->addLabel($oldLabel);

        $this->cardRepository->expects($this->once())->method('save');
        $dispatched = [];
        $this->eventDispatcher->expects($this->exactly(2))->method('dispatch')
            ->willReturnCallback(static function (object $event) use (&$dispatched): object {
                $dispatched[] = $event;

                return $event;
            });

        $this->handler($card, $column, $board, $member, [(string) $newLabel->id() => $newLabel])
            ->__invoke(new SetCardLabelsCommand((string) $card->id(), [(string) $newLabel->id()], (string) $actorId));

        self::assertCount(2, $dispatched);
        $removed = $dispatched[0];
        self::assertInstanceOf(CardLabelRemoved::class, $removed);
        self::assertSame('Old', $removed->labelName);
        $added = $dispatched[1];
        self::assertInstanceOf(CardLabelAdded::class, $added);
        self::assertSame('New', $added->labelName);
        self::assertFalse($card->labels()->contains($oldLabel));
        self::assertTrue($card->labels()->contains($newLabel));
    }

    public function testKeepsLabelStillSelected(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $actorId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'A', '', 0);

        $label = Label::create(Uuid::uuid4(), $board->id(), 'Keep', '#0000ff');
        $card->addLabel($label);

        $this->cardRepository->expects($this->once())->method('save');
        $this->eventDispatcher->expects($this->never())->method('dispatch');

        $this->handler($card, $column, $board, $member, [(string) $label->id() => $label])
            ->__invoke(new SetCardLabelsCommand((string) $card->id(), [(string) $label->id()], (string) $actorId));

        self::assertTrue($card->labels()->contains($label));
    }

    public function testNonCreatorMemberCanSetLabels(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $actorId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'A', '', 0);

        $label = Label::create(Uuid::uuid4(), $board->id(), 'L', '#0000ff');

        $this->cardRepository->expects($this->once())->method('save');
        $this->eventDispatcher->expects($this->once())->method('dispatch')
            ->with($this->isInstanceOf(CardLabelAdded::class))->willReturnArgument(0);

        $this->handler($card, $column, $board, $member, [(string) $label->id() => $label])
            ->__invoke(new SetCardLabelsCommand((string) $card->id(), [(string) $label->id()], (string) $actorId));

        self::assertTrue($card->labels()->contains($label));
    }

    public function testThrowsWhenLabelMissing(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $actorId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'A', '', 0);

        $this->eventDispatcher->expects($this->never())->method('dispatch');
        $this->expectException(LabelNotFoundException::class);

        $this->handler($card, $column, $board, $member, [])
            ->__invoke(new SetCardLabelsCommand((string) $card->id(), [(string) Uuid::uuid4()], (string) $actorId));
    }

    public function testThrowsWhenLabelBelongsToAnotherBoard(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $otherBoard = Board::create(Uuid::uuid4(), $project->id(), 'Other');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $actorId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'A', '', 0);

        $foreign = Label::create(Uuid::uuid4(), $otherBoard->id(), 'Foreign', '#0000ff');

        $this->eventDispatcher->expects($this->never())->method('dispatch');
        $this->expectException(LabelNotFoundException::class);

        $this->handler($card, $column, $board, $member, [(string) $foreign->id() => $foreign])
            ->__invoke(new SetCardLabelsCommand((string) $card->id(), [(string) $foreign->id()], (string) $actorId));
    }

    public function testThrowsWhenCardMissing(): void
    {
        $this->eventDispatcher->expects($this->never())->method('dispatch');
        $this->expectException(CardNotFoundException::class);

        $this->handler(null, null, null, null, [])
            ->__invoke(new SetCardLabelsCommand((string) Uuid::uuid4(), [], (string) Uuid::uuid4()));
    }
}
