<?php

declare(strict_types=1);

namespace App\Call\Infrastructure\Turn;

use App\Call\Application\Service\IceServerProvider;
use Psr\Clock\ClockInterface;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * ICE servers from config. TURN entries get temporary credentials by the
 * "TURN REST" scheme coturn supports with use-auth-secret:
 * username = "<expiry unix time>:<user id>", password = base64(HMAC-SHA1(secret, username)).
 */
#[AsAlias(IceServerProvider::class)]
final class TurnRestIceServerProvider implements IceServerProvider
{
    public function __construct(
        private ClockInterface $clock,
        #[Autowire('%call.ice_urls%')]
        private string $iceUrls,
        #[Autowire('%call.turn_secret%')]
        private string $turnSecret,
        #[Autowire('%call.turn_credential_ttl%')]
        private int $credentialTtl,
    ) {
    }

    public function forUser(UuidInterface $userId): array
    {
        $stun = [];
        $turn = [];
        foreach (explode(',', $this->iceUrls) as $url) {
            $url = trim($url);
            if (str_starts_with($url, 'stun:')) {
                $stun[] = $url;
            } elseif (str_starts_with($url, 'turn:') || str_starts_with($url, 'turns:')) {
                $turn[] = $url;
            }
        }

        $servers = [];
        if ($stun !== []) {
            $servers[] = ['urls' => $stun];
        }
        if ($turn !== [] && $this->turnSecret !== '') {
            $username = ($this->clock->now()->getTimestamp() + $this->credentialTtl) . ':' . $userId;
            $servers[] = [
                'urls' => $turn,
                'username' => $username,
                'credential' => base64_encode(hash_hmac('sha1', $username, $this->turnSecret, true)),
            ];
        }

        return $servers;
    }
}
