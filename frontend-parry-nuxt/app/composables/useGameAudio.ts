import type { Ref } from 'vue'

export type MusicTrack = 'menu' | 'ambient' | 'role' | 'victory' | 'defeat'

const MUSIC_FILES: Record<MusicTrack, string> = {
	menu: '/audio/music/menu.mp3',
	ambient: '/audio/music/ambient.mp3',
	role: '/audio/music/role.mp3',
	victory: '/audio/music/victory.mp3',
	defeat: '/audio/music/defeat.mp3',
}

const STINGER_FILES = {
	elimination: '/audio/music/elimination.mp3',
}

const SFX_FILES = {
	click: '/audio/sfx/click.mp3',
}

// Fondu léger appliqué au point de bouclage (seul le menu boucle assez longtemps pour qu'on l'entende)
const MENU_LOOP_DIP_MS = 450

interface Voice {
	source: AudioBufferSourceNode
	gain: GainNode
	track: MusicTrack
	dipInterval: ReturnType<typeof setInterval> | null
}

let audioContext: AudioContext | null = null
let musicGain: GainNode | null = null
let sfxGain: GainNode | null = null
let activeVoices: Voice[] = []
let generation = 0
let visibilityBound = false

const buffers = new Map<string, Promise<AudioBuffer | null>>()

function readPersisted<T>(key: string, fallback: T): T {
	if (!import.meta.client) return fallback
	try {
		const raw = localStorage.getItem(key)
		return raw !== null ? (JSON.parse(raw) as T) : fallback
	} catch {
		return fallback
	}
}

function writePersisted(key: string, value: unknown) {
	if (!import.meta.client) return
	try { localStorage.setItem(key, JSON.stringify(value)) } catch { /* stockage indisponible */ }
}

let musicVolume: Ref<number>
let sfxVolume: Ref<number>
let muted: Ref<boolean>
let unlocked: Ref<boolean>
let currentTrack: Ref<MusicTrack | null>

// `useState` doit être appelé depuis un contexte Nuxt valide (setup/plugin),
// jamais à l'évaluation du module : on l'initialise donc au premier appel de useGameAudio().
function ensureState() {
	if (musicVolume) return
	musicVolume = useState<number>('audio:musicVolume', () => readPersisted('parry:musicVolume', 0.6))
	sfxVolume = useState<number>('audio:sfxVolume', () => readPersisted('parry:sfxVolume', 0.8))
	muted = useState<boolean>('audio:muted', () => readPersisted('parry:muted', false))
	unlocked = useState<boolean>('audio:unlocked', () => false)
	currentTrack = useState<MusicTrack | null>('audio:currentTrack', () => null)
}

function applyGains() {
	if (!musicGain || !sfxGain) return
	musicGain.gain.value = muted.value ? 0 : musicVolume.value
	sfxGain.gain.value = muted.value ? 0 : sfxVolume.value
}

function bindVisibilityHandling() {
	if (visibilityBound || !import.meta.client) return
	visibilityBound = true
	document.addEventListener('visibilitychange', () => {
		if (!audioContext) return
		if (document.hidden) {
			audioContext.suspend().catch(() => {})
		} else if (unlocked.value) {
			audioContext.resume().catch(() => {})
		}
	})
}

function ensureContext(): AudioContext {
	if (!audioContext || audioContext.state === 'closed') {
		audioContext = new AudioContext()
		musicGain = audioContext.createGain()
		sfxGain = audioContext.createGain()
		musicGain.connect(audioContext.destination)
		sfxGain.connect(audioContext.destination)
		applyGains()
		bindVisibilityHandling()
	}
	return audioContext
}

async function loadBuffer(url: string): Promise<AudioBuffer | null> {
	if (!buffers.has(url)) {
		buffers.set(url, (async () => {
			try {
				const ctx = ensureContext()
				const res = await fetch(url)
				const arrayBuffer = await res.arrayBuffer()
				return await ctx.decodeAudioData(arrayBuffer)
			} catch {
				return null
			}
		})())
	}
	return buffers.get(url)!
}

function fadeOutAndStop(voice: Voice, fadeMs: number) {
	if (voice.dipInterval) clearInterval(voice.dipInterval)
	const ctx = audioContext
	if (!ctx) return
	const now = ctx.currentTime
	try {
		voice.gain.gain.cancelScheduledValues(now)
		if (fadeMs <= 0) {
			voice.gain.gain.value = 0
			voice.source.stop()
		} else {
			voice.gain.gain.setValueAtTime(voice.gain.gain.value, now)
			voice.gain.gain.linearRampToValueAtTime(0, now + fadeMs / 1000)
			voice.source.stop(now + fadeMs / 1000 + 0.05)
		}
	} catch { /* déjà arrêtée */ }
}

/** Lance un morceau en remplaçant celui en cours (fondu croisé). */
async function playMusic(
	track: MusicTrack,
	opts: { fadeMs?: number; loop?: boolean; loopDip?: boolean } = {}
): Promise<void> {
	const { fadeMs = 700, loop = true, loopDip = false } = opts
	const myGen = ++generation

	const previous = activeVoices
	activeVoices = []
	previous.forEach(v => fadeOutAndStop(v, fadeMs))

	const buffer = await loadBuffer(MUSIC_FILES[track])
	if (!buffer || myGen !== generation) return // supplanté pendant le chargement

	const ctx = ensureContext()
	const source = ctx.createBufferSource()
	source.buffer = buffer
	source.loop = loop

	const gain = ctx.createGain()
	source.connect(gain)
	gain.connect(musicGain!)

	const now = ctx.currentTime
	gain.gain.setValueAtTime(0, now)
	gain.gain.linearRampToValueAtTime(1, now + fadeMs / 1000)

	let dipInterval: ReturnType<typeof setInterval> | null = null
	if (loop && loopDip && buffer.duration > 1) {
		dipInterval = setInterval(() => {
			const t = ctx.currentTime
			gain.gain.cancelScheduledValues(t)
			gain.gain.setValueAtTime(gain.gain.value, t)
			gain.gain.linearRampToValueAtTime(0.55, t + MENU_LOOP_DIP_MS / 2000)
			gain.gain.linearRampToValueAtTime(1, t + MENU_LOOP_DIP_MS / 1000)
		}, buffer.duration * 1000)
	}

	source.start()
	activeVoices = [{ source, gain, track, dipInterval }]
	currentTrack.value = track

	// Morceau one-shot (rôle, etc.) : on attend la fin naturelle (ou un stop() forcé par un appel suivant)
	if (!loop) {
		await new Promise<void>(resolve => { source.onended = () => resolve() })
	}
}

/** Coupe la musique en cours sans en relancer une autre. */
function stopMusic(opts: { fadeMs?: number } = {}) {
	const { fadeMs = 400 } = opts
	generation++
	const previous = activeVoices
	activeVoices = []
	previous.forEach(v => fadeOutAndStop(v, fadeMs))
	currentTrack.value = null
}

function duckMusic(toLevel = 0.18, ms = 300): Voice | null {
	const voice = activeVoices[0]
	if (!voice || !audioContext) return null
	const t = audioContext.currentTime
	voice.gain.gain.cancelScheduledValues(t)
	voice.gain.gain.setValueAtTime(voice.gain.gain.value, t)
	voice.gain.gain.linearRampToValueAtTime(toLevel, t + ms / 1000)
	return voice
}

function restoreMusicVolume(voice: Voice | null, ms = 500) {
	if (!voice || !audioContext || !activeVoices.includes(voice)) return // supplantée depuis, on ne ressuscite pas
	const t = audioContext.currentTime
	voice.gain.gain.cancelScheduledValues(t)
	voice.gain.gain.setValueAtTime(voice.gain.gain.value, t)
	voice.gain.gain.linearRampToValueAtTime(1, t + ms / 1000)
}

// Le fichier source est mastérisé très bas (quasi inaudible à gain 1.0) :
// on le booste x7, avec un limiteur pour absorber les pics et éviter que
// ce boost ne fasse écrêter/distordre le son.
const ELIMINATION_GAIN_BOOST = 7

/** Tamise la musique en cours, joue le stinger d'élimination, puis remonte le son (sauf si la partie a changé de morceau pendant ce temps). */
async function playEliminationSequence(): Promise<void> {
	const ducked = duckMusic(0.18, 300)
	const buffer = await loadBuffer(STINGER_FILES.elimination)
	if (buffer) {
		const ctx = ensureContext()
		const source = ctx.createBufferSource()
		source.buffer = buffer

		const gain = ctx.createGain()
		gain.gain.value = ELIMINATION_GAIN_BOOST

		const limiter = ctx.createDynamicsCompressor()
		limiter.threshold.value = -6
		limiter.knee.value = 6
		limiter.ratio.value = 12
		limiter.attack.value = 0.003
		limiter.release.value = 0.15

		source.connect(gain)
		gain.connect(limiter)
		limiter.connect(musicGain!)
		await new Promise<void>(resolve => {
			source.onended = () => resolve()
			source.start()
		})
	}
	restoreMusicVolume(ducked, 500)
}

async function playClick(): Promise<void> {
	const buffer = await loadBuffer(SFX_FILES.click)
	if (!buffer) return
	const ctx = ensureContext()
	const source = ctx.createBufferSource()
	source.buffer = buffer
	source.connect(sfxGain!)
	source.start()
}

function preloadAll() {
	Object.values(MUSIC_FILES).forEach(url => loadBuffer(url))
	Object.values(STINGER_FILES).forEach(url => loadBuffer(url))
	Object.values(SFX_FILES).forEach(url => loadBuffer(url))
}

async function unlock(): Promise<void> {
	const ctx = ensureContext()
	if (ctx.state === 'suspended') {
		try { await ctx.resume() } catch { /* sera retenté au prochain geste */ }
	}
	applyGains()
	unlocked.value = true
	if (import.meta.client) sessionStorage.setItem('parry:audioUnlockSeen', '1')
}

function hasSeenUnlockPrompt(): boolean {
	if (!import.meta.client) return false
	return sessionStorage.getItem('parry:audioUnlockSeen') === '1'
}

function setMusicVolume(v: number) {
	musicVolume.value = Math.min(1, Math.max(0, v))
	writePersisted('parry:musicVolume', musicVolume.value)
	applyGains()
}

function setSfxVolume(v: number) {
	sfxVolume.value = Math.min(1, Math.max(0, v))
	writePersisted('parry:sfxVolume', sfxVolume.value)
	applyGains()
}

function setMuted(v: boolean) {
	muted.value = v
	writePersisted('parry:muted', v)
	applyGains()
}

export function useGameAudio() {
	ensureState()
	return {
		unlocked,
		muted,
		musicVolume,
		sfxVolume,
		currentTrack,

		unlock,
		hasSeenUnlockPrompt,
		preloadAll,

		playMusic,
		stopMusic,
		playEliminationSequence,
		playClick,

		setMusicVolume,
		setSfxVolume,
		setMuted,
	}
}
