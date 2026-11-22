<?php

declare(strict_types=1);

namespace App\Tests\Unit\UserEvent\Domain\Service\Rule;

use App\Board\Domain\Event\CardExecutorAssigned;
use App\Board\Domain\Event\CardExecutorUnassigned;
use App\UserEvent\Domain\Enum\UserEventType;
use App\UserEvent\Domain\Service\Rule\CardExecutorAssignedRule;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class CardExecutorAssignedRuleTest extends TestCase
{
    public function testSupportsOnlyCardExecutorAssigned(): void
    {
        $rule = new CardExecutorAssignedRule();

        self::assertTrue($rule->supports(new CardExecutorAssigned((string) Uuid::uuid4(), (string) Uuid::uuid4(), 'User', null)));
        self::assertFalse($rule->supports(new CardExecutorUnassigned((string) Uuid::uuid4(), (string) Uuid::uuid4(), 'User', null)));
    }

    public function testType(): void
    {
        self::assertSame(UserEventType::CardExecutorAssigned, (new CardExecutorAssignedRule())->type());
    }

    public function testRecipientsContainAssignedUser(): void
    {
        $userId = Uuid::uuid4();
        $event = new CardExecutorAssigned((string) Uuid::uuid4(), (string) $userId, 'User', (string) Uuid::uuid4());

        $recipients = (new CardExecutorAssignedRule())->recipients($event);

        self::assertCount(1, $recipients);
        self::assertTrue($userId->equals($recipients[0]));
    }
}
