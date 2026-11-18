<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Application;

use App\Board\Application\Message\Command\RemoveCardAttachmentCommand;
use App\Board\Application\MessageHandler\RemoveCardAttachmentCommandHandler;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Entity\Attachment;
use App\Board\Domain\Entity\Board;
use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Event\AttachmentRemoved;
use App\Board\Domain\Exception\AttachmentNotFoundException;
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
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class RemoveCardAttachmentCommandHandlerTest extends TestCase
{
    private CardRepository $cardRepository;
    private ColumnRepository $columnRepository;
    private BoardRepository $boardRepository;
    private ProjectMemberRepository $memberRepository;
    private UserRepository $userRepository;
    private AttachmentRepository $attachmentRepository;

    protected function setUp(): void
    {
        $this->cardRepository = $this->createMock(CardRepository::class);
        $this->columnRepository = $this->createMock(ColumnRepository::class);
        $this->boardRepository = $this->createMock(BoardRepository::class);
        $this->memberRepository = $this->createMock(ProjectMemberRepository::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->attachmentRepository = $this->createMock(AttachmentRepository::class);
    }

    private function handler(
        ?Card $card,
        ?Column $column,
        ?Board $board,
        ?ProjectMember $actorMembership,
        ?Attachment $attachment,
    ): RemoveCardAttachmentCommandHandler {
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

        return new RemoveCardAttachmentCommandHandler($guard, $this->attachmentRepository);
    }

    private function context(UuidInterface $actorId): array
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'Card', '', 0);
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);

        return [$card, $column, $board, $member];
    }

    private function attachment(Card $card, ?UuidInterface $commentId = null): Attachment
    {
        $attachment = Attachment::create(
            Uuid::uuid4(),
            $card->id(),
            $commentId,
            Uuid::uuid4(),
            'notes.txt',
            'attachments/'.$card->id()->toString().'/notes.txt',
            null,
            'text/plain',
            12,
        );
        $attachment->releaseEvents();

        return $attachment;
    }

    public function testAnyMemberRemovesACardAttachment(): void
    {
        $actorId = Uuid::uuid4();
        [$card, $column, $board, $member] = $this->context($actorId);
        $attachment = $this->attachment($card);

        $this->attachmentRepository->expects($this->once())->method('remove')->with($attachment);

        $this->handler($card, $column, $board, $member, $attachment)
            ->__invoke(new RemoveCardAttachmentCommand((string)$card->id(), (string)$attachment->id(), (string)$actorId));

        $events = $attachment->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(AttachmentRemoved::class, $events[0]);
        self::assertSame($actorId->toString(), $events[0]->actorId);
        self::assertNull($events[0]->commentId);
    }

    public function testThrowsWhenAttachmentIsACommentAttachment(): void
    {
        $actorId = Uuid::uuid4();
        [$card, $column, $board, $member] = $this->context($actorId);
        $attachment = $this->attachment($card, Uuid::uuid4());

        $this->attachmentRepository->expects($this->never())->method('remove');
        $this->expectException(AttachmentNotFoundException::class);

        $this->handler($card, $column, $board, $member, $attachment)
            ->__invoke(new RemoveCardAttachmentCommand((string)$card->id(), (string)$attachment->id(), (string)$actorId));
    }

    public function testThrowsWhenAttachmentBelongsToAnotherCard(): void
    {
        $actorId = Uuid::uuid4();
        [$card, $column, $board, $member] = $this->context($actorId);
        $otherCard = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'Other', '', 1);
        $attachment = $this->attachment($otherCard);

        $this->attachmentRepository->expects($this->never())->method('remove');
        $this->expectException(AttachmentNotFoundException::class);

        $this->handler($card, $column, $board, $member, $attachment)
            ->__invoke(new RemoveCardAttachmentCommand((string)$card->id(), (string)$attachment->id(), (string)$actorId));
    }

    public function testThrowsWhenAttachmentIsMissing(): void
    {
        $actorId = Uuid::uuid4();
        [$card, $column, $board, $member] = $this->context($actorId);

        $this->attachmentRepository->expects($this->never())->method('remove');
        $this->expectException(AttachmentNotFoundException::class);

        $this->handler($card, $column, $board, $member, null)
            ->__invoke(new RemoveCardAttachmentCommand((string)$card->id(), (string)Uuid::uuid4(), (string)$actorId));
    }

    public function testThrowsWhenCardMissing(): void
    {
        $this->expectException(CardNotFoundException::class);

        $this->handler(null, null, null, null, null)
            ->__invoke(new RemoveCardAttachmentCommand(
                (string)Uuid::uuid4(),
                (string)Uuid::uuid4(),
                (string)Uuid::uuid4(),
            ));
    }

    public function testThrowsWhenActorIsNotMember(): void
    {
        [$card, $column, $board] = $this->context(Uuid::uuid4());
        $actorId = Uuid::uuid4();
        $attachment = $this->attachment($card);

        $this->attachmentRepository->expects($this->never())->method('remove');
        $this->expectException(ProjectForbiddenException::class);

        $this->handler($card, $column, $board, null, $attachment)
            ->__invoke(new RemoveCardAttachmentCommand((string)$card->id(), (string)$attachment->id(), (string)$actorId));
    }
}
