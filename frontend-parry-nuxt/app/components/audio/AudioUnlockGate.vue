<script setup lang="ts">
import { onMounted, ref } from 'vue'

const { unlock, hasSeenUnlockPrompt, setMuted, preloadAll } = useGameAudio()

const visible = ref(false)

onMounted(() => {
	preloadAll()
	visible.value = !hasSeenUnlockPrompt()
})

async function enableSound() {
	await unlock()
	setMuted(false)
	visible.value = false
}

async function continueWithoutSound() {
	await unlock()
	setMuted(true)
	visible.value = false
}
</script>

<template>
	<div v-if="visible" class="audioGateOverlay">
		<div class="audioGate">
			<Icon name="pixelarticons:volume" class="audioGate-icon" />
			<h2 class="audioGate-title">Jouer avec le son ?</h2>
			<p class="audioGate-text">
				PARRY est plus immersif avec l'ambiance sonore et les effets du jeu.
			</p>

			<div class="audioGate-actions">
				<button class="gButton important" @click="enableSound">
					<Icon name="pixelarticons:volume" />
					Activer le son
				</button>
				<button class="gButton" @click="continueWithoutSound">
					<Icon name="pixelarticons:volume-x" />
					Continuer sans son
				</button>
			</div>
		</div>
	</div>
</template>

<style scoped lang="scss">
@use "@/assets/style/components/audioGate";
</style>
