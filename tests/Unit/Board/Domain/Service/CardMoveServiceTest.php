<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Domain\Service;

use App\Board\Domain\Entity\Card;
use App\Board\Domain\Event\CardMoved;
use App\Board\Domain\Service\CardMoveService;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class CardMoveServiceTest extends TestCase
{
    private function card(string $title, int $position = 0): Card
    {
        return Card::create(Uuid::uuid4(), Uuid::uuid4(), Uuid::uuid4(), $title, '', $position);
    }

    /** @return array{0: Card, 1: Card, 2: Card} */
    private function column(): array
    {
        $a = $this->card('A', 0);
        $b = $this->card('B', 1);
        $c = $this->card('C', 2);

        return [$a, $b, $c];
    }

    public function testReorderWithinColumn(): void
    {
        [$a, $b, $c] = $this->column();
        $columnId = $a->columnId();
        $service = new CardMoveService();

        $event = $service->move($a, $columnId, 2, [$a, $b, $c], []);

        self::assertInstanceOf(CardMoved::class, $event);
        self::assertSame(0, $event->oldPosition);
        self::assertSame(2, $event->newPosition);
        self::assertSame(0, $b->position());
        self::assertSame(1, $c->position());
        self::assertSame(2, $a->position());
    }

    public function testMoveToSameIndexReturnsNull(): void
    {
        [$a, $b, $c] = $this->column();
        $service = new CardMoveService();

        $event = $service->move($a, $a->columnId(), 0, [$a, $b, $c], []);

        self::assertNull($event);
        self::assertSame([0, 1, 2], [$a->position(), $b->position(), $c->position()]);
    }

    public function testMoveToAnotherColumnCompactsOldAndInsertsIntoNew(): void
    {
        [$a, $b, $c] = $this->column();
        $x = $this->card('X', 0);
        $newColumnId = $x->columnId();
        $service = new CardMoveService();

        $event = $service->move($b, $newColumnId, 0, [$a, $b, $c], [$x]);

        self::assertInstanceOf(CardMoved::class, $event);
        self::assertSame(1, $event->oldPosition);
        self::assertSame(0, $event->newPosition);
        // Old column compacted to [a, c].
        self::assertSame(0, $a->position());
        self::assertSame(1, $c->position());
        // New column becomes [b, x].
        self::assertSame(0, $b->position());
        self::assertSame(1, $x->position());
        self::assertTrue($b->columnId()->equals($newColumnId));
    }

    public function testTargetIndexClampedToEnd(): void
    {
        [$a, $b, $c] = $this->column();
        $service = new CardMoveService();

        $event = $service->move($a, $a->columnId(), 99, [$a, $b, $c], []);

        self::assertSame(2, $event->newPosition);
        self::assertSame(2, $a->position());
        self::assertSame(0, $b->position());
        self::assertSame(1, $c->position());
    }

    public function testRejectsNegativeTargetIndex(): void
    {
        [$a, $b, $c] = $this->column();
        $service = new CardMoveService();

        $this->expectException(\InvalidArgumentException::class);
        $service->move($a, $a->columnId(), -1, [$a, $b, $c], []);
    }
}
