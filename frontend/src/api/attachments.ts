import { api, apiText, upload } from './client'

export interface AttachmentPayload {
    id: string
    cardId: string
    commentId: string | null
    uploaderId: string
    name: string
    mimeType: string
    sizeBytes: number
    width: number | null
    height: number | null
    createdAt: string
}

export function uploadCardAttachment(cardId: string, file: File): Promise<AttachmentPayload> {
    const form = new FormData()
    form.append('file', file)
    return upload<AttachmentPayload>(`/cards/${cardId}/attachments`, form)
}

export function deleteCardAttachment(cardId: string, attachmentId: string): Promise<void> {
    return api<void>(`/cards/${cardId}/attachments/${attachmentId}`, { method: 'DELETE' })
}

export function deleteCommentAttachment(commentId: string, attachmentId: string): Promise<void> {
    return api<void>(`/comments/${commentId}/attachments/${attachmentId}`, { method: 'DELETE' })
}

export function attachmentUrl(attachmentId: string): string {
    return `/api/attachments/${attachmentId}`
}

export function thumbnailUrl(attachmentId: string): string {
    return `/api/attachments/${attachmentId}/thumbnail`
}

export function getAttachmentText(attachmentId: string): Promise<string> {
    return apiText(`/attachments/${attachmentId}`)
}
