import { useLoader } from '../composables/useLoader'

export interface ApiFieldError {
    property: string
    value: unknown
    message: string
}

export class ApiError extends Error {
    constructor(
        readonly status: number,
        message: string,
        readonly errors: ApiFieldError[] = [],
    ) {
        super(message)
    }
}

export async function api<T>(path: string, init?: RequestInit): Promise<T> {
    const { fetchStart, fetchStop } = useLoader()
    fetchStart()
    try {
        return await send<T>(path, init)
    } finally {
        fetchStop()
    }
}

async function send<T>(path: string, init?: RequestInit): Promise<T> {
    const res = await fetch(`/api${path}`, {
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        ...init,
    })
    if (!res.ok) {
        let message = `Request failed with status ${res.status}`
        let errors: ApiFieldError[] = []
        try {
            const body = (await res.json()) as { message?: unknown; errors?: unknown }
            if (typeof body.message === 'string' && body.message !== '') {
                message = body.message
            }
            if (Array.isArray(body.errors)) {
                errors = body.errors.filter(
                    (e): e is ApiFieldError =>
                        typeof e === 'object' && e !== null && 'property' in e && 'message' in e,
                )
            }
        } catch {
            // non-JSON error body — keep the fallback message
        }
        throw new ApiError(res.status, message, errors)
    }
    if (res.status === 204) {
        return undefined as T
    }
    return res.json() as Promise<T>
}

export interface PingResponse {
    status: string
    service: string
    time: string
}

export function ping(): Promise<PingResponse> {
    return api<PingResponse>('/ping')
}
