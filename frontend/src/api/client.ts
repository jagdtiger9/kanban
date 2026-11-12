export class ApiError extends Error {
  constructor(
    readonly status: number,
    message: string,
  ) {
    super(message)
  }
}

export async function api<T>(path: string, init?: RequestInit): Promise<T> {
  const res = await fetch(`/api${path}`, {
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin',
    ...init,
  })
  if (!res.ok) {
    throw new ApiError(res.status, `API ${res.status}: ${await res.text()}`)
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
