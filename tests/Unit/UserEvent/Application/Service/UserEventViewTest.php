<?php

declare(strict_types=1);

namespace App\Tests\Unit\UserEvent\Application\Service;

use App\UserEvent\Application\Service\UserEventView;
use App\UserEvent\Domain\Entity\UserEvent;
use App\UserEvent\Domain\Enum\UserEventType;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class UserEventViewTest extends TestCase
{
    public function testMapsAnUnseenCardItemForApiAndRealtime(): void
    {
        $id = Uuid::uuid4();
        $userId = Uuid::uuid4();
        $projectId = Uuid::uuid4();
        $cardId = Uuid::uuid4();
        $actorId = Uuid::uuid4();
        $event = UserEvent::record(
            $id,
            $userId,
            UserEventType::CommentCreated,
            $cardId,
            new \DateTimeImmutable('2026-11-20T10:00:00+00:00'),
            ['actorName' => 'Alice', 'projectName' => 'Kanban', 'cardTitle' => 'Fix it'],
            $projectId,
            $cardId,
            $actorId,
        );

        self::assertSame([
            'id' => (string) $id,
            'type' => 'comment.created',
            'actorId' => (string) $actorId,
            'actorName' => 'Alice',
            'projectId' => (string) $projectId,
            'projectName' => 'Kanban',
            'cardId' => (string) $cardId,
            'cardTitle' => 'Fix it',
            'role' => null,
            'occurredOn' => '2026-11-20T10:00:00+00:00',
            'seenAt' => null,
        ], UserEventView::item($event));
    }

    public function testMapsAProjectItemWithoutCardData(): void
    {
        $projectId = Uuid::uuid4();
        $event = UserEvent::record(
            Uuid::uuid4(),
            Uuid::uuid4(),
            UserEventType::ProjectMemberRoleChanged,
            $projectId,
            new \DateTimeImmutable('2026-11-20T10:00:00+00:00'),
            ['actorName' => null, 'projectName' => 'Kanban', 'role' => 'admin'],
            $projectId,
        );

        $item = UserEventView::item($event);

        self::assertSame('project.member_role_changed', $item['type']);
        self::assertNull($item['cardId']);
        self::assertNull($item['cardTitle']);
        self::assertNull($item['actorId']);
        self::assertSame('admin', $item['role']);
    }
}
