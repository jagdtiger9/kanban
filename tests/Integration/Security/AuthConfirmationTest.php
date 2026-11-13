<?php

declare(strict_types=1);

namespace App\Tests\Integration\Security;

use App\Tests\Integration\DatabaseTestCase;
use App\User\Domain\Entity\User;
use App\User\Domain\Repository\UserRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

final class AuthConfirmationTest extends DatabaseTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $this->prepareDatabase();
    }

    public function testConfirmationLinkActivatesAccountAndAllowsLogin(): void
    {
        $user = $this->seedPendingUser('secret-token');

        $this->postJson('/api/auth/confirm', ['token' => 'secret-token']);

        self::assertResponseStatusCodeSame(200);
        self::assertTrue($this->reload($user)->isConfirmed());

        $this->postJson('/api/auth/login', ['email' => 'alice@example.com', 'password' => 'secret123']);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('alice@example.com', $this->responsePayload()['user']['email']);
    }

    public function testUnconfirmedUserCannotLogIn(): void
    {
        $this->seedPendingUser('secret-token');

        $this->postJson('/api/auth/login', ['email' => 'alice@example.com', 'password' => 'secret123']);

        self::assertResponseStatusCodeSame(401);
        self::assertStringContainsString('confirm', $this->responsePayload()['message']);
    }

    public function testUnknownTokenIsRejected(): void
    {
        $user = $this->seedPendingUser('secret-token');

        $this->postJson('/api/auth/confirm', ['token' => 'not-the-token']);

        self::assertResponseStatusCodeSame(400);
        self::assertFalse($this->reload($user)->isConfirmed());
    }

    public function testExpiredTokenIsRejected(): void
    {
        $user = $this->seedPendingUser('secret-token', '-1 hour');

        $this->postJson('/api/auth/confirm', ['token' => 'secret-token']);

        self::assertResponseStatusCodeSame(400);
        self::assertFalse($this->reload($user)->isConfirmed());
    }

    public function testTokenCannotBeUsedTwice(): void
    {
        $this->seedPendingUser('secret-token');

        $this->postJson('/api/auth/confirm', ['token' => 'secret-token']);
        self::assertResponseStatusCodeSame(200);

        $this->postJson('/api/auth/confirm', ['token' => 'secret-token']);
        self::assertResponseStatusCodeSame(400);
    }

    private function seedPendingUser(string $token, string $expiresIn = '+24 hours'): User
    {
        $hasher = self::getContainer()
            ->get(PasswordHasherFactoryInterface::class)
            ->getPasswordHasher(User::class);

        $user = User::create(Uuid::uuid4(), 'alice@example.com', $hasher->hash('secret123'), 'Alice');
        $user->beginEmailConfirmation(hash('sha256', $token), new \DateTimeImmutable($expiresIn));
        $this->repo(UserRepository::class)->save($user);

        return $user;
    }

    private function reload(User $user): User
    {
        $reloaded = $this->repo(UserRepository::class)->findById($user->id());
        self::assertNotNull($reloaded);

        return $reloaded;
    }

    /**
     * @return array<string, mixed>
     */
    private function responsePayload(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function postJson(string $path, array $body): void
    {
        $this->client->request(
            'POST',
            $path,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            json_encode($body, JSON_THROW_ON_ERROR),
        );
    }
}
