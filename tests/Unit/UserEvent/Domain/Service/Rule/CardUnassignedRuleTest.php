<?php

declare(strict_types=1);

namespace App\Tests\Unit\UserEvent\Domain\Service\Rule;

use App\Board\Domain\Event\CardAssigned;
use App\Board\Domain\Event\CardUnassigned;
use App\UserEvent\Domain\Enum\UserEventType;
use App\UserEvent\Domain\Service\Rule\CardUnassignedRule;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class CardUnassignedRuleTest extends TestCase
{
    public function testSupportsOnlyCardUnassigned(): void
    {
        $rule = new CardUnassignedRule();

        self::assertTrue($rule->supports(new CardUnassigned((string) Uuid::uuid4(), (string) Uuid::uuid4(), 'User', null)));
        self::assertFalse($rule->supports(new CardAssigned((string) Uuid::uuid4(), (string) Uuid::uuid4(), 'User', null)));
    }

    public function testType(): void
    {
        self::assertSame(UserEventType::CardUnassigned, (new CardUnassignedRule())->type());
    }

    public function testRecipientsContainUnassignedUser(): void
    {
        $userId = Uuid::uuid4();
        $event = new CardUnassigned((string) Uuid::uuid4(), (string) $userId, 'User', (string) Uuid::uuid4());

        $recipients = (new CardUnassignedRule())->recipients($event);

        self::assertCount(1, $recipients);
        self::assertTrue($userId->equals($recipients[0]));
    }
}
