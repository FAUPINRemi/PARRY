<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue'

type Role = 'player' | 'proai'
type Winner = 'PLAYERS_WIN' | 'AI_WINS' | string | null

const props = defineProps<{
	winner: Winner
	myRole: Role
	isCreator: boolean
	startNewGame: () => Promise<void> | void
	goToMenu: () => Promise<void> | void
}>()

const isPlayersWin = computed(() => props.winner === 'PLAYERS_WIN')
const isProAiWin = computed(() => props.winner === 'PRO_IA_WINS')
const iAmTheWinningProAi = computed(() => isProAiWin.value && props.myRole === 'proai')
// "Gagnant" au sens visuel (flash + confettis) : les joueurs qui éliminent l'IA,
// ou le Pro-IA qui a réussi son coup en se faisant éliminer en premier.
const isWinLike = computed(() => isPlayersWin.value || iAmTheWinningProAi.value)

interface ConfettiPiece {
	left: number
	color: string
	delay: number
	duration: number
	drift: number
	rotate: number
}

const CONFETTI_COLORS = ['#4caf50', '#8bc34a', '#ffffff', '#ffd54f', '#4dd0e1']
const CONFETTI_COLORS_PROAI = ['#ff9800', '#ffb74d', '#ffffff', '#ffd54f']

const confetti = ref<ConfettiPiece[]>([])

function spawnConfetti() {
	const palette = iAmTheWinningProAi.value ? CONFETTI_COLORS_PROAI : CONFETTI_COLORS
	confetti.value = Array.from({ length: 28 }, () => ({
		left: Math.random() * 100,
		color: palette[Math.floor(Math.random() * palette.length)],
		delay: Math.random() * 0.5,
		duration: 1.6 + Math.random() * 1.2,
		drift: (Math.random() - 0.5) * 70,
		rotate: 320 + Math.random() * 360,
	}))
}

// Écran de défaite : quelques phrases courtes qui se glitchent une à une
// (texte normal, pas d'ASCII à largeur fixe) pour rester lisible sans
// scroll horizontal sur mobile ; l'impact vient du tremblement d'écran /
// de la vignette rouge autour, pas de la largeur du texte.
const LOSE_PHRASES = ['Ah.', 'Ah.', 'Ah.', 'Je gagne toujours.']
// Le Pro-IA a réussi son coup : les autres joueurs l'ont éliminé en premier en
// le prenant pour l'IA — même traitement visuel que la défaite classique,
// juste un message et un accent différents (orange).
const PROAI_LOSE_PHRASES = ['Merci.', 'Merci.', 'Merci.', 'Vous avez éliminé la mauvaise personne.', 'Le Pro-IA gagne.']
const losePhrases = computed(() => isProAiWin.value ? PROAI_LOSE_PHRASES : LOSE_PHRASES)

const SCRAMBLE_MS = 260
const PHRASE_HOLD_MS = 700
const UPDATE_INTERVAL = 35
const GLITCH_CHARS = 'abcdefghijklmnopqrstuvwxyz@#$%&?!|/\\-_+*^~<>[]{}01░▒▓'

const glitchDisplay = ref('')
const shaking = ref(false)
const bodyRevealed = ref(false)
const textGlitching = ref(false)
const timers: Array<ReturnType<typeof setTimeout>> = []

// Une fois l'écran de défaite stabilisé (après les phrases), le texte se
// remet à glitcher de temps en temps, à intervalles aléatoires — comme les
// carrés rouges, pour la même ambiance chaotique, mais pas pendant le reveal.
function scheduleTextGlitch() {
	const delay = 1000 + Math.random() * 2400
	timers.push(setTimeout(() => {
		textGlitching.value = true
		timers.push(setTimeout(() => {
			textGlitching.value = false
		}, 160 + Math.random() * 160))
		scheduleTextGlitch()
	}, delay))
}

interface GlitchPixel {
	left: number
	top: number
	size: number
	delay: number
	duration: number
}

// Carrés qui clignotent au hasard un peu partout sur l'écran, en boucle
// continue (délais négatifs = chaque pièce démarre en plein milieu de son
// propre cycle, donc elles ne clignotent jamais toutes ensemble).
const glitchPixels = ref<GlitchPixel[]>([])

function spawnGlitchPixels() {
	glitchPixels.value = Array.from({ length: 16 }, () => ({
		left: Math.random() * 100,
		top: Math.random() * 100,
		size: 3 + Math.random() * 7,
		delay: -(Math.random() * 4),
		duration: 1.6 + Math.random() * 2.2,
	}))
}

function randomChar() {
	return GLITCH_CHARS[Math.floor(Math.random() * GLITCH_CHARS.length)]
}

function scrambleReveal(text: string, onDone: () => void) {
	const chars = text.split('')
	const totalTicks = Math.max(1, Math.round(SCRAMBLE_MS / UPDATE_INTERVAL))
	let tick = 0

	const interval = setInterval(() => {
		tick++
		const revealFrom = Math.floor((tick / totalTicks) * chars.length)
		glitchDisplay.value = chars
			.map((c, i) => (c === ' ' || i < revealFrom) ? c : randomChar())
			.join('')

		if (tick >= totalTicks) {
			clearInterval(interval)
			glitchDisplay.value = text
			onDone()
		}
	}, UPDATE_INTERVAL)

	timers.push(interval)
}

function playLoseSequence(index: number) {
	const phrases = losePhrases.value
	if (index >= phrases.length) {
		shaking.value = false
		bodyRevealed.value = true
		scheduleTextGlitch()
		return
	}

	shaking.value = true
	scrambleReveal(phrases[index], () => {
		timers.push(setTimeout(() => playLoseSequence(index + 1), PHRASE_HOLD_MS))
	})
}

onMounted(() => {
	if (isWinLike.value) {
		bodyRevealed.value = true
		spawnConfetti()
	} else {
		playLoseSequence(0)
		spawnGlitchPixels()
	}
})

onUnmounted(() => {
	timers.forEach(t => clearTimeout(t))
})

const { playClick, playMusic } = useGameAudio()

function onGoToMenu() {
	playClick()
	// coupure nette + relance immédiate : si on quitte vite, la musique de fin ne doit pas traîner
	playMusic('menu', { fadeMs: 0, loop: true, loopDip: true })
	props.goToMenu()
}

function onStartNewGame() {
	playClick()
	playMusic('menu', { fadeMs: 0, loop: true, loopDip: true })
	props.startNewGame()
}
</script>

<template>
	<div
		class="endGame"
		:class="isProAiWin ? (iAmTheWinningProAi ? 'endGame--proai-win' : 'endGame--proai-lose') : (isPlayersWin ? 'endGame--win' : 'endGame--lose')"
	>

		<div v-if="!isWinLike" class="endGame-glitchPixels" aria-hidden="true">
			<span
				v-for="(p, i) in glitchPixels"
				:key="i"
				class="endGame-glitchPixels-piece"
				:class="{ 'endGame-glitchPixels-piece--proai': isProAiWin }"
				:style="{
					left: p.left + '%',
					top: p.top + '%',
					width: p.size + 'px',
					height: p.size + 'px',
					animationDelay: p.delay + 's',
					animationDuration: p.duration + 's',
				}"
			></span>
		</div>

		<template v-if="isWinLike">
			<div class="endGame-winStage">
				<div class="endGame-flash" :class="{ 'endGame-flash--proai': iAmTheWinningProAi }"></div>
				<h2 class="endGame-title" :class="iAmTheWinningProAi ? 'endGame-title--proai' : 'endGame-title--win'">
					{{ iAmTheWinningProAi ? 'MISSION ACCOMPLIE' : 'VICTOIRE' }}
				</h2>
				<div class="endGame-confetti">
					<span
						v-for="(c, i) in confetti"
						:key="i"
						class="endGame-confetti-piece"
						:style="{
							left: c.left + '%',
							backgroundColor: c.color,
							animationDelay: c.delay + 's',
							animationDuration: c.duration + 's',
							'--drift': c.drift + 'px',
							'--rotate': c.rotate + 'deg',
						}"
					></span>
				</div>
			</div>
			<div class="endGame-body" :class="iAmTheWinningProAi ? ['endGame-body--win', 'endGame-body--proai'] : 'endGame-body--win'">
				<template v-if="iAmTheWinningProAi">
					<p>Vous avez été éliminé en premier, exactement comme prévu.</p>
					<p class="endGame-proai">[ ROLE : PRO-IA — mission accomplie ]</p>
				</template>
				<template v-else>
					<p>Les joueurs ont eliminé l'IA.</p>
					<p v-if="myRole === 'proai'" class="endGame-proai">
						[ ROLE : PRO-IA — vous aidiez secretement l'IA ]
					</p>
				</template>
			</div>
		</template>

		<template v-else>
			<div
				class="endGame-shock"
				:class="{ 'endGame-shock--active': shaking, 'endGame-shock--proai': isProAiWin }"
			>
				<div class="endGame-vignette" :class="{ 'endGame-vignette--proai': isProAiWin }"></div>
				<p class="endGame-phrase" :class="{ 'is-glitching': textGlitching, 'endGame-phrase--proai': isProAiWin }">{{ glitchDisplay }}</p>
			</div>

			<div
				class="endGame-body endGame-body--lose"
				:class="{ 'is-revealed': bodyRevealed, 'is-glitching': textGlitching, 'endGame-body--proai': isProAiWin }"
			>
				<template v-if="isProAiWin">
					<p>Le Pro-IA s'est fait éliminer en premier et remporte la partie.</p>
				</template>
				<template v-else>
					<p>L'IA a survécu jusqu'à la fin.</p>
					<p v-if="myRole === 'proai'" class="endGame-proai">
						[ ROLE : PRO-IA — vous aidiez secretement l'IA ]
					</p>
				</template>
			</div>
		</template>

		<div class="endGame-actions">
			<button class="gButton" @click="onGoToMenu">
				<Icon name="pixelarticons:arrow-left" />
				Retour au menu
			</button>
			<button v-if="isCreator" class="gButton important" @click="onStartNewGame">
				<Icon name="pixelarticons:reload" />
				Relancer une partie
			</button>
		</div>
	</div>
</template>

<style scoped lang="scss">
@use "@/assets/style/components/panelEndGame.scss";
</style>
