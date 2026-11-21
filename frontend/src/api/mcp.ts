import { api } from './client'

export interface McpTokenResponse {
    token: string
    expiresAt: string
}

export interface McpInfoResponse {
    /** Plain build number: 2, 3, 4… */
    version: string
}

export function getMcpInfo(): Promise<McpInfoResponse> {
    return api<McpInfoResponse>('/mcp/info')
}

/** Issues a new MCP token; the previous one stops working. */
export function issueMcpToken(): Promise<McpTokenResponse> {
    return api<McpTokenResponse>('/mcp/token', { method: 'POST', body: '{}' })
}
