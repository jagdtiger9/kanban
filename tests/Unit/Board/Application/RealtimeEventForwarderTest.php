<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Application;

use App\Board\Application\MessageHandler\RealtimeEventForwarder;
use App\Board\Application\Service\BoardChannelResolver;
use App\Board\Application\Service\RealtimeNotifierInterface;
use App\Board\Domain\Entity\Board;
use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Event\CardArchived;
use App\Board\Domain\Event\CardAssigned;
use App\Board\Domain\Event\CardCreated;
use App\Board\Domain\Event\CardFieldChanged;
use App\Board\Domain\Event\CardLabelAdded;
use App\Board\Domain\Event\CardLabelRemoved;
use App\Board\Domain\Event\CardMoved;
use App\Board\Domain\Event\CardRestored;
use App\Board\Domain\Event\CardUnassigned;
use App\Board\Domain\Event\ColumnCreated;
use App\Board\Domain\Event\ColumnDeleted;
use App\Board\Domain\Event\ColumnRenamed;
use App\Board\Domain\Event\ColumnReordered;
use App\Board\Domain\Event\CommentCreated;
use App\Board\Domain\Event\CommentDeleted;
use App\Board\Domain\Event\CommentEdited;
use App\Board\Domain\Event\LabelCreated;
use App\Board\Domain\Event\LabelDeleted;
use App\Board\Domain\Event\LabelUpdated;
use App\Board\Domain\Repository\BoardRepository;
use App\Board\Domain\Repository\CardRepository;
use App\Board\Domain\Repository\ColumnRepository;
use App\Project\Domain\Enum\MemberRole;
use App\Project\Domain\Event\ProjectArchived;
use App\Project\Domain\Event\ProjectMemberRoleChanged;
use App\Project\Domain\Event\ProjectRestored;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class RealtimeEventForwarderTest extends TestCase
{
    private BoardRepository $boardRepository;
    private ColumnRepository $columnRepository;
    private CardRepository $cardRepository;
    private RealtimeNotifierInterface $notifier;

    protected function setUp(): void
    {
        $this->boardRepository = $this->createMock(BoardRepository::class);
        $this->columnRepository = $this->createMock(ColumnRepository::class);
        $this->cardRepository = $this->createMock(CardRepository::class);
        $this->notifier = $this->createMock(RealtimeNotifierInterface::class);
    }

    private function forwarder(): RealtimeEventForwarder
    {
        return new RealtimeEventForwarder(new BoardChannelResolver(
            $this->boardRepository,
            $this->columnRepository,
            $this->cardRepository,
        ), $this->notifier);
    }

    #[DataProvider('provideCardEvents')]
    public function testForwardsCardEvents(object $event, string $method, string $eventName): void
    {
        $projectId = $this->mapCardToProject($event->cardId);
        $actorId = $event->authorId ?? $event->actorId;

        $this->notifier->expects($this->once())->method('notify')
            ->with('project:' . $projectId, $eventName, $actorId);

        $this->forwarder()->{$method}($event);
    }

    #[DataProvider('provideBoardEvents')]
    public function testForwardsBoardEvents(object $event, string $method, string $eventName): void
    {
        $projectId = $this->mapBoardToProject($event->boardId);

        $this->notifier->expects($this->once())->method('notify')
            ->with('project:' . $projectId, $eventName, $event->actorId);

        $this->forwarder()->{$method}($event);
    }

    #[DataProvider('provideProjectEvents')]
    public function testForwardsProjectEvents(object $event, string $method, string $eventName): void
    {
        $this->notifier->expects($this->once())->method('notify')
            ->with('project:' . $event->projectId, $eventName, $event->actorId);

        $this->forwarder()->{$method}($event);
    }

    public function testSkipsNotificationWhenCardChannelCannotBeResolved(): void
    {
        $this->cardRepository->method('findById')->willReturn(null);
        $this->notifier->expects($this->never())->method('notify');

        $this->forwarder()->onCardCreated(new CardCreated(Uuid::uuid4()->toString(), Uuid::uuid4()->toString()));
    }

    public function testSkipsNotificationWhenBoardChannelCannotBeResolved(): void
    {
        $this->boardRepository->method('findById')->willReturn(null);
        $this->notifier->expects($this->never())->method('notify');

        $this->forwarder()->onColumnCreated(new ColumnCreated(
            Uuid::uuid4()->toString(),
            Uuid::uuid4()->toString(),
            Uuid::uuid4()->toString(),
        ));
    }

    private function mapCardToProject(string $cardId): string
    {
        $board = Board::create(Uuid::uuid4(), Uuid::uuid4(), 'Board');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);

        $this->cardRepository->method('findById')->willReturn(
            Card::create(Uuid::fromString($cardId), $column->id(), Uuid::uuid4(), 'Card', '', 0),
        );
        $this->columnRepository->method('findById')->willReturn($column);
        $this->boardRepository->method('findById')->willReturn($board);

        return $board->projectId()->toString();
    }

    private function mapBoardToProject(string $boardId): string
    {
        $board = Board::create(Uuid::fromString($boardId), Uuid::uuid4(), 'Board');
        $this->boardRepository->method('findById')->willReturn($board);

        return $board->projectId()->toString();
    }

    public static function provideCardEvents(): iterable
    {
        $cardId = Uuid::uuid4()->toString();
        $actorId = Uuid::uuid4()->toString();
        $userId = Uuid::uuid4()->toString();
        $labelId = Uuid::uuid4()->toString();
        $commentId = Uuid::uuid4()->toString();
        $columnId = Uuid::uuid4()->toString();

        yield 'card created' => [new CardCreated($cardId, $actorId), 'onCardCreated', 'card.created'];
        yield 'card field changed' => [new CardFieldChanged($cardId, 'title', 'a', 'b', $actorId), 'onCardFieldChanged', 'card.updated'];
        yield 'card moved' => [new CardMoved($cardId, $columnId, Uuid::uuid4()->toString(), 0, 0, $actorId), 'onCardMoved', 'card.moved'];
        yield 'card archived' => [new CardArchived($cardId, $actorId), 'onCardArchived', 'card.archived'];
        yield 'card restored' => [new CardRestored($cardId, $actorId), 'onCardRestored', 'card.restored'];
        yield 'card assigned' => [new CardAssigned($cardId, $userId, 'Alice', $actorId), 'onCardAssigned', 'card.assigned'];
        yield 'card unassigned' => [new CardUnassigned($cardId, $userId, 'Alice', $actorId), 'onCardUnassigned', 'card.unassigned'];
        yield 'card label added' => [new CardLabelAdded($cardId, $labelId, 'Bug', $actorId), 'onCardLabelAdded', 'card.label_added'];
        yield 'card label removed' => [new CardLabelRemoved($cardId, $labelId, 'Bug', $actorId), 'onCardLabelRemoved', 'card.label_removed'];
        yield 'comment created' => [new CommentCreated($cardId, $commentId, $userId), 'onCommentCreated', 'comment.created'];
        yield 'comment edited' => [new CommentEdited($cardId, $commentId, $userId), 'onCommentEdited', 'comment.updated'];
        yield 'comment deleted' => [new CommentDeleted($cardId, $commentId, $actorId), 'onCommentDeleted', 'comment.deleted'];
    }

    public static function provideBoardEvents(): iterable
    {
        $boardId = Uuid::uuid4()->toString();
        $actorId = Uuid::uuid4()->toString();
        $columnId = Uuid::uuid4()->toString();
        $labelId = Uuid::uuid4()->toString();

        yield 'column created' => [new ColumnCreated($boardId, $columnId, $actorId), 'onColumnCreated', 'column.created'];
        yield 'column renamed' => [new ColumnRenamed($boardId, $columnId, $actorId), 'onColumnRenamed', 'column.renamed'];
        yield 'column deleted' => [new ColumnDeleted($boardId, $columnId, $actorId), 'onColumnDeleted', 'column.deleted'];
        yield 'column reordered' => [new ColumnReordered($boardId, $columnId, $actorId), 'onColumnReordered', 'column.reordered'];
        yield 'label created' => [new LabelCreated($boardId, $labelId, $actorId), 'onLabelCreated', 'label.created'];
        yield 'label updated' => [new LabelUpdated($boardId, $labelId, $actorId), 'onLabelUpdated', 'label.updated'];
        yield 'label deleted' => [new LabelDeleted($boardId, $labelId, $actorId), 'onLabelDeleted', 'label.deleted'];
    }

    public static function provideProjectEvents(): iterable
    {
        $projectId = Uuid::uuid4()->toString();
        $actorId = Uuid::uuid4()->toString();

        yield 'project archived' => [new ProjectArchived($projectId, $actorId), 'onProjectArchived', 'project.archived'];
        yield 'project restored' => [new ProjectRestored($projectId, $actorId), 'onProjectRestored', 'project.restored'];
        yield 'project member role changed' => [
            new ProjectMemberRoleChanged($projectId, Uuid::uuid4()->toString(), MemberRole::Admin, $actorId),
            'onProjectMemberRoleChanged',
            'project.member_role_changed',
        ];
    }
}
