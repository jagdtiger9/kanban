<?php

declare(strict_types=1);

namespace App\Board\Application\MessageHandler;

use App\Board\Application\Message\Query\GetRealtimeTokenQuery;
use App\Board\Application\Service\RealtimeToken;
use App\Board\Application\Service\RealtimeTokenIssuerInterface;
use App\Project\Application\Service\MembershipGuard;
use App\Project\Domain\Exception\ProjectNotFoundException;
use App\Project\Domain\Repository\ProjectRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetRealtimeTokenQueryHandler
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private MembershipGuard $guard,
        private RealtimeTokenIssuerInterface $issuer,
    ) {
    }

    public function __invoke(GetRealtimeTokenQuery $query): RealtimeToken
    {
        $projectId = Uuid::fromString($query->projectId);
        $actorId = Uuid::fromString($query->actorId);

        if ($this->projectRepository->findById($projectId) === null) {
            throw new ProjectNotFoundException('Project not found');
        }
        $this->guard->requireMember($projectId, $actorId);

        return $this->issuer->issue($actorId, 'kanban:' . $projectId);
    }
}
