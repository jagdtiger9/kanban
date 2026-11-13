<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Security;

use App\Shared\Infrastructure\Security\PasswordAuthenticator;
use App\Shared\Infrastructure\Security\SecurityUser;
use App\Shared\Infrastructure\Security\SecurityUserProvider;
use App\User\Domain\Entity\User;
use App\User\Domain\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

final class PasswordAuthenticatorTest extends TestCase
{
    public function testUnconfirmedUserWithValidPasswordIsRejected(): void
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');
        $authenticator = $this->authenticator($user, passwordValid: true);

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('Please confirm your email address');

        $authenticator->authenticate($this->loginRequest());
    }

    public function testUnconfirmedUserWithWrongPasswordGetsGenericError(): void
    {
        // wrong password must not reveal the account is merely unconfirmed
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');
        $authenticator = $this->authenticator($user, passwordValid: false);

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('Invalid credentials');

        $authenticator->authenticate($this->loginRequest());
    }

    public function testConfirmedUserWithValidPasswordGetsPassport(): void
    {
        $user = User::create(Uuid::uuid4(), 'alice@example.com', 'hash', 'Alice');
        $user->confirmAccount();
        $authenticator = $this->authenticator($user, passwordValid: true);

        $passport = $authenticator->authenticate($this->loginRequest());

        self::assertInstanceOf(SecurityUser::class, $passport->getUser());
        self::assertSame('alice@example.com', $passport->getUser()->getUserIdentifier());
    }

    public function testUnknownEmailIsRejected(): void
    {
        $authenticator = $this->authenticator(null, passwordValid: true);

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('Invalid credentials');

        $authenticator->authenticate($this->loginRequest());
    }

    private function authenticator(?User $user, bool $passwordValid): PasswordAuthenticator
    {
        $repo = $this->createMock(UserRepository::class);
        $repo->method('findByEmail')->willReturn($user);

        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->method('isPasswordValid')->willReturn($passwordValid);

        return new PasswordAuthenticator(new SecurityUserProvider($repo), $hasher);
    }

    private function loginRequest(): Request
    {
        return Request::create(
            '/api/auth/login',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['email' => 'alice@example.com', 'password' => 'secret123'], JSON_THROW_ON_ERROR),
        );
    }
}
