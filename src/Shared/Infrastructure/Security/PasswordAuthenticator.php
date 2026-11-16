<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\InteractiveAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * JSON email/password login. Interactive so that successful authentication
 * migrates the session token (session fixation protection).
 */
final class PasswordAuthenticator extends AbstractAuthenticator implements InteractiveAuthenticatorInterface
{
    private const string LOGIN_PATH = '/api/auth/login';

    public function __construct(
        private SecurityUserProvider $userProvider,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->getPathInfo() === self::LOGIN_PATH && $request->isMethod(Request::METHOD_POST);
    }

    public function authenticate(Request $request): Passport
    {
        $payload = $request->getPayload();
        $email = trim(strtolower((string) $payload->get('email')));
        $password = (string) $payload->get('password');

        if ($email === '') {
            throw new CustomUserMessageAuthenticationException('No user credentials provided');
        }

        try {
            $user = $this->userProvider->loadUserByIdentifier($email);
        } catch (UserNotFoundException) {
            // one message for both cases so account existence is not leaked
            throw new CustomUserMessageAuthenticationException('Invalid credentials');
        }

        if (!$this->passwordHasher->isPasswordValid($user, $password)) {
            throw new CustomUserMessageAuthenticationException('Invalid credentials');
        }

        // check after the password so an unconfirmed account isn't distinguishable
        // from a wrong password for someone who doesn't know the credentials
        if (!$user->user()->isConfirmed()) {
            throw new CustomUserMessageAuthenticationException('Please confirm your email address before signing in.');
        }

        $passport = new SelfValidatingPassport(
            new UserBadge($user->getUserIdentifier(), fn() => $user),
        );
        if ($payload->getBoolean('remember_me')) {
            $passport->addBadge(new RememberMeBadge());
        }

        return $passport;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // let the request continue to the login controller
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse(['message' => $exception->getMessage()], Response::HTTP_UNAUTHORIZED);
    }

    public function isInteractive(): bool
    {
        return true;
    }
}
