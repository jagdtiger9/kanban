export interface TreeNodeData {
    id: string
    title: string
    description: string
    columnId: string | null
    /** key of TreeData.types */
    type: string
}

export interface NodeType {
    name: string
    view: 'board' | 'column' | 'card'
}

export interface TreeData {
    types: Record<string, NodeType>
    project: string
    columns: { id: string, name: string }[]
    doneColumn: string
    rejectedColumn: string
    root: string
    nodes: Record<string, TreeNodeData>
    children: Record<string, string[]>
    parents: Record<string, string[]>
}

export interface Progress {
    done: number
    total: number
}
