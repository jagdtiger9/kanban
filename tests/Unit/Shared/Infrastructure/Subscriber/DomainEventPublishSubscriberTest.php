<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Subscriber;

use Aljerom\SymfonyBoilerplate\DomainEvents\EventId;
use Aljerom\SymfonyBoilerplate\DomainEvents\EventRepositoryInterface;
use Aljerom\SymfonyBoilerplate\EventSubscriber\DomainEventPublishSubscriber;
use App\Board\Domain\Event\CardCreated;
use App\Shared\Domain\Entity\DomainEvent;
use DateTimeImmutable;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\SerializerInterface;

final class DomainEventPublishSubscriberTest extends TestCase
{
    private EventRepositoryInterface $eventRepo;
    private EntityManagerInterface $entityManager;
    private MessageBusInterface $eventBus;
    private SerializerInterface $serializer;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->eventRepo = $this->createMock(EventRepositoryInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->eventBus = $this->createMock(MessageBusInterface::class);
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    private function subscriber(): DomainEventPublishSubscriber
    {
        return new DomainEventPublishSubscriber(
            $this->eventRepo,
            $this->entityManager,
            $this->eventBus,
            $this->serializer,
            $this->logger,
        );
    }

    private function storedEvent(): DomainEvent
    {
        return new DomainEvent(
            EventId::fromString(Uuid::uuid4()->toString()),
            CardCreated::class,
            new DateTimeImmutable(),
            Uuid::uuid4()->toString(),
            '{}',
            Uuid::uuid7()->toString(),
        );
    }

    private function terminateEvent(): TerminateEvent
    {
        return new TerminateEvent($this->createMock(HttpKernelInterface::class), new Request(), new Response());
    }

    private function consoleTerminateEvent(): ConsoleTerminateEvent
    {
        return new ConsoleTerminateEvent(new Command('test'), new ArrayInput([]), new NullOutput(), 0);
    }

    private function workerHandledEvent(): WorkerMessageHandledEvent
    {
        return new WorkerMessageHandledEvent(Envelope::wrap(new CardCreated((string) Uuid::uuid4(), (string) Uuid::uuid4())), 'default');
    }

    public function testSubscribesToHttpConsoleAndWorkerCompletion(): void
    {
        $events = DomainEventPublishSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(KernelEvents::TERMINATE, $events);
        self::assertArrayHasKey(ConsoleEvents::TERMINATE, $events);
        self::assertArrayHasKey(WorkerMessageHandledEvent::class, $events);
    }

    public function testDispatchesUnpublishedEventsAndRemovesRows(): void
    {
        $first = $this->storedEvent();
        $second = $this->storedEvent();
        $firstEvent = new CardCreated((string) Uuid::uuid4(), (string) Uuid::uuid4());
        $secondEvent = new CardCreated((string) Uuid::uuid4(), (string) Uuid::uuid4());

        $this->entityManager->method('isOpen')->willReturn(true);
        $this->eventRepo->expects($this->once())->method('getUnpublished')
            ->willReturn([$first, $second]);
        $this->serializer->expects($this->exactly(2))->method('deserialize')
            ->willReturnOnConsecutiveCalls($firstEvent, $secondEvent);
        $dispatched = [];
        $this->eventBus->expects($this->exactly(2))->method('dispatch')
            ->willReturnCallback(static function (object $event) use (&$dispatched): Envelope {
                $dispatched[] = $event;

                return Envelope::wrap($event);
            });
        $this->entityManager->expects($this->exactly(2))->method('remove')
            ->with($this->isInstanceOf(DomainEvent::class));
        $this->entityManager->expects($this->once())->method('flush');

        $this->subscriber()->onHttpProcess($this->terminateEvent());

        self::assertSame([$firstEvent, $secondEvent], $dispatched);
    }

    public function testLeavesEventUnpublishedWhenDispatchFails(): void
    {
        $stored = $this->storedEvent();

        $this->entityManager->method('isOpen')->willReturn(true);
        $this->eventRepo->method('getUnpublished')->willReturn([$stored]);
        $this->serializer->method('deserialize')
            ->willReturn(new CardCreated((string) Uuid::uuid4(), (string) Uuid::uuid4()));
        $this->eventBus->method('dispatch')->willThrowException(new \RuntimeException('broker down'));
        $this->entityManager->expects($this->never())->method('remove');
        $this->entityManager->expects($this->once())->method('flush');
        $this->logger->expects($this->once())->method('warning');

        $this->subscriber()->onWorkerHandled($this->workerHandledEvent());
    }

    public function testSkipsWhenEntityManagerClosed(): void
    {
        $this->entityManager->method('isOpen')->willReturn(false);
        $this->eventRepo->expects($this->never())->method('getUnpublished');
        $this->eventBus->expects($this->never())->method('dispatch');
        $this->entityManager->expects($this->never())->method('flush');

        $this->subscriber()->onConsoleProcess($this->consoleTerminateEvent());
    }

    public function testSkipsWhenDatabaseFails(): void
    {
        $this->entityManager->method('isOpen')->willReturn(true);
        $dbException = new class ('db down') extends \RuntimeException implements Exception {};
        $this->eventRepo->method('getUnpublished')->willThrowException($dbException);
        $this->eventBus->expects($this->never())->method('dispatch');
        $this->entityManager->expects($this->never())->method('flush');

        $this->subscriber()->onHttpProcess($this->terminateEvent());
    }

    public function testDoesNothingWhenNoEventsPending(): void
    {
        $this->entityManager->method('isOpen')->willReturn(true);
        $this->eventRepo->method('getUnpublished')->willReturn([]);
        $this->eventBus->expects($this->never())->method('dispatch');
        $this->entityManager->expects($this->never())->method('flush');

        $this->subscriber()->onHttpProcess($this->terminateEvent());
    }
}
