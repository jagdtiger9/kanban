import { api, upload } from './client'
import type { AttachmentPayload } from './attachments'

export type ColumnKind = 'task' | 'sticker'

export interface ColumnPayload {
    id: string
    name: string
    position: number
    kind: ColumnKind
    createdAt: string
}

export type RecurrenceKind = 'weekly' | 'monthly'

export interface RecurrenceRulePayload {
    kind: RecurrenceKind
    weekdays: number[]
    monthDays: number[]
}

export type OccurrenceState = 'pending' | 'completed'

export interface OccurrencePayload {
    state: OccurrenceState
    period: string
}

export interface ChecklistItemPayload {
    id: string
    text: string
    done: boolean
}

export interface CardPayload {
    id: string
    columnId: string
    title: string
    description: string
    position: number
    dueDate: string | null
    creatorId: string
    executorId: string | null
    linkedCardId: string | null
    recurrence: RecurrenceRulePayload | null
    checklist: ChecklistItemPayload[]
    createdAt: string
    updatedAt: string
    archived: boolean
    completedAt: string | null
    labelIds: string[]
    assigneeIds: string[]
}

export interface BoardCardPayload extends CardPayload {
    unseenCount: number
    occurrence: OccurrencePayload | null
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

export function createColumn(projectId: string, name: string, kind: ColumnKind): Promise<ColumnPayload> {
    return api<ColumnPayload>(`/projects/${projectId}/columns`, {
        method: 'POST',
        body: JSON.stringify({ name, kind }),
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

export function setCardExecutor(cardId: string, executorId: string | null): Promise<void> {
    return api<void>(`/cards/${cardId}/executor`, {
        method: 'PUT',
        body: JSON.stringify({ executorId }),
    })
}

export function setCardDueDate(cardId: string, dueDate: string | null): Promise<void> {
    return api<void>(`/cards/${cardId}/due-date`, {
        method: 'PUT',
        body: JSON.stringify({ dueDate }),
    })
}

export function setCardLinkedCard(cardId: string, linkedCardId: string | null): Promise<void> {
    return api<void>(`/cards/${cardId}/linked-card`, {
        method: 'PUT',
        body: JSON.stringify({ linkedCardId }),
    })
}

export function setCardRecurrence(cardId: string, recurrence: RecurrenceRulePayload | null): Promise<void> {
    return api<void>(`/cards/${cardId}/recurrence`, {
        method: 'PUT',
        body: JSON.stringify({ recurrence }),
    })
}

export function addChecklistItem(cardId: string, text: string): Promise<ChecklistItemPayload> {
    return api<ChecklistItemPayload>(`/cards/${cardId}/checklist`, {
        method: 'POST',
        body: JSON.stringify({ text }),
    })
}

export function setChecklistItemDone(cardId: string, itemId: string, done: boolean): Promise<void> {
    return api<void>(`/cards/${cardId}/checklist/${itemId}`, {
        method: 'PUT',
        body: JSON.stringify({ done }),
    })
}

export function removeChecklistItem(cardId: string, itemId: string): Promise<void> {
    return api<void>(`/cards/${cardId}/checklist/${itemId}`, { method: 'DELETE' })
}

export function completeCard(cardId: string): Promise<void> {
    return api<void>(`/cards/${cardId}/complete`, { method: 'POST' })
}

export function uncompleteCard(cardId: string): Promise<void> {
    return api<void>(`/cards/${cardId}/uncomplete`, { method: 'POST' })
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

export interface LinkedCardPayload {
    id: string
    title: string
}

export interface CardDetailPayload {
    card: CardPayload & { occurrence: OccurrencePayload | null }
    linkedCard: LinkedCardPayload | null
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
