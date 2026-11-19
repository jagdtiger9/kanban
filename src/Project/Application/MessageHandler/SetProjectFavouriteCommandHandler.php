<?php

declare(strict_types=1);

namespace App\Project\Application\MessageHandler;

use App\Project\Application\Message\Command\SetProjectFavouriteCommand;
use App\Project\Application\Service\MembershipGuard;
use App\Project\Domain\Exception\ProjectNotFoundException;
use App\Project\Domain\Repository\ProjectMemberRepository;
use App\Project\Domain\Repository\ProjectRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SetProjectFavouriteCommandHandler
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private ProjectMemberRepository $projectMemberRepository,
        private MembershipGuard $guard,
    ) {
    }

    public function __invoke(SetProjectFavouriteCommand $command): void
    {
        $projectId = Uuid::fromString($command->projectId);
        $actorId = Uuid::fromString($command->actorId);

        if ($this->projectRepository->findById($projectId) === null) {
            throw new ProjectNotFoundException('Project not found');
        }

        $member = $this->guard->requireMembership($projectId, $actorId);

        if ($command->isFavourite) {
            $member->favourite();
        } else {
            $member->unfavourite();
        }
        $this->projectMemberRepository->save($member);
    }
}
