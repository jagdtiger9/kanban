<?php

declare(strict_types=1);

namespace App\Tests\Integration\Mcp;

use App\Mcp\Application\Message\Command\IssueMcpTokenCommand;
use App\Mcp\Application\Service\IssuedMcpToken;
use App\Shared\Infrastructure\Security\SecurityUser;
use App\Tests\Integration\DatabaseTestCase;
use App\User\Domain\Entity\User;
use App\User\Domain\Repository\UserRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * The MCP server lives under /api (/api/mcp) so any web server that already passes
 * /api to PHP serves it. Only the exact path belongs to the token firewall;
 * /api/mcp/token stays a session route (covered by AclMatrixTest).
 */
final class McpEndpointTest extends DatabaseTestCase
{
    private const string INITIALIZE = '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"test","version":"0"}}}';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        // createClient() must run before the kernel is booted any other way.
        $this->client = self::createClient();
        $this->prepareDatabase();
    }

    public function testWithoutTokenAnswersUnauthorizedJson(): void
    {
        $this->post([]);

        $response = $this->client->getResponse();
        self::assertSame(401, $response->getStatusCode());
        self::assertSame('Bearer', $response->headers->get('WWW-Authenticate'));
        self::assertStringContainsString('application/json', (string)$response->headers->get('Content-Type'));
    }

    public function testSessionLoginIsNotEnough(): void
    {
        $this->client->loginUser(SecurityUser::fromUser($this->createUser()));

        $this->post([]);

        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    public function testValidTokenReachesTheMcpServer(): void
    {
        $user = $this->createUser();
        $token = $this->issueToken($user);

        $this->post(['HTTP_AUTHORIZATION' => 'Bearer ' . $token]);

        $response = $this->client->getResponse();
        self::assertSame(200, $response->getStatusCode(), (string)$response->getContent());
        self::assertStringContainsString('kanban', (string)$response->getContent());
        self::assertStringContainsString(
            sprintf('"version":"%s"', self::getContainer()->getParameter('mcp.version')),
            (string)$response->getContent(),
        );
    }

    /**
     * @param array<string, string> $server
     */
    private function post(array $server): void
    {
        $this->client->request(
            'POST',
            '/api/mcp',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json, text/event-stream'] + $server,
            self::INITIALIZE,
        );
    }

    private function createUser(): User
    {
        $user = User::create(Uuid::uuid4(), 'agent@mcp.test', 'not-used', 'Agent');
        $user->confirmAccount();
        $this->repo(UserRepository::class)->save($user);

        return $user;
    }

    private function issueToken(User $user): string
    {
        /** @var MessageBusInterface $bus */
        $bus = self::getContainer()->get('command.bus');
        $envelope = $bus->dispatch(new IssueMcpTokenCommand($user->id()->toString()));
        /** @var IssuedMcpToken $issued */
        $issued = $envelope->last(HandledStamp::class)?->getResult();

        return $issued->token;
    }
}
