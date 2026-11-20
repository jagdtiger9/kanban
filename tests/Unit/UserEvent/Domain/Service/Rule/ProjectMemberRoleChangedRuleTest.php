<?php

declare(strict_types=1);

namespace App\Tests\Unit\UserEvent\Domain\Service\Rule;

use App\Project\Domain\Enum\MemberRole;
use App\Project\Domain\Event\ProjectArchived;
use App\Project\Domain\Event\ProjectMemberRoleChanged;
use App\UserEvent\Domain\Enum\UserEventType;
use App\UserEvent\Domain\Service\Rule\ProjectMemberRoleChangedRule;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class ProjectMemberRoleChangedRuleTest extends TestCase
{
    public function testSupportsOnlyProjectMemberRoleChanged(): void
    {
        $rule = new ProjectMemberRoleChangedRule();

        self::assertTrue($rule->supports(new ProjectMemberRoleChanged((string) Uuid::uuid4(), (string) Uuid::uuid4(), MemberRole::Admin, (string) Uuid::uuid4())));
        self::assertFalse($rule->supports(new ProjectArchived((string) Uuid::uuid4(), (string) Uuid::uuid4())));
    }

    public function testType(): void
    {
        self::assertSame(UserEventType::ProjectMemberRoleChanged, (new ProjectMemberRoleChangedRule())->type());
    }

    public function testRecipientsContainAffectedUser(): void
    {
        $userId = Uuid::uuid4();
        $event = new ProjectMemberRoleChanged((string) Uuid::uuid4(), (string) $userId, MemberRole::Member, (string) Uuid::uuid4());

        $recipients = (new ProjectMemberRoleChangedRule())->recipients($event);

        self::assertCount(1, $recipients);
        self::assertTrue($userId->equals($recipients[0]));
    }
}
