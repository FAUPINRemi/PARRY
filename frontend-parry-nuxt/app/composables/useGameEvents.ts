import type { Ref } from 'vue'

export function useGameEvents(gameCode: Ref<string>, onEvent: () => void) {
    const config = useRuntimeConfig()
    const mercurePublicUrl = config.public.mercurePublicUrl as string

    let es: EventSource | null = null

    function connect() {
        if (!gameCode.value || !mercurePublicUrl) return
        const url = `${mercurePublicUrl}?topic=${encodeURIComponent('/game/' + gameCode.value)}`
        es = new EventSource(url)
        es.onmessage = () => onEvent()
        es.onerror = () => { /* auto-reconnects */ }
    }

    function disconnect() {
        es?.close()
        es = null
    }

    return { connect, disconnect }
}
