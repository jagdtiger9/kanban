<?php

declare(strict_types=1);

namespace App\Mcp\Infrastructure\Tool;

use Aljerom\SymfonyBoilerplate\CQRS\Command;
use Aljerom\SymfonyBoilerplate\CQRS\CommandBus;
use Aljerom\SymfonyBoilerplate\CQRS\Query;
use Aljerom\SymfonyBoilerplate\CQRS\QueryBus;
use App\Shared\Infrastructure\Security\SecurityUser;
use Mcp\Exception\ToolCallException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Runs existing CQRS messages on behalf of the MCP token owner. Tools add no access
 * rules of their own: the handlers' guards decide. The buses do not validate, so the
 * message constraints are checked here, like MessageValueResolver does for HTTP.
 */
final class McpToolRunner
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus,
        private ValidatorInterface $validator,
        private Security $security,
        #[Autowire('%frontend_url%')]
        private string $frontendUrl,
    ) {
    }

    public function actorId(): string
    {
        $user = $this->security->getUser();
        if (!$user instanceof SecurityUser) {
            throw new \LogicException('MCP tools require an authenticated user');
        }

        return (string)$user->getId();
    }

    public function query(Query $query): mixed
    {
        return $this->run($query, fn(): mixed => $this->queryBus->query($query));
    }

    public function command(Command $command): mixed
    {
        return $this->run($command, fn(): mixed => $this->commandBus->dispatch($command));
    }

    public function projectUrl(string $projectId): string
    {
        return rtrim($this->frontendUrl, '/') . '/projects/' . $projectId;
    }

    /**
     * @param callable(): mixed $dispatch
     */
    private function run(object $message, callable $dispatch): mixed
    {
        $violations = $this->validator->validate($message);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
            }

            throw new ToolCallException('Invalid input — ' . implode('; ', $errors));
        }

        try {
            return $dispatch();
        } catch (\InvalidArgumentException $e) {
            throw new ToolCallException($e->getMessage(), previous: $e);
        } catch (\RuntimeException $e) {
            // domain rule violations (not found, forbidden, invariants) are shown to the
            // agent; anything else stays an internal error
            if (str_contains($e::class, '\\Domain\\Exception\\')) {
                throw new ToolCallException($e->getMessage(), previous: $e);
            }

            throw $e;
        }
    }
}
