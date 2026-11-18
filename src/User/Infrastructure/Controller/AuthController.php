<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Controller;

use Aljerom\SymfonyBoilerplate\CQRS\CommandBus;
use App\Shared\Infrastructure\Security\SecurityUser;
use App\User\Application\Message\Command\ChangePasswordCommand;
use App\User\Application\Message\Command\ConfirmEmailCommand;
use App\User\Application\Message\Command\RegisterCommand;
use App\User\Domain\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;

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

    public function me(Security $security): JsonResponse
    {
        return $this->json($this->userPayload($this->domainUser($security->getUser())));
    }

    public function register(RegisterCommand $command): JsonResponse
    {
        $this->commandBus->dispatch($command);

        return $this->json(
            ['message' => 'Registration successful. Check your inbox to confirm your email address.'],
            Response::HTTP_CREATED,
        );
    }

    public function confirm(ConfirmEmailCommand $command): JsonResponse
    {
        $this->commandBus->dispatch($command);

        return $this->json(['message' => 'Your email address has been confirmed. You can now sign in.']);
    }

    public function changePassword(ChangePasswordCommand $command, Security $security): JsonResponse
    {
        $command->actorId = $this->actorId($security);
        $this->commandBus->dispatch($command);

        return $this->json(['message' => 'Your password has been updated.']);
    }

    private function actorId(Security $security): string
    {
        $user = $security->getUser();
        if (!$user instanceof SecurityUser) {
            throw new \LogicException('Unexpected user type');
        }

        return (string) $user->getId();
    }

    private function domainUser(?UserInterface $user): User
    {
        if (!$user instanceof SecurityUser) {
            throw new \LogicException('Unexpected user type');
        }

        return $user->user();
    }

    /**
     * @return array{user: array{id: string, email: string, displayName: string, roles: list<string>, avatarVersion: ?string}, isAuth: true}
     */
    private function userPayload(User $user): array
    {
        return [
            'user' => [
                'id' => (string) $user->id(),
                'email' => $user->email(),
                'displayName' => $user->displayName(),
                'roles' => $user->roles(),
                'avatarVersion' => $user->avatarVersion(),
            ],
            'isAuth' => true,
        ];
    }
}
