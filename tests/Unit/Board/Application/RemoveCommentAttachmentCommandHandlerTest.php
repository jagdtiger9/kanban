<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Application;

use App\Board\Application\Message\Command\RemoveCommentAttachmentCommand;
use App\Board\Application\MessageHandler\RemoveCommentAttachmentCommandHandler;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Entity\Attachment;
use App\Board\Domain\Entity\Board;
use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Entity\Comment;
use App\Board\Domain\Event\AttachmentRemoved;
use App\Board\Domain\Exception\AttachmentForbiddenException;
use App\Board\Domain\Exception\AttachmentNotFoundException;
use App\Board\Domain\Exception\CommentNotFoundException;
use App\Board\Domain\Repository\AttachmentRepository;
use App\Board\Domain\Repository\BoardRepository;
use App\Board\Domain\Repository\CardRepository;
use App\Board\Domain\Repository\ColumnRepository;
use App\Board\Domain\Repository\CommentRepository;
use App\Board\Domain\Service\AttachmentPolicy;
use App\Project\Application\Service\MembershipGuard;
use App\Project\Domain\Entity\Project;
use App\Project\Domain\Entity\ProjectMember;
use App\Project\Domain\Enum\MemberRole;
use App\Project\Domain\Exception\ProjectForbiddenException;
use App\Project\Domain\Repository\ProjectMemberRepository;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class RemoveCommentAttachmentCommandHandlerTest extends TestCase
{
    private CardRepository $cardRepository;
    private ColumnRepository $columnRepository;
    private BoardRepository $boardRepository;
    private ProjectMemberRepository $memberRepository;
    private UserRepository $userRepository;
    private CommentRepository $commentRepository;
    private AttachmentRepository $attachmentRepository;

    protected function setUp(): void
    {
        $this->cardRepository = $this->createMock(CardRepository::class);
        $this->columnRepository = $this->createMock(ColumnRepository::class);
        $this->boardRepository = $this->createMock(BoardRepository::class);
        $this->memberRepository = $this->createMock(ProjectMemberRepository::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->commentRepository = $this->createMock(CommentRepository::class);
        $this->attachmentRepository = $this->createMock(AttachmentRepository::class);
    }

    private function handler(
        ?Comment $comment,
        Card $card,
        Column $column,
        Board $board,
        ?ProjectMember $actorMembership,
        ?Attachment $attachment,
    ): RemoveCommentAttachmentCommandHandler {
        $this->commentRepository->method('findById')->willReturn($comment);
        $this->cardRepository->method('findById')->willReturn($card);
        $this->columnRepository->method('findById')->willReturn($column);
        $this->boardRepository->method('findById')->willReturn($board);
        $this->memberRepository->method('findByProjectAndUser')->willReturn($actorMembership);
        $this->attachmentRepository->method('findById')->willReturn($attachment);

        $guard = new CardGuard(
            $this->cardRepository,
            $this->columnRepository,
            $this->boardRepository,
            new MembershipGuard($this->memberRepository, $this->userRepository),
        );

        return new RemoveCommentAttachmentCommandHandler(
            $guard,
            $this->commentRepository,
            $this->attachmentRepository,
            new AttachmentPolicy(),
        );
    }

    private function context(): array
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'Card', '', 0);
        $comment = Comment::create(Uuid::uuid4(), $card->id(), Uuid::uuid4(), 'Body');

        return [$project, $board, $column, $card, $comment];
    }

    private function attachment(Card $card, Comment $comment): Attachment
    {
        $attachment = Attachment::create(
            Uuid::uuid4(),
            $card->id(),
            $comment->id(),
            $comment->authorId(),
            'notes.txt',
            'attachments/'.$card->id()->toString().'/notes.txt',
            null,
            'text/plain',
            12,
        );
        $attachment->releaseEvents();

        return $attachment;
    }

    public function testCommentAuthorRemovesOwnAttachment(): void
    {
        [$project, $board, $column, $card, $comment] = $this->context();
        $authorId = $comment->authorId();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $authorId, MemberRole::Member);
        $attachment = $this->attachment($card, $comment);

        $this->attachmentRepository->expects($this->once())->method('remove')->with($attachment);

        $this->handler($comment, $card, $column, $board, $member, $attachment)
            ->__invoke(new RemoveCommentAttachmentCommand(
                (string)$comment->id(),
                (string)$attachment->id(),
                (string)$authorId,
            ));

        $events = $attachment->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(AttachmentRemoved::class, $events[0]);
        self::assertSame($comment->id()->toString(), $events[0]->commentId);
        self::assertSame($authorId->toString(), $events[0]->actorId);
    }

    public function testProjectAdminRemovesAnotherMembersAttachment(): void
    {
        [$project, $board, $column, $card, $comment] = $this->context();
        $adminId = Uuid::uuid4();
        $admin = ProjectMember::create(Uuid::uuid4(), $project->id(), $adminId, MemberRole::Admin);
        $attachment = $this->attachment($card, $comment);

        $this->attachmentRepository->expects($this->once())->method('remove')->with($attachment);

        $this->handler($comment, $card, $column, $board, $admin, $attachment)
            ->__invoke(new RemoveCommentAttachmentCommand(
                (string)$comment->id(),
                (string)$attachment->id(),
                (string)$adminId,
            ));
    }

    public function testOtherMemberCannotRemoveTheAttachment(): void
    {
        [$project, $board, $column, $card, $comment] = $this->context();
        $otherId = Uuid::uuid4();
        $other = ProjectMember::create(Uuid::uuid4(), $project->id(), $otherId, MemberRole::Member);
        $attachment = $this->attachment($card, $comment);

        $this->attachmentRepository->expects($this->never())->method('remove');
        $this->expectException(AttachmentForbiddenException::class);

        $this->handler($comment, $card, $column, $board, $other, $attachment)
            ->__invoke(new RemoveCommentAttachmentCommand(
                (string)$comment->id(),
                (string)$attachment->id(),
                (string)$otherId,
            ));
    }

    public function testThrowsWhenAttachmentBelongsToAnotherComment(): void
    {
        [$project, $board, $column, $card, $comment] = $this->context();
        $authorId = $comment->authorId();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $authorId, MemberRole::Member);
        $otherComment = Comment::create(Uuid::uuid4(), $card->id(), $authorId, 'Other');
        $attachment = $this->attachment($card, $otherComment);

        $this->attachmentRepository->expects($this->never())->method('remove');
        $this->expectException(AttachmentNotFoundException::class);

        $this->handler($comment, $card, $column, $board, $member, $attachment)
            ->__invoke(new RemoveCommentAttachmentCommand(
                (string)$comment->id(),
                (string)$attachment->id(),
                (string)$authorId,
            ));
    }

    public function testThrowsWhenAttachmentIsACardAttachment(): void
    {
        [$project, $board, $column, $card, $comment] = $this->context();
        $authorId = $comment->authorId();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $authorId, MemberRole::Member);
        $cardAttachment = Attachment::create(
            Uuid::uuid4(),
            $card->id(),
            null,
            $authorId,
            'notes.txt',
            'attachments/'.$card->id()->toString().'/other.txt',
            null,
            'text/plain',
            12,
        );

        $this->attachmentRepository->expects($this->never())->method('remove');
        $this->expectException(AttachmentNotFoundException::class);

        $this->handler($comment, $card, $column, $board, $member, $cardAttachment)
            ->__invoke(new RemoveCommentAttachmentCommand(
                (string)$comment->id(),
                (string)$cardAttachment->id(),
                (string)$authorId,
            ));
    }

    public function testThrowsWhenCommentIsMissing(): void
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'Card', '', 0);

        $this->expectException(CommentNotFoundException::class);

        $this->handler(null, $card, $column, $board, null, null)
            ->__invoke(new RemoveCommentAttachmentCommand(
                (string)Uuid::uuid4(),
                (string)Uuid::uuid4(),
                (string)Uuid::uuid4(),
            ));
    }

    public function testThrowsWhenActorIsNotMember(): void
    {
        [, $board, $column, $card, $comment] = $this->context();
        $actorId = Uuid::uuid4();
        $attachment = $this->attachment($card, $comment);

        $this->attachmentRepository->expects($this->never())->method('remove');
        $this->expectException(ProjectForbiddenException::class);

        $this->handler($comment, $card, $column, $board, null, $attachment)
            ->__invoke(new RemoveCommentAttachmentCommand(
                (string)$comment->id(),
                (string)$attachment->id(),
                (string)$actorId,
            ));
    }
}
