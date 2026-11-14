<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Application;

use App\Board\Application\Message\Command\UpdateCommentCommand;
use App\Board\Application\MessageHandler\UpdateCommentCommandHandler;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Entity\Board;
use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Entity\Comment;
use App\Board\Domain\Event\CommentEdited;
use App\Board\Domain\Exception\CommentForbiddenException;
use App\Board\Domain\Exception\CommentNotFoundException;
use App\Board\Domain\Repository\BoardRepository;
use App\Board\Domain\Repository\CardRepository;
use App\Board\Domain\Repository\ColumnRepository;
use App\Board\Domain\Repository\CommentRepository;
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

final class UpdateCommentCommandHandlerTest extends TestCase
{
    private CardRepository $cardRepository;
    private ColumnRepository $columnRepository;
    private BoardRepository $boardRepository;
    private ProjectMemberRepository $memberRepository;
    private UserRepository $userRepository;
    private CommentRepository $commentRepository;

    protected function setUp(): void
    {
        $this->cardRepository = $this->createMock(CardRepository::class);
        $this->columnRepository = $this->createMock(ColumnRepository::class);
        $this->boardRepository = $this->createMock(BoardRepository::class);
        $this->memberRepository = $this->createMock(ProjectMemberRepository::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->commentRepository = $this->createMock(CommentRepository::class);
    }

    private function handler(
        ?Comment $comment,
        ?Card $card,
        ?Column $column,
        ?Board $board,
        ?ProjectMember $actorMembership,
    ): UpdateCommentCommandHandler {
        $this->commentRepository->method('findById')->willReturn($comment);
        $this->cardRepository->method('findById')->willReturn($card);
        $this->columnRepository->method('findById')->willReturn($column);
        $this->boardRepository->method('findById')->willReturn($board);
        $this->memberRepository->method('findByProjectAndUser')->willReturn($actorMembership);

        $guard = new CardGuard(
            $this->cardRepository,
            $this->columnRepository,
            $this->boardRepository,
            new MembershipGuard($this->memberRepository, $this->userRepository),
        );

        return new UpdateCommentCommandHandler($guard, $this->commentRepository);
    }

    private function context(UuidInterface $authorId): array
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'Card', '', 0);
        $comment = Comment::create(Uuid::uuid4(), $card->id(), $authorId, 'Original');
        $comment->releaseEvents();

        return [$comment, $card, $column, $board, $project];
    }

    public function testAuthorEditsOwnComment(): void
    {
        [$comment, $card, $column, $board, $project] = $this->context($authorId = Uuid::uuid4());
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $authorId, MemberRole::Member);

        $this->commentRepository->expects($this->once())->method('save');

        $result = $this->handler($comment, $card, $column, $board, $member)
            ->__invoke(new UpdateCommentCommand((string) $comment->id(), 'Edited', (string) $authorId));

        $events = $result->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CommentEdited::class, $events[0]);
        self::assertSame($card->id()->toString(), $events[0]->cardId);
        self::assertSame($comment->id()->toString(), $events[0]->commentId);
        self::assertSame('Edited', $result->body());
    }

    public function testUnchangedBodyDispatchesNoEvent(): void
    {
        [$comment, $card, $column, $board, $project] = $this->context($authorId = Uuid::uuid4());
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $authorId, MemberRole::Member);

        $this->commentRepository->expects($this->once())->method('save');

        $result = $this->handler($comment, $card, $column, $board, $member)
            ->__invoke(new UpdateCommentCommand((string) $comment->id(), 'Original', (string) $authorId));

        self::assertSame([], $result->releaseEvents());
        self::assertSame('Original', $result->body());
    }

    public function testThrowsWhenActorIsNotAuthor(): void
    {
        [$comment, $card, $column, $board, $project] = $this->context(Uuid::uuid4());
        $otherId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $otherId, MemberRole::Member);

        $this->commentRepository->expects($this->never())->method('save');
        $this->expectException(CommentForbiddenException::class);

        $this->handler($comment, $card, $column, $board, $member)
            ->__invoke(new UpdateCommentCommand((string) $comment->id(), 'x', (string) $otherId));
    }

    public function testThrowsWhenCommentMissing(): void
    {
        $this->expectException(CommentNotFoundException::class);

        $this->handler(null, null, null, null, null)
            ->__invoke(new UpdateCommentCommand((string) Uuid::uuid4(), 'x', (string) Uuid::uuid4()));
    }

    public function testThrowsWhenActorIsNotMember(): void
    {
        [$comment, $card, $column, $board] = $this->context($authorId = Uuid::uuid4());

        $this->commentRepository->expects($this->never())->method('save');
        $this->expectException(ProjectForbiddenException::class);

        $this->handler($comment, $card, $column, $board, null)
            ->__invoke(new UpdateCommentCommand((string) $comment->id(), 'x', (string) $authorId));
    }
}
