<?php

declare(strict_types=1);

namespace App\Tests\Unit\UserEvent\Application\Service;

use App\UserEvent\Application\Service\UserEventCursorCodec;
use App\UserEvent\Domain\Exception\InvalidUserEventCursorException;
use App\UserEvent\Domain\ValueObject\UserEventCursor;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class UserEventCursorCodecTest extends TestCase
{
    public function testRoundTripsMicrosecondPosition(): void
    {
        $codec = new UserEventCursorCodec();
        $cursor = new UserEventCursor(new \DateTimeImmutable('2026-11-20 10:11:12.123456+00:00'), Uuid::uuid4());

        $decoded = $codec->decode($codec->encode($cursor));

        self::assertSame($cursor->occurredOn()->format('U.u'), $decoded->occurredOn()->format('U.u'));
        self::assertTrue($cursor->id()->equals($decoded->id()));
    }

    public function testRejectsMalformedValues(): void
    {
        $codec = new UserEventCursorCodec();
        $values = [
            'not-a-cursor',
            self::urlSafe(base64_encode('no-separator')),
            self::urlSafe(base64_encode('2026-11-20T10:00:00+00:00|not-a-uuid')),
        ];

        foreach ($values as $value) {
            try {
                $codec->decode($value);
                self::fail('Expected a malformed cursor to be rejected: ' . $value);
            } catch (InvalidUserEventCursorException) {
                self::addToAssertionCount(1);
            }
        }
    }

    private static function urlSafe(string $value): string
    {
        return rtrim(strtr($value, '+/', '-_'), '=');
    }
}
