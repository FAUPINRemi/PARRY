<script setup lang="ts">
import { computed } from 'vue'

interface Answer {
	playerId: string
	text: string
}

const props = defineProps<{
	roundNumber: number
	question: string
	answers: Answer[]
	revoteCandidates: string[] | null
	hasVoted: boolean
	amIAlive: boolean
	myUserId: string | null
	votedCount: number
	totalAlive: number
}>()

const emit = defineEmits<{
	(e: 'vote', targetPlayerId: string): void
}>()

const votableAnswers = computed(() => {
	if (!props.revoteCandidates) return props.answers
	return props.answers.filter(a => props.revoteCandidates!.includes(a.playerId))
})
</script>

<template>
	<h1 class="game-screen-title">
		Round  {{ roundNumber }}  — Vote
		<span v-if="revoteCandidates" class="revote-badge">Revote !</span>
	</h1>

	<div class="question-display">
		<p class="question-display--label">Question :</p>
		<p class="question-display--text"> {{ question }} </p>
	</div>

	<p class="phase-hint">
		Votez pour la réponse qui vous semble la plus suspecte (IA).
	</p>

	<div class="answers-grid">
		<div
			v-for="(ans, idx) in votableAnswers"
			:key="ans.playerId"
			class="answer-card"
			:class="{ 'answer-card--voted': hasVoted }"
		>
			<p class="answer-card--label">Réponse  {{ idx + 1 }} </p>
			<p class="answer-card--text"> {{ ans.text }} </p>

			<template v-if="amIAlive">
				<button
					v-if="!hasVoted && ans.playerId !== myUserId"
					class="gButton important answer-card--vote-btn"
					@click="emit('vote', ans.playerId)"
				>
					Voter cette réponse
				</button>

				<p v-else-if="ans.playerId === myUserId" class="phase-hint">(votre réponse)</p>
			</template>

			<p v-else class="phase-hint">Vous êtes éliminé — observation uniquement</p>
		</div>
	</div>

	<p class="phase-hint"> {{ votedCount }}  /  {{ totalAlive }}  votes</p>
</template>