<?php

declare(strict_types=1);

namespace App\Call\Infrastructure\Realtime;

use App\Call\Application\Service\CallChannel;
use App\Call\Application\Service\CallToken;
use App\Call\Application\Service\CallTokenIssuer;
use Firebase\JWT\JWT;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsAlias(CallTokenIssuer::class)]
final class CentrifugoCallTokenIssuer implements CallTokenIssuer
{
    private const TTL_SECONDS = 600;

    public function __construct(
        #[Autowire('%centrifugo_ws_url%')]
        private string $wsUrl,
        #[Autowire('%centrifugo_token_secret%')]
        private string $tokenSecret,
    ) {
    }

    public function issue(UuidInterface $userId): CallToken
    {
        $sub = (string) $userId;
        $channel = CallChannel::forUser($sub);
        $expiresAt = time() + self::TTL_SECONDS;

        return new CallToken(
            $this->wsUrl,
            $channel,
            JWT::encode(['sub' => $sub, 'exp' => $expiresAt], $this->tokenSecret, 'HS256'),
            JWT::encode(['sub' => $sub, 'channel' => $channel, 'exp' => $expiresAt], $this->tokenSecret, 'HS256'),
            $expiresAt,
        );
    }
}
