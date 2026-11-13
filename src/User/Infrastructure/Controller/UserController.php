<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Controller;

use Aljerom\SymfonyBoilerplate\CQRS\QueryBus;
use App\User\Application\Message\Query\SearchUsersQuery;
use App\User\Domain\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class UserController extends AbstractController
{
    public function __construct(
        private QueryBus $queryBus,
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
}
