<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Controller;

use Aljerom\SymfonyBoilerplate\CQRS\CommandBus;
use App\Shared\Infrastructure\Security\PasswordAuthenticator;
use App\Shared\Infrastructure\Security\SecurityUser;
use App\User\Application\Message\Command\RegisterCommand;
use App\User\Domain\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AuthController extends AbstractController
{
    public function __construct(
        private CommandBus $commandBus,
    ) {
    }

    public function login(UserInterface $user): JsonResponse
    {
        return $this->json($this->userPayload($this->domainUser($user)));
    }

    #[IsGranted('ROLE_USER')]
    public function me(Security $security): JsonResponse
    {
        return $this->json($this->userPayload($this->domainUser($security->getUser())));
    }

    public function register(RegisterCommand $command, Security $security): JsonResponse
    {
        $user = $this->commandBus->dispatch($command);

        // auto-login right after registration; the explicit authenticator name
        // is required because the firewall has more than one
        $security->login(SecurityUser::fromUser($user), PasswordAuthenticator::class);

        return $this->json($this->userPayload($user), Response::HTTP_CREATED);
    }

    private function domainUser(?UserInterface $user): User
    {
        if (!$user instanceof SecurityUser) {
            throw new \LogicException('Unexpected user type');
        }

        return $user->user();
    }

    /**
     * @return array{user: array{id: string, email: string, displayName: string, roles: list<string>}, isAuth: true}
     */
    private function userPayload(User $user): array
    {
        return [
            'user' => [
                'id' => (string) $user->id(),
                'email' => $user->email(),
                'displayName' => $user->displayName(),
                'roles' => $user->roles(),
            ],
            'isAuth' => true,
        ];
    }
}
