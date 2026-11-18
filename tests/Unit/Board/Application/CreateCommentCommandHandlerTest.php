<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Application;

use App\Board\Application\Message\Command\CreateCommentCommand;
use App\Board\Application\MessageHandler\CreateCommentCommandHandler;
use App\Board\Application\Service\AttachmentUploader;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Entity\Attachment;
use App\Board\Domain\Entity\Board;
use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Entity\Comment;
use App\Board\Domain\Event\CommentCreated;
use App\Board\Domain\Exception\AttachmentLimitException;
use App\Board\Domain\Exception\CardNotFoundException;
use App\Board\Domain\Repository\AttachmentRepository;
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
use App\Shared\Application\Media\ImageProcessor;
use App\Shared\Application\Media\MediaPolicy;
use App\Shared\Application\Storage\FileStorage;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class CreateCommentCommandHandlerTest extends TestCase
{
    private CardRepository $cardRepository;
    private ColumnRepository $columnRepository;
    private BoardRepository $boardRepository;
    private ProjectMemberRepository $memberRepository;
    private UserRepository $userRepository;
    private CommentRepository $commentRepository;
    private AttachmentRepository $attachmentRepository;
    private FileStorage $storage;
    private ImageProcessor $imageProcessor;

    protected function setUp(): void
    {
        $this->cardRepository = $this->createMock(CardRepository::class);
        $this->columnRepository = $this->createMock(ColumnRepository::class);
        $this->boardRepository = $this->createMock(BoardRepository::class);
        $this->memberRepository = $this->createMock(ProjectMemberRepository::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->commentRepository = $this->createMock(CommentRepository::class);
        $this->attachmentRepository = $this->createMock(AttachmentRepository::class);
        $this->storage = $this->createMock(FileStorage::class);
        $this->imageProcessor = $this->createMock(ImageProcessor::class);

        $this->attachmentRepository->method('usageByCard')->willReturn(['files' => 0, 'bytes' => 0]);
    }

    private function handler(?Card $card, ?Column $column, ?Board $board, ?ProjectMember $actorMembership): CreateCommentCommandHandler
    {
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

        return new CreateCommentCommandHandler(
            $guard,
            $this->commentRepository,
            $this->attachmentRepository,
            new AttachmentUploader(new MediaPolicy(), $this->storage, $this->imageProcessor),
        );
    }

    private function context(UuidInterface $actorId): array
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'Card', '', 0);

        return [$card, $column, $board, $project];
    }

    private function textFile(): UploadedFile
    {
        $file = $this->createMock(UploadedFile::class);
        $file->method('getMimeType')->willReturn('text/plain');
        $file->method('getPathname')->willReturn('/tmp/kanban-note.txt');
        $file->method('getSize')->willReturn(12);
        $file->method('getClientOriginalName')->willReturn('notes.txt');

        return $file;
    }

    public function testAnyMemberCanCommentOnAnyCard(): void
    {
        [$card, $column, $board, $project] = $this->context($actorId = Uuid::uuid4());
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);

        $this->commentRepository->expects($this->once())->method('save')->with($this->callback(
            static function (Comment $comment) use ($card, $actorId): bool {
                return $comment->cardId()->equals($card->id())
                    && $comment->authorId()->equals($actorId)
                    && $comment->body() === 'Hello **world**';
            },
        ));
        $result = $this->handler($card, $column, $board, $member)
            ->__invoke(new CreateCommentCommand(
                (string)$card->id(),
                'Hello **world**',
                actorId: (string)$actorId,
            ));

        $comment = $result['comment'];
        $events = $comment->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CommentCreated::class, $events[0]);
        self::assertSame($card->id()->toString(), $events[0]->cardId);
        self::assertSame($comment->id()->toString(), $events[0]->commentId);
        self::assertSame($actorId->toString(), $events[0]->authorId);
        self::assertSame('Hello **world**', $comment->body());
        self::assertTrue($comment->authorId()->equals($actorId));
        self::assertSame([], $result['attachments']);
    }

    public function testStoresUploadedFilesWithTheComment(): void
    {
        [$card, $column, $board, $project] = $this->context($actorId = Uuid::uuid4());
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);

        $this->storage->expects($this->once())
            ->method('writeFromFile')
            ->with(self::stringContains('attachments/' . $project->id()->toString() . '/' . $card->id()->toString() . '/'), '/tmp/kanban-note.txt');
        $this->attachmentRepository->expects($this->once())->method('save')->with($this->callback(
            static fn(Attachment $attachment): bool => $attachment->isCommentAttachment()
                && $attachment->originalName() === 'notes.txt'
                && $attachment->mimeType() === 'text/plain'
                && $attachment->uploaderId()->equals($actorId),
        ));

        $result = $this->handler($card, $column, $board, $member)
            ->__invoke(new CreateCommentCommand(
                (string)$card->id(),
                'with a file',
                [$this->textFile()],
                actorId: (string)$actorId,
            ));

        self::assertCount(1, $result['attachments']);
    }

    public function testRejectsWhenCardAttachmentLimitIsReached(): void
    {
        [$card, $column, $board, $project] = $this->context($actorId = Uuid::uuid4());
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);
        $this->attachmentRepository = $this->createMock(AttachmentRepository::class);
        $this->attachmentRepository->method('usageByCard')->willReturn([
            'files' => MediaPolicy::MAX_FILES_PER_CARD,
            'bytes' => 0,
        ]);

        $this->commentRepository->expects($this->never())->method('save');
        $this->storage->expects($this->never())->method('writeFromFile');
        $this->expectException(AttachmentLimitException::class);

        $this->handler($card, $column, $board, $member)
            ->__invoke(new CreateCommentCommand(
                (string)$card->id(),
                'x',
                [$this->textFile()],
                actorId: (string)$actorId,
            ));
    }

    public function testThrowsWhenCardMissing(): void
    {
        $this->expectException(CardNotFoundException::class);

        $this->handler(null, null, null, null)
            ->__invoke(new CreateCommentCommand((string)Uuid::uuid4(), 'x', actorId: (string)Uuid::uuid4()));
    }

    public function testThrowsWhenActorIsNotMember(): void
    {
        [$card, $column, $board] = $this->context($actorId = Uuid::uuid4());

        $this->commentRepository->expects($this->never())->method('save');
        $this->expectException(ProjectForbiddenException::class);

        $this->handler($card, $column, $board, null)
            ->__invoke(new CreateCommentCommand((string)$card->id(), 'x', actorId: (string)$actorId));
    }
}
