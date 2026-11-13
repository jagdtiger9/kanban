<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Domain\Entity;

use App\Board\Domain\Entity\CardActivity;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class CardActivityTest extends TestCase
{
    public function testCreateStoresPayloadAndActor(): void
    {
        $cardId = Uuid::uuid4();
        $actorId = Uuid::uuid4();
        $activity = CardActivity::create(
            Uuid::uuid4(),
            $cardId,
            $actorId,
            'card.moved',
            ['old_column' => 'a', 'new_column' => 'b'],
        );

        self::assertTrue($activity->cardId()->equals($cardId));
        self::assertTrue($activity->actorId()->equals($actorId));
        self::assertSame('card.moved', $activity->type());
        self::assertSame(['old_column' => 'a', 'new_column' => 'b'], $activity->payload());
    }

    public function testCreateAllowsNullActor(): void
    {
        $activity = CardActivity::create(Uuid::uuid4(), Uuid::uuid4(), null, 'card.created', []);

        self::assertNull($activity->actorId());
    }

    public function testCreateRejectsEmptyType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CardActivity::create(Uuid::uuid4(), Uuid::uuid4(), null, '  ', []);
    }
}
