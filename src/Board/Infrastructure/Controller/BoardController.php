<?php

declare(strict_types=1);

namespace App\Board\Infrastructure\Controller;

use Aljerom\SymfonyBoilerplate\CQRS\CommandBus;
use Aljerom\SymfonyBoilerplate\CQRS\QueryBus;
use App\Board\Application\Message\Command\ArchiveCardCommand;
use App\Board\Application\Message\Command\CreateCardCommand;
use App\Board\Application\Message\Command\CreateColumnCommand;
use App\Board\Application\Message\Command\CreateCommentCommand;
use App\Board\Application\Message\Command\CreateLabelCommand;
use App\Board\Application\Message\Command\DeleteColumnCommand;
use App\Board\Application\Message\Command\DeleteCommentCommand;
use App\Board\Application\Message\Command\DeleteLabelCommand;
use App\Board\Application\Message\Command\MoveCardCommand;
use App\Board\Application\Message\Command\RenameColumnCommand;
use App\Board\Application\Message\Command\ReorderColumnCommand;
use App\Board\Application\Message\Command\RestoreCardCommand;
use App\Board\Application\Message\Command\SetCardAssigneesCommand;
use App\Board\Application\Message\Command\SetCardDueDateCommand;
use App\Board\Application\Message\Command\SetCardLabelsCommand;
use App\Board\Application\Message\Command\UpdateCardContentCommand;
use App\Board\Application\Message\Command\UpdateCommentCommand;
use App\Board\Application\Message\Command\UpdateLabelCommand;
use App\Board\Application\Message\Query\GetBoardQuery;
use App\Board\Application\Message\Query\GetCardQuery;
use App\Board\Domain\Entity\Card;
use App\Board\Domain\Entity\CardActivity;
use App\Board\Domain\Entity\Column;
use App\Board\Domain\Entity\Comment;
use App\Board\Domain\Entity\Label;
use App\Shared\Infrastructure\Security\ProjectAccessVoter;
use App\Shared\Infrastructure\Security\SecurityUser;
use App\User\Domain\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class BoardController extends AbstractController
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus,
    ) {
    }

    public function get(GetBoardQuery $query, Security $security): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProjectAccessVoter::PROJECT_MEMBER, $query->projectId);
        $query->actorId = $this->actorId($security);

        $result = $this->queryBus->query($query);

        return $this->json([
            'boardId' => (string) $result['boardId'],
            'columns' => array_map(
                fn (Column $column): array => $this->columnPayload($column),
                $result['columns'],
            ),
            'cards' => array_map(
                fn (Card $card): array => $this->cardPayload($card),
                $result['cards'],
            ),
            'labels' => array_map(
                fn (Label $label): array => $this->labelPayload($label),
                $result['labels'],
            ),
        ]);
    }

    public function getCard(GetCardQuery $query, Security $security): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProjectAccessVoter::PROJECT_MEMBER, $query->cardId);
        $query->actorId = $this->actorId($security);

        $result = $this->queryBus->query($query);

        return $this->json([
            'card' => $this->cardPayload($result['card']),
            'comments' => array_map(
                fn (Comment $comment): array => $this->commentPayload($comment),
                $result['comments'],
            ),
            'activities' => array_map(
                fn (CardActivity $activity): array => $this->activityPayload($activity),
                $result['activities'],
            ),
        ]);
    }

    public function createColumn(CreateColumnCommand $command, Security $security): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProjectAccessVoter::PROJECT_MEMBER, $command->projectId);
        $command->actorId = $this->actorId($security);

        return $this->json(
            $this->columnPayload($this->commandBus->dispatch($command)),
            Response::HTTP_CREATED,
        );
    }

    public function renameColumn(RenameColumnCommand $command, Security $security): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProjectAccessVoter::PROJECT_MEMBER, $command->columnId);
        $command->actorId = $this->actorId($security);

        return $this->json($this->columnPayload($this->commandBus->dispatch($command)));
    }

    public function deleteColumn(DeleteColumnCommand $command, Security $security): Response
    {
        $this->denyAccessUnlessGranted(ProjectAccessVoter::PROJECT_ADMIN, $command->columnId);
        $command->actorId = $this->actorId($security);
        $this->commandBus->dispatch($command);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    public function reorderColumn(ReorderColumnCommand $command, Security $security): Response
    {
        $this->denyAccessUnlessGranted(ProjectAccessVoter::PROJECT_MEMBER, $command->columnId);
        $command->actorId = $this->actorId($security);
        $this->commandBus->dispatch($command);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    public function createCard(CreateCardCommand $command, Security $security): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProjectAccessVoter::PROJECT_MEMBER, $command->columnId);
        $command->actorId = $this->actorId($security);

        return $this->json(
            $this->cardPayload($this->commandBus->dispatch($command)),
            Response::HTTP_CREATED,
        );
    }

    public function updateCard(UpdateCardContentCommand $command, Security $security): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProjectAccessVoter::CARD_EDITOR, $command->cardId);
        $command->actorId = $this->actorId($security);

        return $this->json($this->cardPayload($this->commandBus->dispatch($command)));
    }

    public function archiveCard(ArchiveCardCommand $command, Security $security): Response
    {
        $this->denyAccessUnlessGranted(ProjectAccessVoter::CARD_EDITOR, $command->cardId);
        $command->actorId = $this->actorId($security);
        $this->commandBus->dispatch($command);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    public function restoreCard(RestoreCardCommand $command, Security $security): Response
    {
        $this->denyAccessUnlessGranted(ProjectAccessVoter::CARD_EDITOR, $command->cardId);
        $command->actorId = $this->actorId($security);
        $this->commandBus->dispatch($command);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    public function moveCard(MoveCardCommand $command, Security $security): Response
    {
        $this->denyAccessUnlessGranted(ProjectAccessVoter::PROJECT_MEMBER, $command->cardId);
        $command->actorId = $this->actorId($security);
        $this->commandBus->dispatch($command);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    public function setCardLabels(SetCardLabelsCommand $command, Security $security): Response
    {
        $this->denyAccessUnlessGranted(ProjectAccessVoter::PROJECT_MEMBER, $command->cardId);
        $command->actorId = $this->actorId($security);
        $this->commandBus->dispatch($command);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    public function setCardAssignees(SetCardAssigneesCommand $command, Security $security): Response
    {
        $this->denyAccessUnlessGranted(ProjectAccessVoter::PROJECT_MEMBER, $command->cardId);
        $command->actorId = $this->actorId($security);
        $this->commandBus->dispatch($command);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    public function setCardDueDate(SetCardDueDateCommand $command, Security $security): Response
    {
        $this->denyAccessUnlessGranted(ProjectAccessVoter::PROJECT_MEMBER, $command->cardId);
        $command->actorId = $this->actorId($security);
        $this->commandBus->dispatch($command);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    public function createComment(CreateCommentCommand $command, Security $security): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProjectAccessVoter::PROJECT_MEMBER, $command->cardId);
        $command->actorId = $this->actorId($security);

        return $this->json(
            $this->commentPayload($this->commandBus->dispatch($command)),
            Response::HTTP_CREATED,
        );
    }

    public function updateComment(UpdateCommentCommand $command, Security $security): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProjectAccessVoter::COMMENT_EDITOR, $command->commentId);
        $command->actorId = $this->actorId($security);

        return $this->json($this->commentPayload($this->commandBus->dispatch($command)));
    }

    public function deleteComment(DeleteCommentCommand $command, Security $security): Response
    {
        $this->denyAccessUnlessGranted(ProjectAccessVoter::COMMENT_MANAGER, $command->commentId);
        $command->actorId = $this->actorId($security);
        $this->commandBus->dispatch($command);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    public function createLabel(CreateLabelCommand $command, Security $security): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProjectAccessVoter::PROJECT_MEMBER, $command->projectId);
        $command->actorId = $this->actorId($security);

        return $this->json(
            $this->labelPayload($this->commandBus->dispatch($command)),
            Response::HTTP_CREATED,
        );
    }

    public function updateLabel(UpdateLabelCommand $command, Security $security): JsonResponse
    {
        $this->denyAccessUnlessGranted(ProjectAccessVoter::PROJECT_MEMBER, $command->labelId);
        $command->actorId = $this->actorId($security);

        return $this->json($this->labelPayload($this->commandBus->dispatch($command)));
    }

    public function deleteLabel(DeleteLabelCommand $command, Security $security): Response
    {
        $this->denyAccessUnlessGranted(ProjectAccessVoter::PROJECT_ADMIN, $command->labelId);
        $command->actorId = $this->actorId($security);
        $this->commandBus->dispatch($command);

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    private function actorId(Security $security): string
    {
        $user = $security->getUser();
        if (!$user instanceof SecurityUser) {
            throw new \LogicException('Unexpected user type');
        }

        return (string) $user->getId();
    }

    /**
     * @return array{id: string, name: string, position: int, createdAt: string}
     */
    private function columnPayload(Column $column): array
    {
        return [
            'id' => (string) $column->id(),
            'name' => $column->name(),
            'position' => $column->position(),
            'createdAt' => $column->createdAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array{
     *     id: string,
     *     columnId: string,
     *     title: string,
     *     description: string,
     *     position: int,
     *     dueDate: ?string,
     *     creatorId: string,
     *     createdAt: string,
     *     updatedAt: string,
     *     archived: bool,
     *     labelIds: list<string>,
     *     assigneeIds: list<string>,
     * }
     */
    private function cardPayload(Card $card): array
    {
        return [
            'id' => (string) $card->id(),
            'columnId' => (string) $card->columnId(),
            'title' => $card->title(),
            'description' => $card->description(),
            'position' => $card->position(),
            'dueDate' => $card->dueDate()?->format('Y-m-d'),
            'creatorId' => (string) $card->creatorId(),
            'createdAt' => $card->createdAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $card->updatedAt()->format(\DateTimeInterface::ATOM),
            'archived' => $card->isArchived(),
            'labelIds' => array_map(
                static fn (Label $label): string => (string) $label->id(),
                $card->labels()->toArray(),
            ),
            'assigneeIds' => array_map(
                static fn (User $user): string => (string) $user->id(),
                $card->assignees()->toArray(),
            ),
        ];
    }

    /**
     * @return array{id: string, name: string, color: string, createdAt: string}
     */
    private function labelPayload(Label $label): array
    {
        return [
            'id' => (string) $label->id(),
            'name' => $label->name(),
            'color' => $label->color(),
            'createdAt' => $label->createdAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array{id: string, cardId: string, authorId: string, body: string, createdAt: string, updatedAt: string}
     */
    private function commentPayload(Comment $comment): array
    {
        return [
            'id' => (string) $comment->id(),
            'cardId' => (string) $comment->cardId(),
            'authorId' => (string) $comment->authorId(),
            'body' => $comment->body(),
            'createdAt' => $comment->createdAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $comment->updatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array{
     *     id: string,
     *     cardId: string,
     *     actorId: ?string,
     *     type: string,
     *     payload: array<string, mixed>,
     *     createdAt: string,
     * }
     */
    private function activityPayload(CardActivity $activity): array
    {
        $actorId = $activity->actorId();

        return [
            'id' => (string) $activity->id(),
            'cardId' => (string) $activity->cardId(),
            'actorId' => $actorId !== null ? (string) $actorId : null,
            'type' => $activity->type(),
            'payload' => $activity->payload(),
            'createdAt' => $activity->createdAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
