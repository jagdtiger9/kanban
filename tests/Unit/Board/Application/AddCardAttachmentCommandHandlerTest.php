<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Application;

use App\Board\Application\Message\Command\AddCardAttachmentCommand;
use App\Board\Application\MessageHandler\AddCardAttachmentCommandHandler;
use App\Board\Application\Service\AttachmentUploader;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Entity\Attachment;
use App\Board\Domain\Entity\Board;
use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Exception\AttachmentLimitException;
use App\Board\Domain\Exception\CardNotFoundException;
use App\Board\Domain\Repository\AttachmentRepository;
use App\Board\Domain\Repository\BoardRepository;
use App\Board\Domain\Repository\CardRepository;
use App\Board\Domain\Repository\ColumnRepository;
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

final class AddCardAttachmentCommandHandlerTest extends TestCase
{
    private CardRepository $cardRepository;
    private ColumnRepository $columnRepository;
    private BoardRepository $boardRepository;
    private ProjectMemberRepository $memberRepository;
    private UserRepository $userRepository;
    private AttachmentRepository $attachmentRepository;
    private FileStorage $storage;

    protected function setUp(): void
    {
        $this->cardRepository = $this->createMock(CardRepository::class);
        $this->columnRepository = $this->createMock(ColumnRepository::class);
        $this->boardRepository = $this->createMock(BoardRepository::class);
        $this->memberRepository = $this->createMock(ProjectMemberRepository::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->attachmentRepository = $this->createMock(AttachmentRepository::class);
        $this->storage = $this->createMock(FileStorage::class);

        $this->attachmentRepository->method('usageByCard')->willReturn(['files' => 0, 'bytes' => 0]);
    }

    private function handler(?Card $card, ?Column $column, ?Board $board, ?ProjectMember $actorMembership): AddCardAttachmentCommandHandler
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

        return new AddCardAttachmentCommandHandler(
            $guard,
            $this->attachmentRepository,
            new AttachmentUploader(new MediaPolicy(), $this->storage, $this->createMock(ImageProcessor::class)),
        );
    }

    private function context(): array
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'Card', '', 0);

        return [$card, $column, $board, $project];
    }

    private function textFile(int $size = 12): UploadedFile
    {
        $file = $this->createMock(UploadedFile::class);
        $file->method('getMimeType')->willReturn('text/plain');
        $file->method('getPathname')->willReturn('/tmp/kanban-notes.txt');
        $file->method('getSize')->willReturn($size);
        $file->method('getClientOriginalName')->willReturn('notes.txt');

        return $file;
    }

    public function testAnyMemberCanAttachAFileToACard(): void
    {
        [$card, $column, $board, $project] = $this->context();
        $actorId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);

        $this->storage->expects($this->once())
            ->method('writeFromFile')
            ->with(self::stringContains('attachments/'.$project->id()->toString().'/'.$card->id()->toString().'/'), '/tmp/kanban-notes.txt');
        $this->attachmentRepository->expects($this->once())->method('save')->with($this->callback(
            static fn(Attachment $attachment): bool => $attachment->cardId()->equals($card->id())
                && $attachment->commentId() === null
                && $attachment->uploaderId()->equals($actorId),
        ));

        $attachment = $this->handler($card, $column, $board, $member)
            ->__invoke(new AddCardAttachmentCommand(
                (string)$card->id(),
                $this->textFile(),
                (string)$actorId,
            ));

        self::assertTrue($attachment->cardId()->equals($card->id()));
        self::assertSame('notes.txt', $attachment->originalName());
        self::assertSame('text/plain', $attachment->mimeType());
        self::assertSame(12, $attachment->sizeBytes());
    }

    public function testRejectsWhenFileCountLimitIsReached(): void
    {
        [$card, $column, $board, $project] = $this->context();
        $actorId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);
        $this->attachmentRepository = $this->createMock(AttachmentRepository::class);
        $this->attachmentRepository->method('usageByCard')->willReturn([
            'files' => MediaPolicy::MAX_FILES_PER_CARD,
            'bytes' => 0,
        ]);

        $this->storage->expects($this->never())->method('writeFromFile');
        $this->expectException(AttachmentLimitException::class);

        $this->handler($card, $column, $board, $member)
            ->__invoke(new AddCardAttachmentCommand((string)$card->id(), $this->textFile(), (string)$actorId));
    }

    public function testRejectsWhenByteLimitIsReached(): void
    {
        [$card, $column, $board, $project] = $this->context();
        $actorId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);
        $this->attachmentRepository = $this->createMock(AttachmentRepository::class);
        $this->attachmentRepository->method('usageByCard')->willReturn([
            'files' => 0,
            'bytes' => MediaPolicy::MAX_BYTES_PER_CARD - 1,
        ]);

        $this->storage->expects($this->never())->method('writeFromFile');
        $this->expectException(AttachmentLimitException::class);

        $this->handler($card, $column, $board, $member)
            ->__invoke(new AddCardAttachmentCommand((string)$card->id(), $this->textFile(2), (string)$actorId));
    }

    public function testThrowsWhenCardMissing(): void
    {
        $this->expectException(CardNotFoundException::class);

        $this->handler(null, null, null, null)
            ->__invoke(new AddCardAttachmentCommand((string)Uuid::uuid4(), $this->textFile(), (string)Uuid::uuid4()));
    }

    public function testThrowsWhenActorIsNotMember(): void
    {
        [$card, $column, $board] = $this->context();
        $actorId = Uuid::uuid4();

        $this->storage->expects($this->never())->method('writeFromFile');
        $this->expectException(ProjectForbiddenException::class);

        $this->handler($card, $column, $board, null)
            ->__invoke(new AddCardAttachmentCommand((string)$card->id(), $this->textFile(), (string)$actorId));
    }
}
