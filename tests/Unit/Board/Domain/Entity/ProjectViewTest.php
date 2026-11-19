<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Domain\Entity;

use App\Board\Domain\Entity\ProjectView;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class ProjectViewTest extends TestCase
{
    public function testCreateStoresUserProjectAndTimestamp(): void
    {
        $id = Uuid::uuid4();
        $userId = Uuid::uuid4();
        $projectId = Uuid::uuid4();
        $before = new \DateTimeImmutable();

        $view = ProjectView::create($id, $userId, $projectId);

        self::assertTrue($view->id()->equals($id));
        self::assertTrue($view->userId()->equals($userId));
        self::assertTrue($view->projectId()->equals($projectId));
        self::assertGreaterThanOrEqual($before, $view->firstSeenAt());
    }
}
