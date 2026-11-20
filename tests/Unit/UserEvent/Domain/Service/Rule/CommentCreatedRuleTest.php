<?php

declare(strict_types=1);

namespace App\Tests\Unit\UserEvent\Domain\Service\Rule;

use App\Board\Domain\Event\CommentCreated;
use App\Project\Domain\Event\ProjectArchived;
use App\UserEvent\Domain\Enum\UserEventType;
use App\UserEvent\Domain\Port\CardParticipantsProvider;
use App\UserEvent\Domain\Service\Rule\CommentCreatedRule;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class CommentCreatedRuleTest extends TestCase
{
    public function testSupportsOnlyCommentCreated(): void
    {
        $rule = new CommentCreatedRule($this->createMock(CardParticipantsProvider::class));

        self::assertTrue($rule->supports(new CommentCreated((string) Uuid::uuid4(), (string) Uuid::uuid4(), (string) Uuid::uuid4())));
        self::assertFalse($rule->supports(new ProjectArchived((string) Uuid::uuid4(), (string) Uuid::uuid4())));
    }

    public function testType(): void
    {
        self::assertSame(
            UserEventType::CommentCreated,
            (new CommentCreatedRule($this->createMock(CardParticipantsProvider::class)))->type(),
        );
    }

    public function testRecipientsUnionAssigneesAndCreator(): void
    {
        $assignee = Uuid::uuid4();
        $creator = Uuid::uuid4();
        $participants = $this->createMock(CardParticipantsProvider::class);
        $participants->method('assigneeIds')->willReturn([$assignee]);
        $participants->method('creatorId')->willReturn($creator);
        $event = new CommentCreated((string) Uuid::uuid4(), (string) Uuid::uuid4(), (string) Uuid::uuid4());

        $recipients = (new CommentCreatedRule($participants))->recipients($event);

        self::assertCount(2, $recipients);
        self::assertTrue($assignee->equals($recipients[0]));
        self::assertTrue($creator->equals($recipients[1]));
    }

    public function testRecipientsWithoutCreatorAreAssigneesOnly(): void
    {
        $participants = $this->createMock(CardParticipantsProvider::class);
        $participants->method('assigneeIds')->willReturn([Uuid::uuid4()]);
        $participants->method('creatorId')->willReturn(null);
        $event = new CommentCreated((string) Uuid::uuid4(), (string) Uuid::uuid4(), (string) Uuid::uuid4());

        self::assertCount(1, (new CommentCreatedRule($participants))->recipients($event));
    }
}
