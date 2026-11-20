export function text2Clipboard(
    {
        text = '',
        callbackSuccess = () => {
        },
        callbackError = (_error: unknown) => {
        },
    }: {
        text?: string
        callbackSuccess?: () => void
        callbackError?: (error: unknown) => void
    } = {}
): void {
    window.navigator.clipboard
        .writeText(text)
        .then(() => callbackSuccess())
        .catch(callbackError)
}

export function textFromClipboard(callback: (text: string) => void): void {
    window.navigator.clipboard.readText().then(callback)
}
