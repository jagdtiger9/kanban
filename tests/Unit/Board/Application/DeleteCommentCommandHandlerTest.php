<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Application;

use App\Board\Application\Message\Command\DeleteCommentCommand;
use App\Board\Application\MessageHandler\DeleteCommentCommandHandler;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Entity\Board;
use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Entity\Comment;
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
use App\User\Domain\Entity\User;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class DeleteCommentCommandHandlerTest extends TestCase
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
        ?User $actorUser = null,
    ): DeleteCommentCommandHandler {
        $this->commentRepository->method('findById')->willReturn($comment);
        $this->cardRepository->method('findById')->willReturn($card);
        $this->columnRepository->method('findById')->willReturn($column);
        $this->boardRepository->method('findById')->willReturn($board);
        $this->memberRepository->method('findByProjectAndUser')->willReturn($actorMembership);
        $this->userRepository->method('findById')->willReturn($actorUser);

        $guard = new CardGuard(
            $this->cardRepository,
            $this->columnRepository,
            $this->boardRepository,
            new MembershipGuard($this->memberRepository, $this->userRepository),
        );

        return new DeleteCommentCommandHandler($guard, $this->commentRepository);
    }

    private function context(UuidInterface $authorId): array
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'Card', '', 0);
        $comment = Comment::create(Uuid::uuid4(), $card->id(), $authorId, 'Body');

        return [$comment, $card, $column, $board, $project];
    }

    public function testAuthorDeletesOwnComment(): void
    {
        [$comment, $card, $column, $board, $project] = $this->context($authorId = Uuid::uuid4());
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $authorId, MemberRole::Member);

        $this->commentRepository->expects($this->once())->method('remove')->with($comment);

        $this->handler($comment, $card, $column, $board, $member)
            ->__invoke(new DeleteCommentCommand((string) $comment->id(), (string) $authorId));
    }

    public function testThrowsWhenActorIsNotAuthor(): void
    {
        [$comment, $card, $column, $board, $project] = $this->context(Uuid::uuid4());
        $otherId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $otherId, MemberRole::Member);

        $this->commentRepository->expects($this->never())->method('remove');
        $this->expectException(CommentForbiddenException::class);

        $this->handler($comment, $card, $column, $board, $member)
            ->__invoke(new DeleteCommentCommand((string) $comment->id(), (string) $otherId));
    }

    public function testThrowsWhenCommentMissing(): void
    {
        $this->expectException(CommentNotFoundException::class);

        $this->handler(null, null, null, null, null)
            ->__invoke(new DeleteCommentCommand((string) Uuid::uuid4(), (string) Uuid::uuid4()));
    }

    public function testThrowsWhenActorIsNotMember(): void
    {
        [$comment, $card, $column, $board] = $this->context($authorId = Uuid::uuid4());

        $this->commentRepository->expects($this->never())->method('remove');
        $this->expectException(ProjectForbiddenException::class);

        $this->handler($comment, $card, $column, $board, null)
            ->__invoke(new DeleteCommentCommand((string) $comment->id(), (string) $authorId));
    }

    public function testProjectAdminDeletesOtherMembersComment(): void
    {
        [$comment, $card, $column, $board, $project] = $this->context(Uuid::uuid4());
        $adminId = Uuid::uuid4();
        $admin = ProjectMember::create(Uuid::uuid4(), $project->id(), $adminId, MemberRole::Admin);

        $this->commentRepository->expects($this->once())->method('remove')->with($comment);

        $this->handler($comment, $card, $column, $board, $admin)
            ->__invoke(new DeleteCommentCommand((string) $comment->id(), (string) $adminId));
    }

    public function testGlobalAdminDeletesCommentWithoutMembership(): void
    {
        [$comment, $card, $column, $board] = $this->context(Uuid::uuid4());
        $adminUser = User::create(Uuid::uuid4(), 'admin@example.com', 'hash', 'Admin');
        $adminUser->promoteToGlobalAdmin();

        $this->commentRepository->expects($this->once())->method('remove')->with($comment);

        $this->handler($comment, $card, $column, $board, null, $adminUser)
            ->__invoke(new DeleteCommentCommand((string) $comment->id(), (string) $adminUser->id()));
    }
}
