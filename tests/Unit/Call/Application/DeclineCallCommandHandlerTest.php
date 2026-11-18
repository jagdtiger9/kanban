<?php

declare(strict_types=1);

namespace App\Tests\Unit\Call\Application;

use App\Call\Application\Message\Command\DeclineCallCommand;
use App\Call\Application\MessageHandler\DeclineCallCommandHandler;

final class DeclineCallCommandHandlerTest extends CallFixture
{
    public function testInviterIsToldAboutTheDecline(): void
    {
        $handler = new DeclineCallCommandHandler($this->access, $this->broadcaster, $this->publisher);

        $handler(new DeclineCallCommand($this->projectId(), $this->id($this->alice), $this->id($this->bob)));

        self::assertSame([$this->id($this->alice)], $this->publisher->published[0]['userIds']);
        self::assertSame('invite.declined', $this->publisher->published[0]['data']['type']);
        self::assertSame('Bob', $this->publisher->published[0]['data']['displayName']);
    }
}
