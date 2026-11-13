<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Application;

use App\Board\Application\Message\Command\CreateCommentCommand;
use App\Board\Application\MessageHandler\CreateCommentCommandHandler;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Entity\Board;
use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Entity\Comment;
use App\Board\Domain\Event\CommentCreated;
use App\Board\Domain\Exception\CardNotFoundException;
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
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class CreateCommentCommandHandlerTest extends TestCase
{
    private CardRepository $cardRepository;
    private ColumnRepository $columnRepository;
    private BoardRepository $boardRepository;
    private ProjectMemberRepository $memberRepository;
    private UserRepository $userRepository;
    private CommentRepository $commentRepository;
    private EventDispatcherInterface $eventDispatcher;

    protected function setUp(): void
    {
        $this->cardRepository = $this->createMock(CardRepository::class);
        $this->columnRepository = $this->createMock(ColumnRepository::class);
        $this->boardRepository = $this->createMock(BoardRepository::class);
        $this->memberRepository = $this->createMock(ProjectMemberRepository::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->commentRepository = $this->createMock(CommentRepository::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
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

        return new CreateCommentCommandHandler($guard, $this->commentRepository, $this->eventDispatcher);
    }

    private function context(UuidInterface $actorId): array
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $card = Card::create(Uuid::uuid4(), $column->id(), Uuid::uuid4(), 'Card', '', 0);

        return [$card, $column, $board, $project];
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
        $this->eventDispatcher->expects($this->once())->method('dispatch')->with($this->callback(
            static function (CommentCreated $event) use ($card, $actorId): bool {
                return $event->cardId->equals($card->id()) && $event->authorId->equals($actorId);
            },
        ))->willReturnArgument(0);

        $result = $this->handler($card, $column, $board, $member)
            ->__invoke(new CreateCommentCommand((string) $card->id(), 'Hello **world**', (string) $actorId));

        self::assertSame('Hello **world**', $result->body());
        self::assertTrue($result->authorId()->equals($actorId));
    }

    public function testThrowsWhenCardMissing(): void
    {
        $this->expectException(CardNotFoundException::class);

        $this->handler(null, null, null, null)
            ->__invoke(new CreateCommentCommand((string) Uuid::uuid4(), 'x', (string) Uuid::uuid4()));
    }

    public function testThrowsWhenActorIsNotMember(): void
    {
        [$card, $column, $board] = $this->context($actorId = Uuid::uuid4());

        $this->commentRepository->expects($this->never())->method('save');
        $this->expectException(ProjectForbiddenException::class);

        $this->handler($card, $column, $board, null)
            ->__invoke(new CreateCommentCommand((string) $card->id(), 'x', (string) $actorId));
    }
}
