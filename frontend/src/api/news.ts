import { api } from './client'

export interface NewsPostPayload {
    id: string
    authorId: string
    authorName: string
    authorAvatarVersion: string | null
    body: string
    createdAt: string
    updatedAt: string
}

export function listNews(p = 1, perPage = 20): Promise<NewsPostPayload[]> {
    return api<NewsPostPayload[]>(`/news?p=${p}&perPage=${perPage}`)
}

export function createNews(body: string): Promise<NewsPostPayload> {
    return api<NewsPostPayload>('/news', {
        method: 'POST',
        body: JSON.stringify({ body }),
    })
}

export function updateNews(id: string, body: string): Promise<NewsPostPayload> {
    return api<NewsPostPayload>(`/news/${id}`, {
        method: 'PUT',
        body: JSON.stringify({ body }),
    })
}

export function deleteNews(id: string): Promise<void> {
    return api<void>(`/news/${id}`, { method: 'DELETE' })
}
