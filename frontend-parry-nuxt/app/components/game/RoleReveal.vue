<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue'

type Role = 'player' | 'proai'

const props = defineProps<{
	myRole: Role
}>()

const isProAi = computed(() => props.myRole === 'proai')

const PROAI_MESSAGE = `
  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _

   V  O  U  S     Ê  T  E  S     L  E     P  R  O  -  I  A


   R  é  p  o  n  d  e  z     c  o  m  m  e     l  '  I  A  .
   S  i     v  o  u  s     ê  t  e  s     é  l  i  m  i  n  é
   e  n     p  r  e  m  i  e  r  ,     v  o  u  s     g  a  g  n  e  z  .

  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _`

const DEBUSQUEZ_MESSAGE = `
  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _

   D  É  B  U  S  Q  U  E  Z     L  '  I  A


   U  n  e     I  A     s  e     c  a  c  h  e     p  a  r  m  i     v  o  u  s  .
   T  r  o  u  v  e  z  -  l  a     e  t     é  l  i  m  i  n  e  z  -  l  a  .

  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _  _`

const GLITCH_CHARS = 'abcdefghijklmnopqrstuvwxyz@#$%&?!|/\\-_+*^~<>[]{}01░▒▓'
const REVEAL_DURATION = 2500
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
	startGlitchReveal(isProAi.value ? PROAI_MESSAGE : DEBUSQUEZ_MESSAGE)
})

onUnmounted(() => {
	if (timer) clearInterval(timer)
})
</script>

<template>
	<div class="roleReveal" :class="isProAi ? 'roleReveal--proai' : 'roleReveal--player'">
		<pre class="roleReveal-glitch">{{ glitchDisplay }}</pre>
	</div>
</template>

<style scoped lang="scss">
@use "@/assets/style/components/roleReveal.scss";
</style>
