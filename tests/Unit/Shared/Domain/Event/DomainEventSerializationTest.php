<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain\Event;

use Aljerom\SymfonyBoilerplate\DomainEvents\DomainEventInterface;
use App\Board\Domain\Event\CardArchived;
use App\Board\Domain\Event\CardAssigned;
use App\Board\Domain\Event\CardCreated;
use App\Board\Domain\Event\CardFieldChanged;
use App\Board\Domain\Event\CardLabelAdded;
use App\Board\Domain\Event\CardLabelRemoved;
use App\Board\Domain\Event\CardMoved;
use App\Board\Domain\Event\CardRestored;
use App\Board\Domain\Event\CardUnassigned;
use App\Board\Domain\Event\ColumnCreated;
use App\Board\Domain\Event\ColumnDeleted;
use App\Board\Domain\Event\ColumnRenamed;
use App\Board\Domain\Event\ColumnReordered;
use App\Board\Domain\Event\CommentCreated;
use App\Board\Domain\Event\CommentDeleted;
use App\Board\Domain\Event\CommentEdited;
use App\Board\Domain\Event\LabelCreated;
use App\Board\Domain\Event\LabelDeleted;
use App\Board\Domain\Event\LabelUpdated;
use App\Project\Domain\Enum\MemberRole;
use App\Project\Domain\Event\ProjectArchived;
use App\Project\Domain\Event\ProjectMemberRoleChanged;
use App\Project\Domain\Event\ProjectRestored;
use App\User\Domain\Event\UserRegistered;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

final class DomainEventSerializationTest extends TestCase
{
    #[DataProvider('provideEvents')]
    public function testRoundTripsThroughTheSerializer(object $event): void
    {
        $serializer = new Serializer([new BackedEnumNormalizer(), new ObjectNormalizer()], [new JsonEncoder()]);

        $restored = $serializer->deserialize(
            $serializer->serialize($event, 'json'),
            $event::class,
            'json',
        );

        self::assertInstanceOf(DomainEventInterface::class, $restored);
        self::assertSame($event->getAggregateRootId(), $restored->getAggregateRootId());
        self::assertSame($event->getOccurredOn(), $restored->getOccurredOn());
        self::assertSame($event->getActorId(), $restored->getActorId());
        self::assertEquals(get_object_vars($event), get_object_vars($restored));
    }

    public function testOccurredOnCarriesMicroseconds(): void
    {
        $event = new CardCreated(self::id(), self::id());

        self::assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}[+-]\d{2}:\d{2}$/',
            $event->getOccurredOn(),
        );
    }

    public function testMemberRoleSurvivesAsEnum(): void
    {
        $event = new ProjectMemberRoleChanged(self::id(), self::id(), MemberRole::Admin, self::id());
        $serializer = new Serializer([new BackedEnumNormalizer(), new ObjectNormalizer()], [new JsonEncoder()]);

        $restored = $serializer->deserialize($serializer->serialize($event, 'json'), ProjectMemberRoleChanged::class, 'json');

        self::assertInstanceOf(ProjectMemberRoleChanged::class, $restored);
        self::assertSame(MemberRole::Admin, $restored->newRole);
    }

    public static function provideEvents(): iterable
    {
        $cardId = Uuid::uuid4()->toString();
        $boardId = Uuid::uuid4()->toString();
        $projectId = Uuid::uuid4()->toString();
        $actorId = Uuid::uuid4()->toString();
        $userId = Uuid::uuid4()->toString();
        $labelId = Uuid::uuid4()->toString();
        $commentId = Uuid::uuid4()->toString();
        $columnId = Uuid::uuid4()->toString();

        yield 'card created' => [new CardCreated($cardId, $actorId)];
        yield 'card field changed' => [new CardFieldChanged($cardId, 'title', 'Old', 'New', $actorId)];
        yield 'card field changed with null values' => [new CardFieldChanged($cardId, 'dueDate', null, '2026-12-01', null)];
        yield 'card moved' => [new CardMoved($cardId, $columnId, Uuid::uuid4()->toString(), 1, 3, $actorId)];
        yield 'card archived' => [new CardArchived($cardId, $actorId)];
        yield 'card archived without actor' => [new CardArchived($cardId, null)];
        yield 'card restored' => [new CardRestored($cardId, $actorId)];
        yield 'card assigned' => [new CardAssigned($cardId, $userId, 'Alice', $actorId)];
        yield 'card unassigned' => [new CardUnassigned($cardId, $userId, 'Alice', $actorId)];
        yield 'card label added' => [new CardLabelAdded($cardId, $labelId, 'Bug', $actorId)];
        yield 'card label removed' => [new CardLabelRemoved($cardId, $labelId, 'Bug', $actorId)];
        yield 'column created' => [new ColumnCreated($boardId, $columnId, $actorId)];
        yield 'column renamed' => [new ColumnRenamed($boardId, $columnId, $actorId)];
        yield 'column deleted' => [new ColumnDeleted($boardId, $columnId, $actorId)];
        yield 'column reordered' => [new ColumnReordered($boardId, $columnId, $actorId)];
        yield 'comment created' => [new CommentCreated($cardId, $commentId, $userId)];
        yield 'comment edited' => [new CommentEdited($cardId, $commentId, $userId)];
        yield 'comment deleted' => [new CommentDeleted($cardId, $commentId, $actorId)];
        yield 'label created' => [new LabelCreated($boardId, $labelId, $actorId)];
        yield 'label updated' => [new LabelUpdated($boardId, $labelId, $actorId)];
        yield 'label deleted' => [new LabelDeleted($boardId, $labelId, $actorId)];
        yield 'project archived' => [new ProjectArchived($projectId, $actorId)];
        yield 'project restored' => [new ProjectRestored($projectId, $actorId)];
        yield 'project member role changed' => [
            new ProjectMemberRoleChanged($projectId, $userId, MemberRole::Admin, $actorId),
        ];
        yield 'user registered' => [new UserRegistered($userId, 'alice@example.com', 'Alice')];
    }

    private static function id(): string
    {
        return Uuid::uuid4()->toString();
    }
}
