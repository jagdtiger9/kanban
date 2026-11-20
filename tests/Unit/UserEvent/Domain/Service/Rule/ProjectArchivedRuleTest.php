<?php

declare(strict_types=1);

namespace App\Tests\Unit\UserEvent\Domain\Service\Rule;

use App\Project\Domain\Event\ProjectArchived;
use App\Project\Domain\Event\ProjectRestored;
use App\UserEvent\Domain\Enum\UserEventType;
use App\UserEvent\Domain\Port\ProjectMembersProvider;
use App\UserEvent\Domain\Service\Rule\ProjectArchivedRule;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class ProjectArchivedRuleTest extends TestCase
{
    public function testSupportsOnlyProjectArchived(): void
    {
        $rule = new ProjectArchivedRule($this->createMock(ProjectMembersProvider::class));

        self::assertTrue($rule->supports(new ProjectArchived((string) Uuid::uuid4(), (string) Uuid::uuid4())));
        self::assertFalse($rule->supports(new ProjectRestored((string) Uuid::uuid4(), (string) Uuid::uuid4())));
    }

    public function testType(): void
    {
        self::assertSame(
            UserEventType::ProjectArchived,
            (new ProjectArchivedRule($this->createMock(ProjectMembersProvider::class)))->type(),
        );
    }

    public function testRecipientsAreAllProjectMembers(): void
    {
        $first = Uuid::uuid4();
        $second = Uuid::uuid4();
        $members = $this->createMock(ProjectMembersProvider::class);
        $members->method('memberIds')->willReturn([$first, $second]);
        $event = new ProjectArchived((string) Uuid::uuid4(), (string) Uuid::uuid4());

        $recipients = (new ProjectArchivedRule($members))->recipients($event);

        self::assertCount(2, $recipients);
        self::assertTrue($first->equals($recipients[0]));
        self::assertTrue($second->equals($recipients[1]));
    }
}
