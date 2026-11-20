<?php

declare(strict_types=1);

namespace App\Tests\Unit\UserEvent\Domain\Service\Rule;

use App\Board\Domain\Event\CardAssigned;
use App\Board\Domain\Event\CardCreated;
use App\UserEvent\Domain\Enum\UserEventType;
use App\UserEvent\Domain\Port\CardParticipantsProvider;
use App\UserEvent\Domain\Port\ProjectMembersProvider;
use App\UserEvent\Domain\Service\Rule\CardCreatedRule;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class CardCreatedRuleTest extends TestCase
{
    public function testSupportsOnlyCardCreated(): void
    {
        $rule = new CardCreatedRule(
            $this->createMock(CardParticipantsProvider::class),
            $this->createMock(ProjectMembersProvider::class),
        );

        self::assertTrue($rule->supports(new CardCreated((string)Uuid::uuid4(), (string)Uuid::uuid4())));
        self::assertFalse(
            $rule->supports(
                new CardAssigned((string)Uuid::uuid4(), (string)Uuid::uuid4(), 'Bob', (string)Uuid::uuid4()),
            )
        );
    }

    public function testType(): void
    {
        $rule = new CardCreatedRule(
            $this->createMock(CardParticipantsProvider::class),
            $this->createMock(ProjectMembersProvider::class),
        );

        self::assertSame(UserEventType::CardCreated, $rule->type());
    }

    public function testRecipientsAreAllProjectMembers(): void
    {
        $cardId = Uuid::uuid4();
        $projectId = Uuid::uuid4();
        $first = Uuid::uuid4();
        $second = Uuid::uuid4();
        $cards = $this->createMock(CardParticipantsProvider::class);
        $cards->method('projectId')->willReturn($projectId);
        $members = $this->createMock(ProjectMembersProvider::class);
        $members->method('memberIds')->willReturn([$first, $second]);

        $recipients = (new CardCreatedRule($cards, $members))->recipients(
            new CardCreated((string)$cardId, (string)Uuid::uuid4()),
        );

        self::assertCount(2, $recipients);
        self::assertTrue($first->equals($recipients[0]));
        self::assertTrue($second->equals($recipients[1]));
    }

    public function testMissingProjectYieldsNoRecipients(): void
    {
        $cards = $this->createMock(CardParticipantsProvider::class);
        $cards->method('projectId')->willReturn(null);
        $members = $this->createMock(ProjectMembersProvider::class);
        $members->expects($this->never())->method('memberIds');

        $recipients = (new CardCreatedRule($cards, $members))->recipients(
            new CardCreated((string)Uuid::uuid4(), (string)Uuid::uuid4()),
        );

        self::assertSame([], $recipients);
    }
}
