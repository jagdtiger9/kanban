<?php

declare(strict_types=1);

namespace App\Tests\Unit\UserEvent\Domain\Service\Rule;

use App\Board\Domain\Event\CardAssigned;
use App\Board\Domain\Event\CardExecutorUnassigned;
use App\UserEvent\Domain\Enum\UserEventType;
use App\UserEvent\Domain\Service\Rule\CardExecutorUnassignedRule;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class CardExecutorUnassignedRuleTest extends TestCase
{
    public function testSupportsOnlyCardExecutorUnassigned(): void
    {
        $rule = new CardExecutorUnassignedRule();

        self::assertTrue($rule->supports(new CardExecutorUnassigned((string) Uuid::uuid4(), (string) Uuid::uuid4(), 'User', null)));
        self::assertFalse($rule->supports(new CardAssigned((string) Uuid::uuid4(), (string) Uuid::uuid4(), 'User', null)));
    }

    public function testType(): void
    {
        self::assertSame(UserEventType::CardExecutorUnassigned, (new CardExecutorUnassignedRule())->type());
    }

    public function testRecipientsContainUnassignedUser(): void
    {
        $userId = Uuid::uuid4();
        $event = new CardExecutorUnassigned((string) Uuid::uuid4(), (string) $userId, 'User', (string) Uuid::uuid4());

        $recipients = (new CardExecutorUnassignedRule())->recipients($event);

        self::assertCount(1, $recipients);
        self::assertTrue($userId->equals($recipients[0]));
    }
}
