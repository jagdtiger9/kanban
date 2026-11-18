<?php

declare(strict_types=1);

namespace App\Board\Infrastructure\Controller;

use Aljerom\SymfonyBoilerplate\CQRS\CommandBus;
use Aljerom\SymfonyBoilerplate\CQRS\QueryBus;
use App\Board\Application\Message\Command\AddCardAttachmentCommand;
use App\Board\Application\Message\Command\RemoveCardAttachmentCommand;
use App\Board\Application\Message\Command\RemoveCommentAttachmentCommand;
use App\Board\Application\Message\Query\GetAttachmentQuery;
use App\Board\Application\Message\Query\GetAttachmentThumbnailQuery;
use App\Board\Domain\Entity\Attachment;
use App\Shared\Application\Media\MediaPolicy;
use App\Shared\Infrastructure\Security\SecurityUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AttachmentController extends AbstractController
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus,
        private MediaPolicy $policy,
    ) {
    }

    public function createCardAttachment(AddCardAttachmentCommand $command, Security $security): JsonResponse
    {
        $command->actorId = $this->actorId($security);

        return $this->json(
            $this->attachmentPayload($this->commandBus->dispatch($command)),
            Response::HTTP_CREATED,
        );
    }

    public function deleteCardAttachment(RemoveCardAttachmentCommand $command, Security $security): Response
    {
        $command->actorId = $this->actorId($security);
        $this->commandBus->dispatch($command);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    public function deleteCommentAttachment(RemoveCommentAttachmentCommand $command, Security $security): Response
    {
        $command->actorId = $this->actorId($security);
        $this->commandBus->dispatch($command);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    public function download(GetAttachmentQuery $query, Security $security): Response
    {
        $query->actorId = $this->actorId($security);

        $result = $this->queryBus->query($query);
        $attachment = $result['attachment'];
        $mimeType = $this->policy->isText($attachment->mimeType())
            ? 'text/plain; charset=utf-8'
            : $attachment->mimeType();

        return $this->streamed(
            $result['stream'],
            $mimeType,
            $this->policy->isImage($attachment->mimeType())
                ? HeaderUtils::DISPOSITION_INLINE
                : HeaderUtils::DISPOSITION_ATTACHMENT,
            $attachment->originalName(),
            $attachment->sizeBytes(),
        );
    }

    public function thumbnail(GetAttachmentThumbnailQuery $query, Security $security): Response
    {
        $query->actorId = $this->actorId($security);

        $result = $this->queryBus->query($query);

        return $this->streamed(
            $result['stream'],
            'image/webp',
            HeaderUtils::DISPOSITION_INLINE,
            'thumbnail.webp',
        );
    }

    private function actorId(Security $security): string
    {
        $user = $security->getUser();
        if (!$user instanceof SecurityUser) {
            throw new \LogicException('Unexpected user type');
        }

        return (string)$user->getId();
    }

    /**
     * @return array{
     *     id: string,
     *     cardId: string,
     *     commentId: ?string,
     *     uploaderId: string,
     *     name: string,
     *     mimeType: string,
     *     sizeBytes: int,
     *     width: ?int,
     *     height: ?int,
     *     createdAt: string,
     * }
     */
    private function attachmentPayload(Attachment $attachment): array
    {
        return [
            'id' => (string)$attachment->id(),
            'cardId' => (string)$attachment->cardId(),
            'commentId' => $attachment->commentId() !== null ? (string)$attachment->commentId() : null,
            'uploaderId' => (string)$attachment->uploaderId(),
            'name' => $attachment->originalName(),
            'mimeType' => $attachment->mimeType(),
            'sizeBytes' => $attachment->sizeBytes(),
            'width' => $attachment->width(),
            'height' => $attachment->height(),
            'createdAt' => $attachment->createdAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param resource $stream
     */
    private function streamed(
        $stream,
        string $contentType,
        string $disposition,
        string $fileName,
        ?int $contentLength = null,
    ): StreamedResponse {
        $headers = [
            'Content-Type' => $contentType,
            'Content-Disposition' => HeaderUtils::makeDisposition(
                $disposition,
                $fileName,
                $this->fileNameFallback($fileName),
            ),
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'; sandbox",
        ];
        if ($contentLength !== null) {
            $headers['Content-Length'] = (string)$contentLength;
        }

        return new StreamedResponse(
            static function () use ($stream): void {
                fpassthru($stream);
                fclose($stream);
            },
            Response::HTTP_OK,
            $headers,
        );
    }

    private function fileNameFallback(string $fileName): string
    {
        $fallback = preg_replace('/[^\x20-\x7E]/', '_', $fileName) ?? 'file';
        $fallback = str_replace(['%', '/', '\\'], '_', $fallback);

        return $fallback === '' ? 'file' : $fallback;
    }
}
