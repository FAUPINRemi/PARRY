<script setup lang="ts">
const props = defineProps<{
	roundNumber: number
	isMyTurnToAsk: boolean
	questionMasterId: string | null
	playerAlias: (playerId: string) => string
}>()
</script>

<template>
	<h1 class="game-screen-title">Round  {{ roundNumber }}  — Question</h1>

	<div v-if="isMyTurnToAsk" class="phase-box phase-box--mine">
		<p>C'est votre tour de poser une question.</p>
		<p class="phase-hint">Écrivez votre question dans le terminal ci-dessous.</p>
	</div>

	<div v-else class="phase-box">
		<p>
			<strong> {{playerAlias(questionMasterId ?? '')}} </strong>
			est en train de poser la question…
		</p>
		<div class="loading-dots"><span></span><span></span><span></span></div>
	</div>
</template>