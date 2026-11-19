<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Application;

use App\Board\Application\Message\Query\GetCardQuery;
use App\Board\Application\MessageHandler\GetCardQueryHandler;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Entity\Attachment;
use App\Board\Domain\Entity\Board;
use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\CardActivity;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Entity\Comment;
use App\Board\Domain\Exception\CardNotFoundException;
use App\Board\Domain\Repository\AttachmentRepository;
use App\Board\Domain\Repository\BoardRepository;
use App\Board\Domain\Repository\CardActivityRepository;
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

final class GetCardQueryHandlerTest extends TestCase
{
    private CardRepository $cardRepository;
    private ColumnRepository $columnRepository;
    private BoardRepository $boardRepository;
    private ProjectMemberRepository $memberRepository;
    private UserRepository $userRepository;
    private CommentRepository $commentRepository;
    private AttachmentRepository $attachmentRepository;
    private CardActivityRepository $activityRepository;

    protected function setUp(): void
    {
        $this->cardRepository = $this->createMock(CardRepository::class);
        $this->columnRepository = $this->createMock(ColumnRepository::class);
        $this->boardRepository = $this->createMock(BoardRepository::class);
        $this->memberRepository = $this->createMock(ProjectMemberRepository::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->commentRepository = $this->createMock(CommentRepository::class);
        $this->attachmentRepository = $this->createMock(AttachmentRepository::class);
        $this->activityRepository = $this->createMock(CardActivityRepository::class);
    }

    /**
     * @param list<Comment> $comments
     * @param list<Attachment> $attachments
     * @param list<CardActivity> $activities
     */
    private function handler(
        ?Card $card,
        ?Column $column,
        ?Board $board,
        ?ProjectMember $actorMembership,
        array $comments,
        array $activities,
        array $attachments = [],
    ): GetCardQueryHandler {
        $this->cardRepository->method('findById')->willReturn($card);
        $this->columnRepository->method('findById')->willReturn($column);
        $this->boardRepository->method('findById')->willReturn($board);
        $this->memberRepository->method('findByProjectAndUser')->willReturn($actorMembership);
        $this->commentRepository->method('findByCard')->willReturn($comments);
        $this->attachmentRepository->method('findByCard')->willReturn($attachments);
        $this->activityRepository->method('findByCard')->willReturn($activities);

        $guard = new CardGuard(
            $this->cardRepository,
            $this->columnRepository,
            $this->boardRepository,
            new MembershipGuard($this->memberRepository, $this->userRepository),
        );

        return new GetCardQueryHandler(
            $guard,
            $this->commentRepository,
            $this->attachmentRepository,
            $this->activityRepository,
        );
    }

    /**
     * @return array{Card, Column, Board, Project}
     */
    private function context(UuidInterface $actorId): array
    {
        $project = Project::create(Uuid::uuid4(), 'P');
        $board = Board::create(Uuid::uuid4(), $project->id(), 'P');
        $column = Column::create(Uuid::uuid4(), $board->id(), 'To Do', 0);
        $card = Card::create(Uuid::uuid4(), $column->id(), $actorId, 'Card', '', 0);

        return [$card, $column, $board, $project];
    }

    public function testReturnsCardWithCommentsAndActivities(): void
    {
        [$card, $column, $board, $project] = $this->context($actorId = Uuid::uuid4());
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);
        $comments = [Comment::create(Uuid::uuid4(), $card->id(), $actorId, 'First')];
        $attachments = [Attachment::create(Uuid::uuid4(), $card->id(), null, $actorId, 'notes.txt', 'attachments/a/notes.txt', null, 'text/plain', 5)];
        $activities = [CardActivity::create(Uuid::uuid4(), $card->id(), $actorId, 'card_created', [])];

        $result = $this->handler($card, $column, $board, $member, $comments, $activities, $attachments)
            ->__invoke(new GetCardQuery((string) $card->id(), (string) $actorId));

        self::assertSame($card, $result['card']);
        self::assertSame($comments, $result['comments']);
        self::assertSame($attachments, $result['attachments']);
        self::assertSame($activities, $result['activities']);
        self::assertCount(2, $result['timeline']);
        self::assertTrue(in_array($comments[0], $result['timeline'], true));
        self::assertTrue(in_array($activities[0], $result['timeline'], true));
    }

    public function testTimelineOrdersNewestFirstAndSkipsCommentEvents(): void
    {
        [$card, $column, $board, $project] = $this->context($actorId = Uuid::uuid4());
        $member = ProjectMember::create(Uuid::uuid4(), $project->id(), $actorId, MemberRole::Member);

        $firstComment = Comment::create(Uuid::uuid4(), $card->id(), $actorId, 'First');
        usleep(2000);

        $created = CardActivity::create(Uuid::uuid4(), $card->id(), $actorId, 'card_created', []);
        usleep(2000);

        $commentEvent = CardActivity::create(
            Uuid::uuid4(),
            $card->id(),
            $actorId,
            'comment_added',
            ['commentId' => (string) $firstComment->id()],
        );
        usleep(2000);

        $secondComment = Comment::create(Uuid::uuid4(), $card->id(), $actorId, 'Second');
        usleep(2000);

        $moved = CardActivity::create(Uuid::uuid4(), $card->id(), $actorId, 'card_moved', []);

        $result = $this->handler(
            $card,
            $column,
            $board,
            $member,
            [$firstComment, $secondComment],
            [$created, $commentEvent, $moved],
        )->__invoke(new GetCardQuery((string) $card->id(), (string) $actorId));

        self::assertSame([$moved, $secondComment, $created, $firstComment], $result['timeline']);
    }

    public function testThrowsWhenCardMissing(): void
    {
        $this->expectException(CardNotFoundException::class);

        $this->handler(null, null, null, null, [], [])
            ->__invoke(new GetCardQuery((string) Uuid::uuid4(), (string) Uuid::uuid4()));
    }

    public function testThrowsWhenActorIsNotMember(): void
    {
        [$card, $column, $board] = $this->context($actorId = Uuid::uuid4());

        $this->expectException(ProjectForbiddenException::class);

        $this->handler($card, $column, $board, null, [], [])
            ->__invoke(new GetCardQuery((string) $card->id(), (string) $actorId));
    }
}
