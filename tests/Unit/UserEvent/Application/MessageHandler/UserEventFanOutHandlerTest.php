<?php

declare(strict_types=1);

namespace App\Tests\Unit\UserEvent\Application\MessageHandler;

use App\Board\Domain\Event\CardAssigned;
use App\Board\Domain\Event\CardCreated;
use App\Board\Domain\Event\CardUnassigned;
use App\Board\Domain\Event\CommentCreated;
use App\Project\Domain\Enum\MemberRole;
use App\Project\Domain\Event\ProjectArchived;
use App\Project\Domain\Event\ProjectMemberRoleChanged;
use App\Project\Domain\Event\ProjectRestored;
use App\UserEvent\Application\Dto\CardEventContext;
use App\UserEvent\Application\MessageHandler\UserEventFanOutHandler;
use App\UserEvent\Application\Port\UserEventContextProvider;
use App\UserEvent\Application\Port\UserEventNotifierInterface;
use App\UserEvent\Domain\Entity\UserEvent;
use App\UserEvent\Domain\Enum\UserEventType;
use App\UserEvent\Domain\Port\CardParticipantsProvider;
use App\UserEvent\Domain\Port\ProjectMembersProvider;
use App\UserEvent\Domain\Repository\UserEventRepository;
use App\UserEvent\Domain\Service\RecipientResolver;
use App\UserEvent\Domain\Service\Rule\CardAssignedRule;
use App\UserEvent\Domain\Service\Rule\CardCreatedRule;
use App\UserEvent\Domain\Service\Rule\CardUnassignedRule;
use App\UserEvent\Domain\Service\Rule\CommentCreatedRule;
use App\UserEvent\Domain\Service\Rule\ProjectArchivedRule;
use App\UserEvent\Domain\Service\Rule\ProjectMemberRoleChangedRule;
use App\UserEvent\Domain\Service\Rule\ProjectRestoredRule;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class UserEventFanOutHandlerTest extends TestCase
{
    private UserEventRepository&MockObject $events;
    private UserEventContextProvider&MockObject $contexts;
    private CardParticipantsProvider&MockObject $participants;
    private ProjectMembersProvider&MockObject $members;
    private UserEventNotifierInterface&MockObject $notifier;

    /** @var list<UserEvent> */
    private array $saved = [];

    /** @var list<array{userId: \Ramsey\Uuid\UuidInterface, item: array<string, mixed>}> */
    private array $notified = [];

    protected function setUp(): void
    {
        $this->events = $this->createMock(UserEventRepository::class);
        $this->contexts = $this->createMock(UserEventContextProvider::class);
        $this->participants = $this->createMock(CardParticipantsProvider::class);
        $this->members = $this->createMock(ProjectMembersProvider::class);
        $this->notifier = $this->createMock(UserEventNotifierInterface::class);
        $this->saved = [];
        $this->notified = [];
    }

    public function testAssignmentCreatesRowForAssignee(): void
    {
        $cardId = Uuid::uuid4();
        $projectId = Uuid::uuid4();
        $assignee = Uuid::uuid4();
        $actor = Uuid::uuid4();
        $this->contexts->method('card')->willReturn(new CardEventContext($projectId, 'Kanban', 'Fix it'));
        $this->contexts->method('userName')->willReturn('Alice');
        $this->captureSaves(1);
        $this->captureNotify(1);

        $this->handler()->onCardAssigned(new CardAssigned((string) $cardId, (string) $assignee, 'Bob', (string) $actor));

        self::assertTrue($assignee->equals($this->notified[0]['userId']));
        self::assertSame((string) $this->saved[0]->id(), $this->notified[0]['item']['id']);
        self::assertSame('card.assigned', $this->notified[0]['item']['type']);
        self::assertSame('Alice', $this->notified[0]['item']['actorName']);

        $event = $this->saved[0];
        self::assertTrue($assignee->equals($event->userId()));
        self::assertSame(UserEventType::CardAssigned, $event->type());
        self::assertTrue($cardId->equals($event->aggregateRootId()));
        self::assertTrue($projectId->equals($event->projectId()));
        self::assertTrue($cardId->equals($event->cardId()));
        self::assertTrue($actor->equals($event->actorId()));
        self::assertSame(
            ['actorName' => 'Alice', 'projectName' => 'Kanban', 'cardTitle' => 'Fix it'],
            $event->payload(),
        );
    }

    public function testCardCreatedNotifiesAllProjectMembersExceptActor(): void
    {
        $cardId = Uuid::uuid4();
        $projectId = Uuid::uuid4();
        $actor = Uuid::uuid4();
        $member = Uuid::uuid4();
        $this->participants->method('projectId')->willReturn($projectId);
        $this->members->method('memberIds')->willReturn([$actor, $member]);
        $this->contexts->method('card')->willReturn(new CardEventContext($projectId, 'Kanban', 'New card'));
        $this->contexts->method('userName')->willReturn('Alice');
        $this->captureSaves(1);
        $this->captureNotify(1);

        $this->handler()->onCardCreated(new CardCreated((string) $cardId, (string) $actor));

        $event = $this->saved[0];
        self::assertTrue($member->equals($event->userId()));
        self::assertSame(UserEventType::CardCreated, $event->type());
        self::assertTrue($cardId->equals($event->aggregateRootId()));
        self::assertTrue($projectId->equals($event->projectId()));
        self::assertTrue($cardId->equals($event->cardId()));
        self::assertSame(
            ['actorName' => 'Alice', 'projectName' => 'Kanban', 'cardTitle' => 'New card'],
            $event->payload(),
        );
        self::assertSame('card.created', $this->notified[0]['item']['type']);
        self::assertTrue($member->equals($this->notified[0]['userId']));
    }

    public function testSelfAssignmentIsSilent(): void
    {
        $userId = Uuid::uuid4();
        $this->contexts->method('card')->willReturn(new CardEventContext(Uuid::uuid4(), 'Kanban', 'Fix it'));
        $this->events->expects($this->never())->method('save');
        $this->notifier->expects($this->never())->method('notify');

        $this->handler()->onCardAssigned(new CardAssigned((string) Uuid::uuid4(), (string) $userId, 'Bob', (string) $userId));
    }

    public function testUnassignmentCreatesRowForUnassignedUser(): void
    {
        $cardId = Uuid::uuid4();
        $userId = Uuid::uuid4();
        $this->contexts->method('card')->willReturn(new CardEventContext(Uuid::uuid4(), 'Kanban', 'Fix it'));
        $this->captureSaves(1);

        $this->handler()->onCardUnassigned(new CardUnassigned((string) $cardId, (string) $userId, 'Bob', (string) Uuid::uuid4()));

        self::assertSame(UserEventType::CardUnassigned, $this->saved[0]->type());
        self::assertTrue($userId->equals($this->saved[0]->userId()));
    }

    public function testCommentNotifiesCardParticipantsExceptAuthor(): void
    {
        $cardId = Uuid::uuid4();
        $commentId = Uuid::uuid4();
        $projectId = Uuid::uuid4();
        $author = Uuid::uuid4();
        $assignee = Uuid::uuid4();
        $this->participants->method('assigneeIds')->willReturn([$author, $assignee]);
        $this->participants->method('creatorId')->willReturn($assignee);
        $this->contexts->method('card')->willReturn(new CardEventContext($projectId, 'Kanban', 'Fix it'));
        $this->contexts->method('userName')->willReturn('Author');
        $this->captureSaves(1);

        $this->handler()->onCommentCreated(new CommentCreated((string) $cardId, (string) $commentId, (string) $author));

        $event = $this->saved[0];
        self::assertTrue($assignee->equals($event->userId()));
        self::assertSame(UserEventType::CommentCreated, $event->type());
        self::assertTrue($commentId->equals($event->aggregateRootId()));
        self::assertTrue($cardId->equals($event->cardId()));
        self::assertTrue($projectId->equals($event->projectId()));
        self::assertSame('Author', $event->payload()['actorName']);
    }

    public function testProjectArchivedNotifiesAllMembersExceptActor(): void
    {
        $projectId = Uuid::uuid4();
        $actor = Uuid::uuid4();
        $member = Uuid::uuid4();
        $this->members->method('memberIds')->willReturn([$actor, $member]);
        $this->contexts->method('projectName')->willReturn('Kanban');
        $this->contexts->method('userName')->willReturn('Alice');
        $this->captureSaves(1);

        $this->handler()->onProjectArchived(new ProjectArchived((string) $projectId, (string) $actor));

        $event = $this->saved[0];
        self::assertTrue($member->equals($event->userId()));
        self::assertSame(UserEventType::ProjectArchived, $event->type());
        self::assertTrue($projectId->equals($event->aggregateRootId()));
        self::assertTrue($projectId->equals($event->projectId()));
        self::assertNull($event->cardId());
        self::assertSame(['actorName' => 'Alice', 'projectName' => 'Kanban'], $event->payload());
    }

    public function testProjectRestoredNotifiesAllMembersExceptActor(): void
    {
        $projectId = Uuid::uuid4();
        $actor = Uuid::uuid4();
        $member = Uuid::uuid4();
        $this->members->method('memberIds')->willReturn([$actor, $member]);
        $this->contexts->method('projectName')->willReturn('Kanban');
        $this->captureSaves(1);

        $this->handler()->onProjectRestored(new ProjectRestored((string) $projectId, (string) $actor));

        self::assertSame(UserEventType::ProjectRestored, $this->saved[0]->type());
        self::assertTrue($member->equals($this->saved[0]->userId()));
    }

    public function testRoleChangeStoresNewRole(): void
    {
        $projectId = Uuid::uuid4();
        $userId = Uuid::uuid4();
        $this->contexts->method('projectName')->willReturn('Kanban');
        $this->captureSaves(1);

        $this->handler()->onProjectMemberRoleChanged(
            new ProjectMemberRoleChanged((string) $projectId, (string) $userId, MemberRole::Admin, (string) Uuid::uuid4()),
        );

        self::assertSame(UserEventType::ProjectMemberRoleChanged, $this->saved[0]->type());
        self::assertSame('admin', $this->saved[0]->payload()['role']);
        self::assertTrue($projectId->equals($this->saved[0]->aggregateRootId()));
    }

    public function testExistingRowIsSkipped(): void
    {
        $this->contexts->method('card')->willReturn(new CardEventContext(Uuid::uuid4(), 'Kanban', 'Fix it'));
        $this->events->method('existsByNaturalKey')->willReturn(true);
        $this->events->expects($this->never())->method('save');
        $this->notifier->expects($this->never())->method('notify');

        $this->handler()->onCardAssigned(new CardAssigned(
            (string) Uuid::uuid4(),
            (string) Uuid::uuid4(),
            'Bob',
            (string) Uuid::uuid4(),
        ));
    }

    public function testMissingCardContextSkips(): void
    {
        $this->contexts->method('card')->willReturn(null);
        $this->events->expects($this->never())->method('save');

        $this->handler()->onCardAssigned(new CardAssigned(
            (string) Uuid::uuid4(),
            (string) Uuid::uuid4(),
            'Bob',
            (string) Uuid::uuid4(),
        ));
    }

    public function testMissingProjectSkips(): void
    {
        $this->contexts->method('projectName')->willReturn(null);
        $this->events->expects($this->never())->method('save');

        $this->handler()->onProjectArchived(new ProjectArchived((string) Uuid::uuid4(), (string) Uuid::uuid4()));
    }

    private function handler(): UserEventFanOutHandler
    {
        return new UserEventFanOutHandler(
            new RecipientResolver([
                new CardCreatedRule($this->participants, $this->members),
                new CardAssignedRule(),
                new CardUnassignedRule(),
                new CommentCreatedRule($this->participants),
                new ProjectMemberRoleChangedRule(),
                new ProjectArchivedRule($this->members),
                new ProjectRestoredRule($this->members),
            ]),
            $this->events,
            $this->contexts,
            $this->notifier,
        );
    }

    private function captureSaves(int $count): void
    {
        $this->events->expects($this->exactly($count))->method('save')->willReturnCallback(
            function (UserEvent $event): void {
                $this->saved[] = $event;
            },
        );
    }

    private function captureNotify(int $count): void
    {
        $this->notifier->expects($this->exactly($count))->method('notify')->willReturnCallback(
            function (\Ramsey\Uuid\UuidInterface $userId, array $item): void {
                $this->notified[] = ['userId' => $userId, 'item' => $item];
            },
        );
    }
}
