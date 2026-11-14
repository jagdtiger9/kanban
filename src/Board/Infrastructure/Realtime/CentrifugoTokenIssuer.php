<?php

declare(strict_types=1);

namespace App\Board\Infrastructure\Realtime;

use App\Board\Application\Service\RealtimeToken;
use App\Board\Application\Service\RealtimeTokenIssuerInterface;
use Firebase\JWT\JWT;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsAlias(RealtimeTokenIssuerInterface::class)]
final class CentrifugoTokenIssuer implements RealtimeTokenIssuerInterface
{
    private const TTL_SECONDS = 600;

    public function __construct(
        #[Autowire('%centrifugo_ws_url%')]
        private string $wsUrl,
        #[Autowire('%centrifugo_token_secret%')]
        private string $tokenSecret,
    ) {
    }

    public function issue(UuidInterface $userId, string $channel): RealtimeToken
    {
        $sub = (string) $userId;
        $expiresAt = time() + self::TTL_SECONDS;

        return new RealtimeToken(
            $this->wsUrl,
            $channel,
            JWT::encode(['sub' => $sub, 'exp' => $expiresAt], $this->tokenSecret, 'HS256'),
            JWT::encode(['sub' => $sub, 'channel' => $channel, 'exp' => $expiresAt], $this->tokenSecret, 'HS256'),
            $expiresAt,
        );
    }
}
