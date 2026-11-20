<?php

declare(strict_types=1);

namespace App\UserEvent\Application\Service;

use App\UserEvent\Domain\Exception\InvalidUserEventCursorException;
use App\UserEvent\Domain\ValueObject\UserEventCursor;
use Ramsey\Uuid\Uuid;

final class UserEventCursorCodec
{
    public function encode(UserEventCursor $cursor): string
    {
        $value = $cursor->occurredOn()->format('Y-m-d\TH:i:s.uP') . '|' . $cursor->id();

        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    public function decode(string $value): UserEventCursor
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false || substr_count($decoded, '|') !== 1) {
            throw InvalidUserEventCursorException::malformed();
        }

        [$occurredOn, $id] = explode('|', $decoded);

        try {
            return new UserEventCursor(new \DateTimeImmutable($occurredOn), Uuid::fromString($id));
        } catch (\Throwable) {
            throw InvalidUserEventCursorException::malformed();
        }
    }
}
