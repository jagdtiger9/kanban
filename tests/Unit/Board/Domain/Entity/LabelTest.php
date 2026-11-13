<?php

declare(strict_types=1);

namespace App\Tests\Unit\Board\Domain\Entity;

use App\Board\Domain\Entity\Label;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class LabelTest extends TestCase
{
    public function testCreateTrimsNameAndColor(): void
    {
        $label = Label::create(Uuid::uuid4(), Uuid::uuid4(), '  Bug  ', ' #ff0000 ');

        self::assertSame('Bug', $label->name());
        self::assertSame('#ff0000', $label->color());
    }

    public function testCreateRejectsEmptyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Label::create(Uuid::uuid4(), Uuid::uuid4(), ' ', '#ff0000');
    }

    public function testCreateRejectsEmptyColor(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Label::create(Uuid::uuid4(), Uuid::uuid4(), 'Bug', ' ');
    }

    public function testRenameRejectsEmpty(): void
    {
        $label = Label::create(Uuid::uuid4(), Uuid::uuid4(), 'Bug', '#ff0000');

        $this->expectException(\InvalidArgumentException::class);
        $label->rename(' ');
    }
}
