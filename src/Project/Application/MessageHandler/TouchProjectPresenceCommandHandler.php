<?php

declare(strict_types=1);

namespace App\Project\Application\MessageHandler;

use App\Project\Application\Message\Command\TouchProjectPresenceCommand;
use App\Project\Application\Service\MembershipGuard;
use App\Project\Domain\Exception\ProjectNotFoundException;
use App\Project\Domain\Repository\ProjectMemberRepository;
use App\Project\Domain\Repository\ProjectRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class TouchProjectPresenceCommandHandler
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private ProjectMemberRepository $projectMemberRepository,
        private MembershipGuard $guard,
    ) {
    }

    public function __invoke(TouchProjectPresenceCommand $command): void
    {
        $projectId = Uuid::fromString($command->projectId);
        $actorId = Uuid::fromString($command->actorId);

        if ($this->projectRepository->findById($projectId) === null) {
            throw new ProjectNotFoundException('Project not found');
        }

        $this->guard->requireMember($projectId, $actorId);

        $member = $this->projectMemberRepository->findByProjectAndUser($projectId, $actorId);
        if ($member === null) {
            return;
        }

        $member->touchPresence(new \DateTimeImmutable());
        $this->projectMemberRepository->save($member);
    }
}
