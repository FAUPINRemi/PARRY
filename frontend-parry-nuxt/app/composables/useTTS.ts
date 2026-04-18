// AudioContext survit entre les appels (évite la recréation à chaque synthèse)
let audioContext: AudioContext | null = null
let currentSource: AudioBufferSourceNode | null = null

export function useTTS() {
    const { apiFetch } = useApi()

    const ttsEnabled = useState<boolean>('tts:enabled', () => false)
    const isSpeaking = useState<boolean>('tts:speaking', () => false)

    function getAudioContext(): AudioContext {
        // AudioContext avec 24kHz car Gemini TTS retourne du PCM 24kHz
        if (!audioContext || audioContext.state === 'closed') {
            audioContext = new AudioContext({ sampleRate: 24000 })
        }
        return audioContext
    }

    /**
     * Envoie le texte à /api/tts et joue l'audio reçu.
     * L'audio retourné est du PCM 16-bit 24kHz mono encodé en base64.
     */
    async function speak(text: string): Promise<void> {
        if (!import.meta.client || !ttsEnabled.value || !text.trim()) return

        // Arrête la synthèse en cours si elle existe
        stop()
        isSpeaking.value = true

        try {
            const res = await apiFetch('/api/tts', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ text }),
            })

            const data = await res.json()
            if (!data.success || !data.audio) {
                isSpeaking.value = false
                return
            }

            // base64 → Uint8Array
            const binaryStr = atob(data.audio)
            const bytes = new Uint8Array(binaryStr.length)
            for (let i = 0; i < binaryStr.length; i++) {
                bytes[i] = binaryStr.charCodeAt(i)
            }

            // PCM 16-bit signé → Float32 [-1, 1] pour Web Audio API
            const int16 = new Int16Array(bytes.buffer)
            const float32 = new Float32Array(int16.length)
            for (let i = 0; i < int16.length; i++) {
                float32[i] = int16[i] / 32768
            }

            const ctx = getAudioContext()

            // Reprend le contexte s'il est suspendu (politique autoplay navigateur)
            if (ctx.state === 'suspended') {
                await ctx.resume()
            }

            const buffer = ctx.createBuffer(1, float32.length, 24000)
            buffer.copyToChannel(float32, 0)

            currentSource = ctx.createBufferSource()
            currentSource.buffer = buffer
            currentSource.connect(ctx.destination)
            currentSource.onended = () => {
                isSpeaking.value = false
                currentSource = null
            }
            currentSource.start()

        } catch {
            isSpeaking.value = false
        }
    }

    /**
     * Arrête la lecture en cours immédiatement.
     */
    function stop(): void {
        if (currentSource) {
            try { currentSource.stop() } catch { /* déjà arrêté */ }
            currentSource = null
        }
        isSpeaking.value = false
    }

    return { ttsEnabled, isSpeaking, speak, stop }
}
