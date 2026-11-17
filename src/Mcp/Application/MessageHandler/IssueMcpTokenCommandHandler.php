<?php

declare(strict_types=1);

namespace App\Mcp\Application\MessageHandler;

use App\Mcp\Application\Message\Command\IssueMcpTokenCommand;
use App\Mcp\Application\Service\IssuedMcpToken;
use App\Mcp\Domain\Repository\McpTokenRepository;
use App\Mcp\Domain\ValueObject\McpToken;
use App\Mcp\Domain\ValueObject\McpTokenFormat;
use Psr\Clock\ClockInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class IssueMcpTokenCommandHandler
{
    private const int SECRET_BYTES = 32;
    private const string LIFETIME = '+90 days';

    public function __construct(
        private McpTokenRepository $tokenRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(IssueMcpTokenCommand $command): IssuedMcpToken
    {
        $userId = Uuid::fromString($command->actorId);
        $secret = rtrim(strtr(base64_encode(random_bytes(self::SECRET_BYTES)), '+/', '-_'), '=');
        $expiresAt = $this->clock->now()->modify(self::LIFETIME);

        $this->tokenRepository->save(new McpToken($userId, McpToken::hashSecret($secret), $expiresAt));

        return new IssuedMcpToken(McpTokenFormat::compose($userId, $secret), $expiresAt);
    }
}
