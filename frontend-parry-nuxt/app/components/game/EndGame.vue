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

// ── ASCII art victoire joueurs ──────────────────────────────────────────────
const ASCII_WIN = `
  _   _ ___ ____ _____ ___  ___ ___ _  _____   _
 | | | |_  _/ ___|_   _/ _ \\|_ _|| _ \\| ____| | |
 | | | ||  | |     | || | | | | | | |_) |  _|   | |
 | |_| ||  | |___  | || |_| | | | |  _ <| |___  |_|
 \\___/|___\\____| |_|\\___/ |___||_| \_\_____| (_)`

const AI_MESSAGE = `
  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _

        Ah.           Ah.           Ah.

  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _


   J  e     g  a  g  n  e     t  o  u  j  o  u  r  s  .


   J  e     g  a  g  n  e     t  o  u  j  o  u  r  s  .


   J  e     g  a  g  n  e     t  o  u  j  o  u  r  s  .


  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _`

const GLITCH_CHARS = 'abcdefghijklmnopqrstuvwxyz@#$%&?!|/\\-_+*^~<>[]{}01░▒▓'
const REVEAL_DURATION = 13000
const UPDATE_INTERVAL = 80

const glitchDisplay = ref('')
let timer: ReturnType<typeof setInterval> | null = null

function randomChar() {
	return GLITCH_CHARS[Math.floor(Math.random() * GLITCH_CHARS.length)]
}

function startGlitchReveal(target: string) {
	const chars = target.split('')
	const revealable = chars
		.map((c, i) => (c !== '\n' && c !== ' ') ? i : -1)
		.filter(i => i >= 0)
		.sort(() => Math.random() - 0.5)

	const revealed = new Set<number>()
	const totalTicks = REVEAL_DURATION / UPDATE_INTERVAL

	let tick = 0

	glitchDisplay.value = chars
		.map(c => (c === '\n' || c === ' ') ? c : randomChar())
		.join('')

	timer = setInterval(() => {
		tick++
		const progress = tick / totalTicks
		const targetRevealed = Math.floor(progress * revealable.length)

		while (revealed.size < targetRevealed && revealable.length > revealed.size) {
			revealed.add(revealable[revealed.size])
		}

		glitchDisplay.value = chars.map((c, i) => {
			if (c === '\n' || c === ' ') return c
			if (revealed.has(i)) return c
			return Math.random() > 0.4 ? randomChar() : (glitchDisplay.value[i] ?? randomChar())
		}).join('')

		if (tick >= totalTicks) {
			glitchDisplay.value = target
			clearInterval(timer!)
			timer = null
		}
	}, UPDATE_INTERVAL)
}

onMounted(() => {
	if (!isPlayersWin.value) {
		startGlitchReveal(AI_MESSAGE)
	}
})

onUnmounted(() => {
	if (timer) clearInterval(timer)
})
</script>

<template>
	<div class="endGame" :class="isPlayersWin ? 'endGame--win' : 'endGame--lose'">

		<template v-if="isPlayersWin">
			<pre class="endGame-ascii endGame-ascii--win">{{ ASCII_WIN }}</pre>
			<div class="endGame-body endGame-body--win">
				<p>Les joueurs ont eliminé l'IA.</p>
				<p v-if="myRole === 'proai'" class="endGame-proai">
					[ ROLE : PRO-IA — vous aidiez secretement l'IA ]
				</p>
			</div>
		</template>

		<template v-else>
			<div class="endGame-lose-layout">
				<div class="endGame-lose-left">
					<pre class="endGame-glitch">{{ glitchDisplay }}</pre>
					<div class="endGame-body endGame-body--lose">
						<p>L'IA a survécu jusqu'à la fin.</p>
						<p v-if="myRole === 'proai'" class="endGame-proai">
							[ ROLE : PRO-IA — vous aidiez secretement l'IA ]
						</p>
					</div>
				</div>
			</div>
		</template>

		<div class="endGame-actions">
			<button class="gButton" @click="goToMenu">
				<Icon name="pixelarticons:arrow-left" />
				Retour au menu
			</button>
			<button v-if="isCreator" class="gButton important" @click="startNewGame">
				<Icon name="pixelarticons:reload" />
				Relancer une partie
			</button>
		</div>
	</div>
</template>

<style scoped lang="scss">
.endGame {
	display: flex;
	flex-direction: column;
	gap: 1.2rem;
	padding: 0.5rem 0;

	&--win  { --accent: #4caf50; }
	&--lose { --accent: #f44336; }
}

.endGame-ascii {
	font-family: monospace;
	font-size: 0.65rem;
	line-height: 1.3;
	white-space: pre;
	overflow-x: auto;
	margin: 0;

	&--win { color: #4caf50; }
}

.endGame-body {
	display: flex;
	flex-direction: column;
	gap: 0.4rem;
	border-left: 2px solid var(--accent);
	padding-left: 0.8rem;
	font-size: 0.95rem;

	&--lose { border-color: #f44336; }
	&--win  { border-color: #4caf50; }
}

.endGame-proai {
	font-size: 0.8rem;
	color: #ff9800;
}

.endGame-lose-layout {
	display: flex;
	gap: 1.5rem;
	align-items: flex-start;
}

.endGame-lose-left {
	display: flex;
	flex-direction: column;
	gap: 1rem;
	flex: 1;
	min-width: 0;
}

.endGame-glitch {
	font-family: monospace;
	font-size: 0.65rem;
	line-height: 1.4;
	white-space: pre;
	overflow-x: auto;
	margin: 0;
	color: #f44336;
}

.endGame-actions {
	display: flex;
	gap: 0.8rem;
	flex-wrap: wrap;
	margin-top: 0.5rem;
}
</style>
