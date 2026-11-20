<?php

declare(strict_types=1);

namespace App\Tests\Unit\UserEvent\Domain\Service;

use Aljerom\SymfonyBoilerplate\DomainEvents\DomainEventInterface;
use App\Board\Domain\Event\CardAssigned;
use App\UserEvent\Domain\Enum\UserEventType;
use App\UserEvent\Domain\Service\RecipientResolver;
use App\UserEvent\Domain\Service\RecipientRule;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

final class RecipientResolverTest extends TestCase
{
    public function testReturnsNullWhenNoRuleSupports(): void
    {
        $resolver = new RecipientResolver([
            $this->rule(false, UserEventType::CardAssigned, [Uuid::uuid4()]),
        ]);

        self::assertNull($resolver->resolve($this->event()));
    }

    public function testUnionsAndDedupesRecipientsAcrossSupportingRules(): void
    {
        $shared = Uuid::uuid4();
        $other = Uuid::uuid4();
        $resolver = new RecipientResolver([
            $this->rule(true, UserEventType::CardAssigned, [$shared, $other]),
            $this->rule(true, UserEventType::CommentCreated, [$shared]),
        ]);

        $resolved = $resolver->resolve($this->event());

        self::assertNotNull($resolved);
        self::assertSame(UserEventType::CardAssigned, $resolved->type());
        self::assertCount(2, $resolved->userIds());
        self::assertTrue($shared->equals($resolved->userIds()[0]));
        self::assertTrue($other->equals($resolved->userIds()[1]));
    }

    public function testDropsTheActor(): void
    {
        $actor = Uuid::uuid4();
        $recipient = Uuid::uuid4();
        $resolver = new RecipientResolver([
            $this->rule(true, UserEventType::CardAssigned, [$actor, $recipient]),
        ]);

        $resolved = $resolver->resolve($this->event((string) $actor));

        self::assertNotNull($resolved);
        self::assertCount(1, $resolved->userIds());
        self::assertTrue($recipient->equals($resolved->userIds()[0]));
    }

    public function testKeepsEveryoneWhenActorIsNull(): void
    {
        $resolver = new RecipientResolver([
            $this->rule(true, UserEventType::CardAssigned, [Uuid::uuid4(), Uuid::uuid4()]),
        ]);

        $resolved = $resolver->resolve($this->event());

        self::assertNotNull($resolved);
        self::assertCount(2, $resolved->userIds());
    }

    private function event(?string $actorId = null): CardAssigned
    {
        return new CardAssigned((string) Uuid::uuid4(), (string) Uuid::uuid4(), 'User', $actorId);
    }

    /** @param list<UuidInterface> $recipients */
    private function rule(bool $supports, UserEventType $type, array $recipients): RecipientRule
    {
        return new class ($supports, $type, $recipients) implements RecipientRule {
            /** @param list<UuidInterface> $recipients */
            public function __construct(
                private bool $supports,
                private UserEventType $type,
                private array $recipients,
            ) {
            }

            public function supports(DomainEventInterface $event): bool
            {
                return $this->supports;
            }

            public function type(): UserEventType
            {
                return $this->type;
            }

            public function recipients(DomainEventInterface $event): array
            {
                return $this->recipients;
            }
        };
    }
}
