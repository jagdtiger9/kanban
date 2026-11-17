<?php

declare(strict_types=1);

namespace App\Mcp\Infrastructure\Security;

use App\Mcp\Application\Service\McpTokenVerifier;
use App\Shared\Infrastructure\Security\SecurityUser;
use App\User\Domain\Repository\UserRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Stateless `Authorization: Bearer knb_...` authentication for the `mcp` firewall, plus the
 * MCP rate limits: failed attempts per IP and requests per user.
 */
final class McpTokenAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private McpTokenVerifier $verifier,
        private UserRepository $userRepository,
        #[Autowire(service: 'limiter.mcp_requests')]
        private RateLimiterFactoryInterface $requestLimiter,
        #[Autowire(service: 'limiter.mcp_failed_auth')]
        private RateLimiterFactoryInterface $failedAuthLimiter,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return true;
    }

    public function authenticate(Request $request): Passport
    {
        $failedAuth = $this->failedAuthLimiter->create($request->getClientIp() ?? 'unknown');
        $failedState = $failedAuth->consume(0);
        if ($failedState->getRemainingTokens() === 0) {
            throw new TooManyMcpRequestsException(self::retryAfter($failedState));
        }

        $userId = $this->verifier->verify($this->bearerToken($request));
        if ($userId === null) {
            $failedAuth->consume();

            throw new CustomUserMessageAuthenticationException('Invalid or expired MCP token');
        }

        $requests = $this->requestLimiter->create($userId->toString())->consume();
        if (!$requests->isAccepted()) {
            throw new TooManyMcpRequestsException(self::retryAfter($requests));
        }

        return new SelfValidatingPassport(new UserBadge($userId->toString(), function (string $id): SecurityUser {
            $user = $this->userRepository->findById(Uuid::fromString($id));
            if ($user === null || !$user->isConfirmed()) {
                throw new UserNotFoundException('MCP token owner is missing or unconfirmed');
            }

            return SecurityUser::fromUser($user);
        }));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if ($exception instanceof TooManyMcpRequestsException) {
            return new JsonResponse(
                ['message' => $exception->getMessageKey()],
                Response::HTTP_TOO_MANY_REQUESTS,
                ['Retry-After' => (string)$exception->retryAfterSeconds()],
            );
        }

        return self::unauthorized($exception instanceof CustomUserMessageAuthenticationException
            ? $exception->getMessageKey()
            : 'Invalid or expired MCP token');
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return self::unauthorized('MCP token required');
    }

    private function bearerToken(Request $request): string
    {
        $header = (string)$request->headers->get('Authorization', '');

        return str_starts_with($header, 'Bearer ') ? trim(substr($header, 7)) : '';
    }

    private static function unauthorized(string $message): JsonResponse
    {
        return new JsonResponse(['message' => $message], Response::HTTP_UNAUTHORIZED, ['WWW-Authenticate' => 'Bearer']);
    }

    private static function retryAfter(RateLimit $limit): int
    {
        return max(1, $limit->getRetryAfter()->getTimestamp() - time());
    }
}
