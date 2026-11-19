import { api, upload } from './client'
import type { AttachmentPayload } from './attachments'

export interface ColumnPayload {
    id: string
    name: string
    position: number
    createdAt: string
}

export interface CardPayload {
    id: string
    columnId: string
    title: string
    description: string
    position: number
    dueDate: string | null
    creatorId: string
    createdAt: string
    updatedAt: string
    archived: boolean
    labelIds: string[]
    assigneeIds: string[]
}

export interface BoardCardPayload extends CardPayload {
    unseenCount: number
}

export interface LabelPayload {
    id: string
    name: string
    color: string
    createdAt: string
}

export interface BoardPayload {
    boardId: string
    columns: ColumnPayload[]
    cards: BoardCardPayload[]
    labels: LabelPayload[]
}

export function getBoard(projectId: string, includeArchived = false): Promise<BoardPayload> {
    const query = includeArchived ? '?includeArchived=1' : ''
    return api<BoardPayload>(`/projects/${projectId}/board${query}`)
}

export function createColumn(projectId: string, name: string): Promise<ColumnPayload> {
    return api<ColumnPayload>(`/projects/${projectId}/columns`, {
        method: 'POST',
        body: JSON.stringify({ name }),
    })
}

export function renameColumn(columnId: string, name: string): Promise<ColumnPayload> {
    return api<ColumnPayload>(`/columns/${columnId}`, {
        method: 'PUT',
        body: JSON.stringify({ name }),
    })
}

export function deleteColumn(columnId: string): Promise<void> {
    return api<void>(`/columns/${columnId}`, { method: 'DELETE' })
}

export function reorderColumn(columnId: string, targetIndex: number): Promise<void> {
    return api<void>(`/columns/${columnId}/reorder`, {
        method: 'PUT',
        body: JSON.stringify({ targetIndex }),
    })
}

export function createCard(columnId: string, title: string, description = ''): Promise<CardPayload> {
    return api<CardPayload>(`/columns/${columnId}/cards`, {
        method: 'POST',
        body: JSON.stringify({ title, description }),
    })
}

export function updateCard(cardId: string, title: string, description: string): Promise<CardPayload> {
    return api<CardPayload>(`/cards/${cardId}`, {
        method: 'PUT',
        body: JSON.stringify({ title, description }),
    })
}

export function archiveCard(cardId: string): Promise<void> {
    return api<void>(`/cards/${cardId}`, { method: 'DELETE' })
}

export function restoreCard(cardId: string): Promise<void> {
    return api<void>(`/cards/${cardId}/restore`, { method: 'POST' })
}

export function markCardSeen(cardId: string): Promise<void> {
    return api<void>(`/cards/${cardId}/seen`, { method: 'POST' })
}

export function moveCard(cardId: string, targetColumnId: string, targetIndex: number): Promise<void> {
    return api<void>(`/cards/${cardId}/move`, {
        method: 'PUT',
        body: JSON.stringify({ targetColumnId, targetIndex }),
    })
}

export function setCardLabels(cardId: string, labelIds: string[]): Promise<void> {
    return api<void>(`/cards/${cardId}/labels`, {
        method: 'PUT',
        body: JSON.stringify({ labelIds }),
    })
}

export function setCardAssignees(cardId: string, userIds: string[]): Promise<void> {
    return api<void>(`/cards/${cardId}/assignees`, {
        method: 'PUT',
        body: JSON.stringify({ userIds }),
    })
}

export function setCardDueDate(cardId: string, dueDate: string | null): Promise<void> {
    return api<void>(`/cards/${cardId}/due-date`, {
        method: 'PUT',
        body: JSON.stringify({ dueDate }),
    })
}

export function createLabel(projectId: string, name: string, color: string): Promise<LabelPayload> {
    return api<LabelPayload>(`/projects/${projectId}/labels`, {
        method: 'POST',
        body: JSON.stringify({ name, color }),
    })
}

export function updateLabel(labelId: string, name: string, color: string): Promise<LabelPayload> {
    return api<LabelPayload>(`/labels/${labelId}`, {
        method: 'PUT',
        body: JSON.stringify({ name, color }),
    })
}

export function deleteLabel(labelId: string): Promise<void> {
    return api<void>(`/labels/${labelId}`, { method: 'DELETE' })
}

export interface CommentPayload {
    id: string
    cardId: string
    authorId: string
    body: string
    createdAt: string
    updatedAt: string
    attachments: AttachmentPayload[]
}

export interface ActivityPayload {
    id: string
    cardId: string
    actorId: string | null
    type: string
    payload: Record<string, unknown>
    createdAt: string
}

export type TimelineItem =
    | { kind: 'comment'; at: string; comment: CommentPayload }
    | { kind: 'event'; at: string; event: ActivityPayload }

export interface CardDetailPayload {
    card: CardPayload
    attachments: AttachmentPayload[]
    timeline: TimelineItem[]
}

export function getCard(cardId: string): Promise<CardDetailPayload> {
    return api<CardDetailPayload>(`/cards/${cardId}`)
}

export function createComment(cardId: string, body: string, files: File[] = []): Promise<CommentPayload> {
    const form = new FormData()
    form.append('body', body)
    for (const file of files) {
        form.append('attachments[]', file)
    }

    return upload<CommentPayload>(`/cards/${cardId}/comments`, form)
}

export function updateComment(commentId: string, body: string): Promise<CommentPayload> {
    return api<CommentPayload>(`/comments/${commentId}`, {
        method: 'PUT',
        body: JSON.stringify({ body }),
    })
}

export function deleteComment(commentId: string): Promise<void> {
    return api<void>(`/comments/${commentId}`, { method: 'DELETE' })
}
