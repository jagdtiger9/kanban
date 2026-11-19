<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Domain\Entity;

use App\Board\Domain\Entity\CardView;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class CardViewTest extends TestCase
{
    public function testCreateStoresUserCardAndTimestamp(): void
    {
        $id = Uuid::uuid4();
        $userId = Uuid::uuid4();
        $cardId = Uuid::uuid4();
        $before = new \DateTimeImmutable();

        $view = CardView::create($id, $userId, $cardId);

        self::assertTrue($view->id()->equals($id));
        self::assertTrue($view->userId()->equals($userId));
        self::assertTrue($view->cardId()->equals($cardId));
        self::assertGreaterThanOrEqual($before, $view->lastSeenAt());
    }

    public function testMarkSeenAdvancesTimestamp(): void
    {
        $view = CardView::create(Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4());
        $future = (new \DateTimeImmutable())->modify('+1 hour');

        $view->markSeen($future);

        self::assertSame($future, $view->lastSeenAt());
    }

    public function testMarkSeenIgnoresEarlierTimestamp(): void
    {
        $view = CardView::create(Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4());
        $initial = $view->lastSeenAt();
        $past = (new \DateTimeImmutable())->modify('-1 hour');

        $view->markSeen($past);

        self::assertSame($initial, $view->lastSeenAt());
    }
}
