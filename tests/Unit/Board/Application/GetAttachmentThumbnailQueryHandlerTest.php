<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Application;

use App\Board\Application\Message\Query\GetAttachmentThumbnailQuery;
use App\Board\Application\MessageHandler\GetAttachmentThumbnailQueryHandler;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Entity\Attachment;
use App\Board\Domain\Entity\Board;
use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Exception\AttachmentNotFoundException;
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
use App\Shared\Application\Storage\FileStorage;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class GetAttachmentThumbnailQueryHandlerTest extends TestCase
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
    }

    private function handler(
        ?Card $card,
        ?Column $column,
        ?Board $board,
        ?ProjectMember $actorMembership,
        ?Attachment $attachment,
    ): GetAttachmentThumbnailQueryHandler {
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

        return new GetAttachmentThumbnailQueryHandler($guard, $this->attachmentRepository, $this->storage);
    }

    private function context(): array
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'Card', '', 0);

        return [$project, $board, $column, $card];
    }

    private function image(Card $card, ?string $thumbnailKey): Attachment
    {
        return Attachment::create(
            Uuid::uuid4(),
            $card->id(),
            null,
            Uuid::uuid4(),
            'photo.jpg',
            'attachments/'.$card->id()->toString().'/photo.jpg',
            $thumbnailKey,
            'image/jpeg',
            1024,
            800,
            600,
        );
    }

    public function testReturnsThumbnailStream(): void
    {
        [$project, $board, $column, $card] = $this->context();
        $actorId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);
        $attachment = $this->image($card, 'attachments/'.$card->id()->toString().'/photo.webp');
        $stream = fopen('php://memory', 'r');
        self::assertIsResource($stream);

        $this->storage->expects($this->once())
            ->method('open')
            ->with($attachment->thumbnailStorageKey())
            ->willReturn($stream);

        $result = $this->handler($card, $column, $board, $member, $attachment)
            ->__invoke(new GetAttachmentThumbnailQuery((string)$attachment->id(), (string)$actorId));

        self::assertSame($stream, $result['stream']);
    }

    public function testThrowsWhenAttachmentHasNoThumbnail(): void
    {
        [$project, $board, $column, $card] = $this->context();
        $actorId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);
        $attachment = $this->image($card, null);

        $this->storage->expects($this->never())->method('open');
        $this->expectException(AttachmentNotFoundException::class);

        $this->handler($card, $column, $board, $member, $attachment)
            ->__invoke(new GetAttachmentThumbnailQuery((string)$attachment->id(), (string)$actorId));
    }

    public function testThrowsWhenAttachmentIsMissing(): void
    {
        [$project, $board, $column, $card] = $this->context();
        $actorId = Uuid::uuid4();
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);

        $this->storage->expects($this->never())->method('open');
        $this->expectException(AttachmentNotFoundException::class);

        $this->handler($card, $column, $board, $member, null)
            ->__invoke(new GetAttachmentThumbnailQuery((string)Uuid::uuid4(), (string)$actorId));
    }

    public function testThrowsWhenActorIsNotMember(): void
    {
        [, $board, $column, $card] = $this->context();
        $actorId = Uuid::uuid4();
        $attachment = $this->image($card, 'attachments/'.$card->id()->toString().'/photo.webp');

        $this->storage->expects($this->never())->method('open');
        $this->expectException(ProjectForbiddenException::class);

        $this->handler($card, $column, $board, null, $attachment)
            ->__invoke(new GetAttachmentThumbnailQuery((string)$attachment->id(), (string)$actorId));
    }
}
