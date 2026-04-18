// Variables hors du composable — survivent entre les appels au sein de la même session de navigation
let mediaRecorder: MediaRecorder | null = null
let audioChunks: Blob[] = []
let currentStream: MediaStream | null = null

export function useMicrophone() {
    const { apiFetch } = useApi()

    // useState = partagé entre tous les composants qui appellent useMicrophone()
    const micEnabled     = useState<boolean>('mic:enabled',      () => false)
    const hasPermission  = useState<boolean>('mic:hasPermission', () => false)
    const isRecording    = useState<boolean>('mic:isRecording',   () => false)
    const isTranscribing = useState<boolean>('mic:isTranscribing', () => false)

    /**
     * Demande la permission micro au navigateur.
     * Teste immédiatement en ouvrant + fermant le stream (ne garde pas la piste active).
     */
    async function requestPermission(): Promise<boolean> {
        if (!import.meta.client) return false

        try {
            const test = await navigator.mediaDevices.getUserMedia({ audio: true })
            test.getTracks().forEach(t => t.stop())
            hasPermission.value = true
            micEnabled.value    = true
            return true
        } catch {
            hasPermission.value = false
            micEnabled.value    = false
            return false
        }
    }

    /**
     * Désactive le micro — arrête les pistes et remet les flags à false.
     */
    function disableMic(): void {
        micEnabled.value    = false
        hasPermission.value = false
        if (currentStream) {
            currentStream.getTracks().forEach(t => t.stop())
            currentStream = null
        }
    }

    /**
     * Démarre l'enregistrement. Ré-ouvre un stream à chaque fois
     * (permission déjà accordée → pas de dialog navigateur).
     * L'indicateur d'enregistrement du navigateur n'est visible que pendant la capture.
     */
    async function startRecording(): Promise<void> {
        if (!import.meta.client || isRecording.value) return

        try {
            currentStream = await navigator.mediaDevices.getUserMedia({ audio: true })
            audioChunks   = []

            const mimeType = MediaRecorder.isTypeSupported('audio/webm;codecs=opus')
                ? 'audio/webm;codecs=opus'
                : MediaRecorder.isTypeSupported('audio/webm')
                    ? 'audio/webm'
                    : 'audio/ogg'

            mediaRecorder = new MediaRecorder(currentStream, { mimeType })

            mediaRecorder.ondataavailable = (e) => {
                if (e.data.size > 0) audioChunks.push(e.data)
            }

            mediaRecorder.start()
            isRecording.value = true
        } catch {
            isRecording.value = false
        }
    }

    /**
     * Arrête l'enregistrement, convertit en base64 et appelle /api/stt.
     * Résout avec la transcription ou rejette en cas d'erreur.
     */
    function stopAndTranscribe(): Promise<string> {
        return new Promise((resolve, reject) => {
            if (!mediaRecorder || !isRecording.value) {
                resolve('')
                return
            }

            mediaRecorder.onstop = async () => {
                // Ferme les pistes → plus d'indicateur d'enregistrement
                if (currentStream) {
                    currentStream.getTracks().forEach(t => t.stop())
                    currentStream = null
                }

                isRecording.value    = false
                isTranscribing.value = true

                // mime_type sans codec pour Gemini (ex: "audio/webm" pas "audio/webm;codecs=opus")
                const fullMime   = mediaRecorder?.mimeType || 'audio/webm'
                const simpleMime = fullMime.split(';')[0]
                const blob       = new Blob(audioChunks, { type: simpleMime })

                try {
                    const base64     = await blobToBase64(blob)
                    const pureBase64 = base64.split(',')[1] // retire "data:audio/webm;base64,"

                    const res = await apiFetch('/api/stt', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ audio: pureBase64, mimeType: simpleMime }),
                    })

                    const data = await res.json()
                    isTranscribing.value = false

                    if (data.success) {
                        resolve(data.text || '')
                    } else {
                        reject(new Error(data.error || 'ERREUR_TRANSCRIPTION'))
                    }
                } catch (e) {
                    isTranscribing.value = false
                    reject(e)
                }
            }

            mediaRecorder.stop()
        })
    }

    function blobToBase64(blob: Blob): Promise<string> {
        return new Promise((resolve, reject) => {
            const reader = new FileReader()
            reader.onload  = () => resolve(reader.result as string)
            reader.onerror = reject
            reader.readAsDataURL(blob)
        })
    }

    return {
        micEnabled,
        hasPermission,
        isRecording,
        isTranscribing,
        requestPermission,
        disableMic,
        startRecording,
        stopAndTranscribe,
    }
}
