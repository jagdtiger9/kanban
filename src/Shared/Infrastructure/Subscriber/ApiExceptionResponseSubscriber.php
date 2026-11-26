<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Subscriber;

use Aljerom\SymfonyBoilerplate\Request\ValidationException;
use App\Board\Domain\Exception\AttachmentForbiddenException;
use App\Board\Domain\Exception\AttachmentLimitException;
use App\Board\Domain\Exception\AttachmentNotFoundException;
use App\Board\Domain\Exception\CardForbiddenException;
use App\Board\Domain\Exception\CardKindMismatchException;
use App\Board\Domain\Exception\CardLinkException;
use App\Board\Domain\Exception\CardNotFoundException;
use App\Board\Domain\Exception\ColumnNotEmptyException;
use App\Board\Domain\Exception\ColumnNotFoundException;
use App\Board\Domain\Exception\CommentForbiddenException;
use App\Board\Domain\Exception\CommentNotFoundException;
use App\Board\Domain\Exception\InvalidRecurrenceException;
use App\Board\Domain\Exception\LabelNotFoundException;
use App\News\Domain\Exception\NewsPostForbiddenException;
use App\News\Domain\Exception\NewsPostNotFoundException;
use App\Project\Domain\Exception\LastProjectAdminException;
use App\Project\Domain\Exception\ProjectForbiddenException;
use App\Project\Domain\Exception\ProjectMemberAlreadyExistsException;
use App\Project\Domain\Exception\ProjectMemberNotFoundException;
use App\Project\Domain\Exception\ProjectNotFoundException;
use App\Shared\Application\Storage\Exception\FileNotFoundException;
use App\Shared\Domain\Exception\AccessForbiddenException;
use App\User\Domain\Exception\AvatarNotFoundException;
use App\User\Domain\Exception\ConfirmationTokenExpiredException;
use App\User\Domain\Exception\ConfirmationTokenInvalidException;
use App\User\Domain\Exception\CurrentPasswordInvalidException;
use App\User\Domain\Exception\PasswordsDoNotMatchException;
use App\User\Domain\Exception\UserAlreadyConfirmedException;
use App\User\Domain\Exception\UserAlreadyRegisteredException;
use App\User\Domain\Exception\UserNotFoundException;
use App\UserEvent\Domain\Exception\InvalidUserEventCursorException;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;

/**
 * Maps known domain/infrastructure exceptions to HTTP codes and turns every
 * unhandled exception into a JSON error. ValidationException is left to the
 * boilerplate listener (400 with per-field errors).
 */
final class ApiExceptionResponseSubscriber implements EventSubscriberInterface
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['__invoke', 0],
        ];
    }

    public function __invoke(ExceptionEvent $event): void
    {
        if ($event->getResponse() !== null) {
            return;
        }

        $throwable = $event->getThrowable();
        if ($throwable instanceof ValidationException) {
            return;
        }

        [$status, $message] = $this->statusAndMessage($throwable);

        // 5xx messages are never shown to the client — full details go to the log
        if ($status >= Response::HTTP_INTERNAL_SERVER_ERROR) {
            $this->logger->error($throwable::class . ': ' . $throwable->getMessage(), [
                'exception' => $throwable,
                'url' => $event->getRequest()->getPathInfo(),
                'method' => $event->getRequest()->getMethod(),
            ]);
        }

        $request = $event->getRequest();
        if (str_starts_with($request->getPathInfo(), '/api') || $request->isXmlHttpRequest()) {
            $response = new JsonResponse([
                'message' => $message,
                'errors' => [],
            ], $status);
        } else {
            $response = new Response($message, $status);
        }
        $event->setResponse($response);
        $event->allowCustomResponseCode();
    }

    /**
     * @return array{int, string}
     */
    private function statusAndMessage(Throwable $throwable): array
    {
        if ($throwable instanceof UserAlreadyRegisteredException) {
            return [Response::HTTP_CONFLICT, $throwable->getMessage()];
        }
        if ($throwable instanceof PasswordsDoNotMatchException
            || $throwable instanceof CurrentPasswordInvalidException
            || $throwable instanceof ConfirmationTokenInvalidException
            || $throwable instanceof ConfirmationTokenExpiredException
        ) {
            return [Response::HTTP_BAD_REQUEST, $throwable->getMessage()];
        }
        if ($throwable instanceof InvalidUserEventCursorException) {
            return [Response::HTTP_BAD_REQUEST, $throwable->getMessage()];
        }
        if ($throwable instanceof CardLinkException
            || $throwable instanceof CardKindMismatchException
            || $throwable instanceof InvalidRecurrenceException
        ) {
            return [Response::HTTP_BAD_REQUEST, $throwable->getMessage()];
        }
        if ($throwable instanceof ProjectNotFoundException
            || $throwable instanceof ProjectMemberNotFoundException
            || $throwable instanceof ColumnNotFoundException
            || $throwable instanceof CardNotFoundException
            || $throwable instanceof LabelNotFoundException
            || $throwable instanceof CommentNotFoundException
            || $throwable instanceof AttachmentNotFoundException
            || $throwable instanceof FileNotFoundException
            || $throwable instanceof AvatarNotFoundException
            || $throwable instanceof NewsPostNotFoundException
            || $throwable instanceof UserNotFoundException
        ) {
            return [Response::HTTP_NOT_FOUND, $throwable->getMessage()];
        }
        if ($throwable instanceof AccessForbiddenException
            || $throwable instanceof ProjectForbiddenException
            || $throwable instanceof CardForbiddenException
            || $throwable instanceof CommentForbiddenException
            || $throwable instanceof AttachmentForbiddenException
            || $throwable instanceof NewsPostForbiddenException
        ) {
            return [Response::HTTP_FORBIDDEN, $throwable->getMessage()];
        }
        if ($throwable instanceof LastProjectAdminException
            || $throwable instanceof ProjectMemberAlreadyExistsException
            || $throwable instanceof ColumnNotEmptyException
            || $throwable instanceof UserAlreadyConfirmedException
            || $throwable instanceof AttachmentLimitException
        ) {
            return [Response::HTTP_CONFLICT, $throwable->getMessage()];
        }
        if ($throwable instanceof UniqueConstraintViolationException) {
            return [Response::HTTP_CONFLICT, 'Record already exists'];
        }
        if ($throwable instanceof ConnectionException) {
            return [Response::HTTP_SERVICE_UNAVAILABLE, 'Service temporarily unavailable'];
        }
        if ($throwable instanceof HttpExceptionInterface) {
            return [$throwable->getStatusCode(), $throwable->getMessage()];
        }

        return [Response::HTTP_INTERNAL_SERVER_ERROR, 'Internal server error'];
    }
}
