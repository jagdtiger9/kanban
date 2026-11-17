import { api } from './client'

export interface McpTokenResponse {
    token: string
    expiresAt: string
}

/** Issues a new MCP token; the previous one stops working. */
export function issueMcpToken(): Promise<McpTokenResponse> {
    return api<McpTokenResponse>('/mcp/token', { method: 'POST', body: '{}' })
}
