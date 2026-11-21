<?php

declare(strict_types=1);

namespace App\Board\Application\MessageHandler;

use App\Board\Application\Message\Command\SetCardExecutorCommand;
use App\Board\Application\Service\CardGuard;
use App\Board\Domain\Repository\CardRepository;
use App\Project\Domain\Entity\ProjectMember;
use App\Project\Domain\Exception\ProjectMemberNotFoundException;
use App\Project\Domain\Repository\ProjectMemberRepository;
use App\User\Domain\Entity\User;
use App\User\Domain\Repository\UserRepository;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SetCardExecutorCommandHandler
{
    public function __construct(
        private CardGuard $guard,
        private ProjectMemberRepository $projectMemberRepository,
        private UserRepository $userRepository,
        private CardRepository $cardRepository,
    ) {
    }

    public function __invoke(SetCardExecutorCommand $command): void
    {
        $actorId = Uuid::fromString($command->actorId);

        $card = $this->guard->requireCard(Uuid::fromString($command->cardId), $actorId);

        if ($command->executorId === null || $command->executorId === '') {
            $current = $card->executorId();
            if ($current !== null) {
                $card->unassignExecutor($this->requireUser($current), $actorId);
            }
            $this->cardRepository->save($card);

            return;
        }

        $executorId = Uuid::fromString($command->executorId);
        $board = $this->guard->resolveBoard($card);
        $members = $this->projectMemberRepository->findByProject($board->projectId());
        $memberUserIds = array_map(
            static fn(ProjectMember $member): string => (string)$member->userId(),
            $members,
        );
        if (!\in_array((string)$executorId, $memberUserIds, true)) {
            throw new ProjectMemberNotFoundException('User is not a member of this project');
        }

        $card->assignExecutor($this->requireUser($executorId), $actorId);
        $this->cardRepository->save($card);
    }

    private function requireUser(UuidInterface $id): User
    {
        $user = $this->userRepository->findById($id);
        if ($user === null) {
            throw new \LogicException('User must exist for every membership');
        }

        return $user;
    }
}
