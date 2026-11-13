<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Controller;

use Aljerom\SymfonyBoilerplate\CQRS\CommandBus;
use Aljerom\SymfonyBoilerplate\CQRS\QueryBus;
use App\Shared\Infrastructure\Security\ProjectAccessVoter;
use App\User\Application\Message\Command\ConfirmUserAccountCommand;
use App\User\Application\Message\Command\DemoteUserFromGlobalAdminCommand;
use App\User\Application\Message\Command\PromoteUserToGlobalAdminCommand;
use App\User\Application\Message\Query\ListUsersQuery;
use App\User\Application\Message\Query\SearchUsersQuery;
use App\User\Domain\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class UserController extends AbstractController
{
    public function __construct(
        private QueryBus $queryBus,
        private CommandBus $commandBus,
    ) {
    }

    #[IsGranted('ROLE_USER')]
    public function search(SearchUsersQuery $query): JsonResponse
    {
        return $this->json(array_map(
            fn (User $user): array => [
                'id' => (string) $user->id(),
                'email' => $user->email(),
                'displayName' => $user->displayName(),
            ],
            $this->queryBus->query($query),
        ));
    }

    public function list(ListUsersQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProjectAccessVoter::GLOBAL_ADMIN);

        return $this->json(array_map(
            fn (User $user): array => $this->adminUserPayload($user),
            $this->queryBus->query($query),
        ));
    }

    public function confirmUser(ConfirmUserAccountCommand $command): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProjectAccessVoter::GLOBAL_ADMIN);

        return $this->json($this->adminUserPayload($this->commandBus->dispatch($command)));
    }

    public function promote(PromoteUserToGlobalAdminCommand $command): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProjectAccessVoter::GLOBAL_ADMIN);

        return $this->json($this->adminUserPayload($this->commandBus->dispatch($command)));
    }

    public function demote(DemoteUserFromGlobalAdminCommand $command): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProjectAccessVoter::GLOBAL_ADMIN);

        return $this->json($this->adminUserPayload($this->commandBus->dispatch($command)));
    }

    /**
     * @return array{id: string, email: string, displayName: string, isGlobalAdmin: bool, confirmed: bool, createdAt: string}
     */
    private function adminUserPayload(User $user): array
    {
        return [
            'id' => (string) $user->id(),
            'email' => $user->email(),
            'displayName' => $user->displayName(),
            'isGlobalAdmin' => $user->isGlobalAdmin(),
            'confirmed' => $user->isConfirmed(),
            'createdAt' => $user->createdAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
