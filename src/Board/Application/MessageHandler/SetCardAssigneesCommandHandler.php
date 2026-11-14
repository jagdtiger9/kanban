<?php

declare(strict_types=1);

namespace App\Board\Application\MessageHandler;

use App\Board\Application\Message\Command\SetCardAssigneesCommand;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Repository\CardRepository;
use App\Project\Domain\Entity\ProjectMember;
use App\Project\Domain\Exception\ProjectMemberNotFoundException;
use App\Project\Domain\Repository\ProjectMemberRepository;
use App\User\Domain\Repository\UserRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SetCardAssigneesCommandHandler
{
    public function __construct(
        private CardGuard $guard,
        private ProjectMemberRepository $projectMemberRepository,
        private UserRepository $userRepository,
        private CardRepository $cardRepository,
    ) {
    }

    public function __invoke(SetCardAssigneesCommand $command): void
    {
        $actorId = Uuid::fromString($command->actorId);

        $card = $this->guard->requireCard(Uuid::fromString($command->cardId), $actorId);
        $board = $this->guard->resolveBoard($card);

        $members = $this->projectMemberRepository->findByProject($board->projectId());
        $memberUserIds = array_map(
            static fn (ProjectMember $member): string => (string) $member->userId(),
            $members,
        );
        foreach ($command->userIds as $userId) {
            if (!\in_array($userId, $memberUserIds, true)) {
                throw new ProjectMemberNotFoundException('User is not a member of this project');
            }
        }

        foreach ($card->assignees() as $existing) {
            if (!\in_array((string) $existing->id(), $command->userIds, true)) {
                $card->unassign($existing, $actorId);
            }
        }
        foreach ($command->userIds as $userId) {
            $user = $this->userRepository->findById(Uuid::fromString($userId));
            if ($user === null) {
                throw new \LogicException('User must exist for every membership');
            }
            $card->assign($user, $actorId);
        }

        $this->cardRepository->save($card);
    }
}
