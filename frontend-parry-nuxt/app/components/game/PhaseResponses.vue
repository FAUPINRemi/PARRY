<script setup lang="ts">
const props = defineProps<{
	roundNumber: number
	question: string
	hasAnswered: boolean
	answeredCount: number
	totalAlive: number
}>()
</script>

<template>
	<h1 class="game-screen-title">Round  {{ roundNumber }}  — Réponses</h1>

	<div class="question-display">
		<p class="question-display--label">Question :</p>
		<p class="question-display--text"> {{ question }} </p>
	</div>

	<div class="phase-box">
		<p v-if="!hasAnswered">
			Répondez dans le terminal ci-dessous.
		</p>
		<p v-else class="phase-success">
			Réponse envoyée ✓
		</p>

		<div class="answers-progress">
			<div
				class="answers-progress--bar"
				:style="{ width: totalAlive > 0 ? (answeredCount / totalAlive * 100) + '%' : '0%' }"
			></div>
		</div>

		<p class="phase-hint"> {{ answeredCount }}  /  {{ totalAlive }}  joueurs ont répondu</p>
	</div>
</template>