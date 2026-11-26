<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Application;

use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Entity\Board;
use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Entity\Comment;
use App\Board\Domain\Enum\ColumnKind;
use App\Board\Domain\Exception\CardForbiddenException;
use App\Board\Domain\Exception\CardKindMismatchException;
use App\Board\Domain\Exception\CardNotFoundException;
use App\Board\Domain\Exception\CommentForbiddenException;
use App\Board\Domain\Repository\BoardRepository;
use App\Board\Domain\Repository\CardRepository;
use App\Board\Domain\Repository\ColumnRepository;
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

final class CardGuardTest extends TestCase
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

    private function guard(
        ?Card $card,
        ?Column $column,
        ?Board $board,
        ?ProjectMember $actorMembership,
        ?User $actorUser = null,
    ): CardGuard {
        $this->cardRepository->method('findById')->willReturn($card);
        $this->columnRepository->method('findById')->willReturn($column);
        $this->boardRepository->method('findById')->willReturn($board);
        $this->memberRepository->method('findByProjectAndUser')->willReturn($actorMembership);
        $this->userRepository->method('findById')->willReturn($actorUser);

        return new CardGuard(
            $this->cardRepository,
            $this->columnRepository,
            $this->boardRepository,
            new MembershipGuard($this->memberRepository, $this->userRepository),
        );
    }

    private function globalAdmin(): User
    {
        $user = User::create(Uuid::uuid4(), 'admin@example.com', 'hash', 'Admin');
        $user->promoteToGlobalAdmin();

        return $user;
    }

    /**
     * @return array{Comment, Card, Column, Board, Project}
     */
    private function commentContext(UuidInterface $authorId): array
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'Card', '', 0);
        $comment = Comment::create(Uuid::uuid4(), $card->id(), $authorId, 'Body');

        return [$comment, $card, $column, $board, $project];
    }

    public function testRequireCardReturnsCardForMember(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $actorId = Uuid::uuid4();
        $card = Card::create(Uuid::uuid4(), $column->id(), $actorId, 'A', '', 0);
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);

        $result = $this->guard($card, $column, $board, $member)->requireCard($card->id(), $actorId);

        self::assertSame($card, $result);
    }

    public function testRequireCardThrowsWhenCardMissing(): void
    {
        $this->expectException(CardNotFoundException::class);

        $this->guard(null, null, null, null)->requireCard(Uuid::uuid4(), Uuid::uuid4());
    }

    public function testRequireCardThrowsWhenColumnMissing(): void
    {
        $columnId = Uuid::uuid4();
        $card = Card::create(Uuid::uuid4(), $columnId, Uuid::uuid4(), 'A', '', 0);

        $this->expectException(\LogicException::class);

        $this->guard($card, null, null, null)->requireCard($card->id(), Uuid::uuid4());
    }

    public function testRequireCardThrowsWhenActorIsNotMember(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'A', '', 0);

        $this->expectException(ProjectForbiddenException::class);

        $this->guard($card, $column, $board, null)->requireCard($card->id(), Uuid::uuid4());
    }

    public function testRequireCardEditableAllowsCreator(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $actorId = Uuid::uuid4();
        $card = Card::create(Uuid::uuid4(), $column->id(), $actorId, 'A', '', 0);
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);

        $result = $this->guard($card, $column, $board, $member)->requireCardEditable($card->id(), $actorId);

        self::assertSame($card, $result);
    }

    public function testRequireCardEditableThrowsForOtherMember(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'A', '', 0);
        $otherId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $otherId, MemberRole::Member);

        $this->expectException(CardForbiddenException::class);

        $this->guard($card, $column, $board, $member)->requireCardEditable($card->id(), $otherId);
    }

    public function testRequireCardEditableAllowsProjectAdmin(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'A', '', 0);
        $adminId = Uuid::uuid4();
        $admin = ProjectMember::create(Uuid::uuid4(), $project->id(), $adminId, MemberRole::Admin);

        $result = $this->guard($card, $column, $board, $admin)->requireCardEditable($card->id(), $adminId);

        self::assertSame($card, $result);
    }

    public function testRequireCardEditableAllowsGlobalAdmin(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'A', '', 0);
        $admin = $this->globalAdmin();

        $result = $this->guard($card, $column, $board, null, $admin)->requireCardEditable($card->id(), $admin->id());

        self::assertSame($card, $result);
    }

    public function testRequireCardEditableThrowsForNonMember(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $actorId = Uuid::uuid4();
        $card = Card::create(Uuid::uuid4(), $column->id(), $actorId, 'A', '', 0);

        $this->expectException(ProjectForbiddenException::class);

        $this->guard($card, $column, $board, null)->requireCardEditable($card->id(), $actorId);
    }

    public function testRequireCommentDeletableAllowsAuthor(): void
    {
        [$comment, $card, $column, $board, $project] = $this->commentContext($authorId = Uuid::uuid4());
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $authorId, MemberRole::Member);

        $this->expectNotToPerformAssertions();

        $this->guard($card, $column, $board, $member)->requireCommentDeletable($comment, $authorId);
    }

    public function testRequireCommentDeletableThrowsForOtherMember(): void
    {
        [$comment, $card, $column, $board, $project] = $this->commentContext(Uuid::uuid4());
        $otherId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $otherId, MemberRole::Member);

        $this->expectException(CommentForbiddenException::class);

        $this->guard($card, $column, $board, $member)->requireCommentDeletable($comment, $otherId);
    }

    public function testRequireCommentDeletableAllowsProjectAdmin(): void
    {
        [$comment, $card, $column, $board, $project] = $this->commentContext(Uuid::uuid4());
        $adminId = Uuid::uuid4();
        $admin = ProjectMember::create(Uuid::uuid4(), $project->id(), $adminId, MemberRole::Admin);

        $this->expectNotToPerformAssertions();

        $this->guard($card, $column, $board, $admin)->requireCommentDeletable($comment, $adminId);
    }

    public function testRequireCommentDeletableAllowsGlobalAdmin(): void
    {
        [$comment, $card, $column, $board] = $this->commentContext(Uuid::uuid4());
        $admin = $this->globalAdmin();

        $this->expectNotToPerformAssertions();

        $this->guard($card, $column, $board, null, $admin)->requireCommentDeletable($comment, $admin->id());
    }

    public function testRequireCommentDeletableThrowsForNonMember(): void
    {
        [$comment, $card, $column, $board] = $this->commentContext($authorId = Uuid::uuid4());

        $this->expectException(ProjectForbiddenException::class);

        $this->guard($card, $column, $board, null)->requireCommentDeletable($comment, $authorId);
    }

    public function testRequireCommentEditableAllowsAuthor(): void
    {
        [$comment, $card, $column, $board, $project] = $this->commentContext($authorId = Uuid::uuid4());
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $authorId, MemberRole::Member);

        $this->expectNotToPerformAssertions();

        $this->guard($card, $column, $board, $member)->requireCommentEditable($comment, $authorId);
    }

    public function testRequireCommentEditableThrowsForOtherMember(): void
    {
        [$comment, $card, $column, $board, $project] = $this->commentContext(Uuid::uuid4());
        $otherId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $otherId, MemberRole::Member);

        $this->expectException(CommentForbiddenException::class);

        $this->guard($card, $column, $board, $member)->requireCommentEditable($comment, $otherId);
    }

    public function testRequireCommentEditableThrowsForProjectAdmin(): void
    {
        [$comment, $card, $column, $board, $project] = $this->commentContext(Uuid::uuid4());
        $adminId = Uuid::uuid4();
        $admin = ProjectMember::create(Uuid::uuid4(), $project->id(), $adminId, MemberRole::Admin);

        $this->expectException(CommentForbiddenException::class);

        $this->guard($card, $column, $board, $admin)->requireCommentEditable($comment, $adminId);
    }

    public function testRequireCommentEditableThrowsForNonMember(): void
    {
        [$comment, $card, $column, $board] = $this->commentContext($authorId = Uuid::uuid4());

        $this->expectException(ProjectForbiddenException::class);

        $this->guard($card, $column, $board, null)->requireCommentEditable($comment, $authorId);
    }

    public function testResolveBoardReturnsBoardForCard(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'A', '', 0);

        $result = $this->guard($card, $column, $board, null)->resolveBoard($card);

        self::assertSame($board, $result);
    }

    public function testRequireCompletableAllowsMemberWhenCardHasNoExecutor(): void
    {
        $card = Card::create(Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4(), 'A', '', 0);

        $this->expectNotToPerformAssertions();

        $this->guard(null, null, null, null)->requireCompletable($card, Uuid::uuid4());
    }

    public function testRequireCompletableAllowsExecutor(): void
    {
        $executorId = Uuid::uuid4();
        $card = Card::create(Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4(), 'A', '', 0);
        $card->assignExecutor(User::create($executorId, 'executor@example.com', 'hash', 'Executor'));

        $this->expectNotToPerformAssertions();

        $this->guard(null, null, null, null)->requireCompletable($card, $executorId);
    }

    public function testRequireCompletableThrowsForNonExecutor(): void
    {
        $card = Card::create(Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4(), 'A', '', 0);
        $card->assignExecutor(User::create(Uuid::uuid4(), 'executor@example.com', 'hash', 'Executor'));

        $this->expectException(CardForbiddenException::class);

        $this->guard(null, null, null, null)->requireCompletable($card, Uuid::uuid4());
    }

    public function testRequireTaskCardPassesForTaskColumn(): void
    {
        $column = Column::create(Uuid::uuid4(), Uuid::uuid4(), 'To Do', 0);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'A', '', 0);

        $this->expectNotToPerformAssertions();

        $this->guard(null, $column, null, null)->requireTaskCard($card);
    }

    public function testRequireTaskCardThrowsForStickerColumn(): void
    {
        $column = Column::create(Uuid::uuid4(), Uuid::uuid4(), 'Stickers', 0, ColumnKind::Sticker);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'A', '', 0);

        $this->expectException(CardKindMismatchException::class);

        $this->guard(null, $column, null, null)->requireTaskCard($card);
    }

    public function testRequireSameColumnKindPassesWithinSameKind(): void
    {
        $source = Column::create(Uuid::uuid4(), Uuid::uuid4(), 'To Do', 0, ColumnKind::Sticker);
        $target = Column::create(Uuid::uuid4(), $source->boardId(), 'Done', 1, ColumnKind::Sticker);
        $card = Card::create(Uuid::uuid4(), $source->id(), Uuid::uuid4(), 'A', '', 0);

        $this->expectNotToPerformAssertions();

        $this->guard(null, $source, null, null)->requireSameColumnKind($card, $target);
    }

    public function testRequireSameColumnKindThrowsForCrossKindMove(): void
    {
        $source = Column::create(Uuid::uuid4(), Uuid::uuid4(), 'To Do', 0);
        $target = Column::create(Uuid::uuid4(), $source->boardId(), 'Stickers', 1, ColumnKind::Sticker);
        $card = Card::create(Uuid::uuid4(), $source->id(), Uuid::uuid4(), 'A', '', 0);

        $this->expectException(CardKindMismatchException::class);

        $this->guard(null, $source, null, null)->requireSameColumnKind($card, $target);
    }
}
