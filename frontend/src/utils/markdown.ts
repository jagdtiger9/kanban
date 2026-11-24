import { marked } from 'marked'
import DOMPurify from 'dompurify'

export function renderMarkdown(markdown: string): string {
    return DOMPurify.sanitize(marked.parse(markdown, { async: false, breaks: true }))
}

export const MARKDOWN_CLASS =
    '[&_a]:text-brand-text [&_a]:underline [&_blockquote]:border-l-2 [&_blockquote]:border-line-emphasis [&_blockquote]:pl-3 [&_blockquote]:text-muted [&_code]:rounded [&_code]:bg-raised [&_code]:px-1 [&_code]:py-0.5 [&_code]:text-sm [&_h1]:text-xl [&_h1]:font-semibold [&_h2]:text-lg [&_h2]:font-semibold [&_hr]:my-2 [&_hr]:border-line-strong [&_ol]:list-decimal [&_ol]:pl-5 [&_p]:my-1.5 [&_pre]:overflow-x-auto [&_pre]:rounded-lg [&_pre]:bg-raised [&_pre]:p-2 [&_strong]:font-semibold [&_ul]:list-disc [&_ul]:pl-5'
