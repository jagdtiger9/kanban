<?php

declare(strict_types=1);

namespace App\Tests\Unit\Call\Application;

use App\Call\Application\Message\Query\ListActiveCallsQuery;
use App\Call\Application\MessageHandler\ListActiveCallsQueryHandler;
use App\Call\Domain\Model\Participant;
use App\Project\Domain\Repository\ProjectRepository;
use Ramsey\Uuid\UuidInterface;

final class ListActiveCallsQueryHandlerTest extends CallFixture
{
    private function handler(): ListActiveCallsQueryHandler
    {
        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('findByMember')->willReturnCallback(
            fn(UuidInterface $id): array => $id->equals($this->eve->id()) ? [] : [$this->project],
        );

        return new ListActiveCallsQueryHandler($projects, $this->rooms, $this->broadcaster, $this->settings, $this->clock);
    }

    public function testListsNonEmptyRoomsOfOwnProjects(): void
    {
        $this->rooms->rooms[$this->projectId()][$this->id($this->alice)] = new Participant($this->id($this->alice), 's1', $this->clock->now());

        $calls = ($this->handler())(new ListActiveCallsQuery($this->id($this->bob)));

        self::assertSame($this->projectId(), $calls[0]['projectId']);
        self::assertSame(['Alice'], array_column($calls[0]['participants'], 'displayName'));
        self::assertSame([], ($this->handler())(new ListActiveCallsQuery($this->id($this->eve))));
    }

    public function testStaleRoomIsNotListed(): void
    {
        $this->rooms->rooms[$this->projectId()][$this->id($this->alice)] = new Participant($this->id($this->alice), 's1', $this->clock->now()->modify('-5 minutes'));

        self::assertSame([], ($this->handler())(new ListActiveCallsQuery($this->id($this->bob))));
    }
}
