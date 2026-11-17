<?php

declare(strict_types=1);

namespace App\Mcp\Application\MessageHandler;

use App\Mcp\Application\Message\Query\ListRecentActivityQuery;
use App\Mcp\Domain\ReadModel\RecentActivity;
use App\Mcp\Domain\Repository\RecentActivityReader;
use App\Shared\Domain\Exception\AccessForbiddenException;
use App\User\Domain\Repository\UserRepository;
use Psr\Clock\ClockInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ListRecentActivityQueryHandler
{
    private const string DEFAULT_PERIOD = '-1 day';
    private const int LIMIT = 200;

    public function __construct(
        private RecentActivityReader $reader,
        private UserRepository $userRepository,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return list<RecentActivity>
     */
    public function __invoke(ListRecentActivityQuery $query): array
    {
        $actorId = Uuid::fromString($query->actorId);
        $actor = $this->userRepository->findById($actorId);
        if ($actor === null) {
            throw new AccessForbiddenException('Unknown user');
        }

        return $this->reader->findSince(
            $this->since($query->since),
            $actor->isGlobalAdmin() ? null : $actorId,
            self::LIMIT,
        );
    }

    private function since(string $since): \DateTimeImmutable
    {
        if ($since === '') {
            return $this->clock->now()->modify(self::DEFAULT_PERIOD);
        }

        try {
            return new \DateTimeImmutable($since);
        } catch (\Exception) {
            throw new \InvalidArgumentException(sprintf('Invalid "since" date: "%s". Use ISO 8601, e.g. 2026-11-17T09:00:00+00:00.', $since));
        }
    }
}
