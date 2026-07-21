<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue'

type Role = 'player' | 'proai'

const props = withDefaults(defineProps<{
	myRole: Role
	durationMs?: number
}>(), {
	durationMs: 7000,
})

const isProAi = computed(() => props.myRole === 'proai')

const BOOT_TEXT = '// ANALYSE EN COURS...'
const TITLE_TEXT = computed(() => isProAi.value ? 'PRO-IA' : 'JOUEUR')
const MISSION_TEXT = computed(() => isProAi.value
	? "Répondez comme l'IA. Si vous êtes éliminé en premier, vous gagnez."
	: "Une IA se cache parmi vous. Trouvez-la et éliminez-la.")

const GLITCH_CHARS = 'abcdefghijklmnopqrstuvwxyz@#$%&?!|/\\-_+*^~<>[]{}01░▒▓'
const UPDATE_INTERVAL = 35

const bootDisplay = ref('')
const titleDisplay = ref('')
const missionDisplay = ref('')
// Trois temps sur les 7s d'affichage : "scan" du joueur, puis stamp du rôle,
// puis la phrase de mission — comme un briefing qui se décrypte, pas un bloc
// ASCII qui apparaît d'un coup.
const stage = ref<'boot' | 'title' | 'mission' | 'done'>('boot')
const timers: Array<ReturnType<typeof setTimeout>> = []

function randomChar() {
	return GLITCH_CHARS[Math.floor(Math.random() * GLITCH_CHARS.length)]
}

function scrambleInto(text: string, durationMs: number, onUpdate: (v: string) => void, onDone: () => void) {
	const chars = text.split('')
	const totalTicks = Math.max(1, Math.round(durationMs / UPDATE_INTERVAL))
	let tick = 0

	const interval = setInterval(() => {
		tick++
		const revealFrom = Math.floor((tick / totalTicks) * chars.length)
		onUpdate(chars.map((c, i) => (c === ' ' || i < revealFrom) ? c : randomChar()).join(''))

		if (tick >= totalTicks) {
			clearInterval(interval)
			onUpdate(text)
			onDone()
		}
	}, UPDATE_INTERVAL)

	timers.push(interval)
}

onMounted(() => {
	scrambleInto(BOOT_TEXT, 900, v => (bootDisplay.value = v), () => {
		stage.value = 'title'
		timers.push(setTimeout(() => {
			scrambleInto(TITLE_TEXT.value, 320, v => (titleDisplay.value = v), () => {
				stage.value = 'mission'
				timers.push(setTimeout(() => {
					scrambleInto(MISSION_TEXT.value, 500, v => (missionDisplay.value = v), () => {
						stage.value = 'done'
					})
				}, 150))
			})
		}, 150))
	})
})

onUnmounted(() => {
	timers.forEach(t => clearTimeout(t))
})
</script>

<template>
	<div class="roleReveal" :class="isProAi ? 'roleReveal--proai' : 'roleReveal--player'">
		<p class="roleReveal-boot" :class="{ 'is-resolved': stage !== 'boot', 'is-typing': stage === 'boot' }">
			{{ bootDisplay }}
		</p>

		<div class="roleReveal-card">
			<span class="roleReveal-corner roleReveal-corner--tl" aria-hidden="true"></span>
			<span class="roleReveal-corner roleReveal-corner--tr" aria-hidden="true"></span>
			<span class="roleReveal-corner roleReveal-corner--bl" aria-hidden="true"></span>
			<span class="roleReveal-corner roleReveal-corner--br" aria-hidden="true"></span>

			<div v-if="isProAi" class="roleReveal-static" aria-hidden="true"></div>

			<div class="roleReveal-badge" :class="{ 'is-visible': stage !== 'boot' }">
				<Icon :name="isProAi ? 'pixelarticons:bug' : 'pixelarticons:eye'" class="roleReveal-icon" />
				<h2 class="roleReveal-title">{{ titleDisplay }}</h2>
			</div>

			<p class="roleReveal-mission" :class="{ 'is-visible': stage === 'mission' || stage === 'done' }">
				{{ missionDisplay }}
			</p>
		</div>

		<div class="roleReveal-timebar" :style="{ animationDuration: durationMs + 'ms' }" aria-hidden="true"></div>
	</div>
</template>

<style scoped lang="scss">
@use "@/assets/style/components/roleReveal.scss";
</style>
