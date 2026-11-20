<?php

declare(strict_types=1);

namespace App\Tests\Unit\UserEvent\Application\Service;

use App\UserEvent\Application\Service\UserChannel;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class UserChannelTest extends TestCase
{
    public function testAddressesTheUsersPersonalChannel(): void
    {
        $userId = (string) Uuid::uuid4();

        self::assertSame('user:' . $userId, UserChannel::forUser($userId));
    }
}
