<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Controller;

use Aljerom\SymfonyBoilerplate\CQRS\CommandBus;
use Aljerom\SymfonyBoilerplate\CQRS\QueryBus;
use App\Shared\Infrastructure\Security\SecurityUser;
use App\User\Application\Message\Command\ConfirmUserAccountCommand;
use App\User\Application\Message\Query\ListUsersQuery;
use App\User\Application\Message\Query\SearchUsersQuery;
use App\User\Domain\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;

final class UserController extends AbstractController
{
    public function __construct(
        private QueryBus $queryBus,
        private CommandBus $commandBus,
    ) {
    }

    public function search(SearchUsersQuery $query): JsonResponse
    {
        return $this->json(
            array_map(
                fn(User $user): array => [
                    'id' => (string)$user->id(),
                    'email' => $user->email(),
                    'displayName' => $user->displayName(),
                    'avatarVersion' => $user->avatarVersion(),
                ],
                $this->queryBus->query($query),
            )
        );
    }

    public function list(ListUsersQuery $query, Security $security): JsonResponse
    {
        $query->actorId = $this->actorId($security);

        return $this->json(
            array_map(
                fn(User $user): array => $this->adminUserPayload($user),
                $this->queryBus->query($query),
            )
        );
    }

    public function confirmUser(ConfirmUserAccountCommand $command, Security $security): JsonResponse
    {
        $command->actorId = $this->actorId($security);

        return $this->json($this->adminUserPayload($this->commandBus->dispatch($command)));
    }

    private function actorId(Security $security): string
    {
        $user = $security->getUser();
        if (!$user instanceof SecurityUser) {
            throw new \LogicException('Unexpected user type');
        }

        return (string)$user->getId();
    }

    /**
     * @return array{id: string, email: string, displayName: string, isGlobalAdmin: bool, confirmed: bool, createdAt: string, avatarVersion: ?string}
     */
    private function adminUserPayload(User $user): array
    {
        return [
            'id' => (string)$user->id(),
            'email' => $user->email(),
            'displayName' => $user->displayName(),
            'isGlobalAdmin' => $user->isGlobalAdmin(),
            'confirmed' => $user->isConfirmed(),
            'createdAt' => $user->createdAt()->format(\DateTimeInterface::ATOM),
            'avatarVersion' => $user->avatarVersion(),
        ];
    }
}
