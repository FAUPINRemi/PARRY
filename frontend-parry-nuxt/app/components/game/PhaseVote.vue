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

const { playClick } = useGameAudio()

function onVote(targetPlayerId: string) {
	playClick()
	emit('vote', targetPlayerId)
}
</script>

<template>
	<h1 class="game-screen-title">
		Round {{ roundNumber }} — Vote
		<span v-if="revoteCandidates" class="revote-badge">Revote</span>
	</h1>

	<div class="question-display">
		<p class="question-display--label">Question</p>
		<p class="question-display--text">{{ question }}</p>
	</div>

	<p class="vote-instruction">
		<span v-if="amIAlive && !hasVoted">Quelle réponse vous semble la plus suspecte ?</span>
		<span v-else-if="hasVoted">Vote enregistré — {{ votedCount }} / {{ totalAlive }}</span>
		<span v-else>Partie en observation — {{ votedCount }} / {{ totalAlive }}</span>
	</p>

	<div class="vote-grid">
		<div
			v-for="(ans, idx) in votableAnswers"
			:key="ans.playerId"
			class="vote-card"
			:class="{
				'vote-card--mine':  ans.playerId === myUserId,
				'vote-card--voted': hasVoted,
			}"
		>
			<div class="vote-card__header">
				<span class="vote-card__num">{{ String(idx + 1).padStart(2, '0') }}</span>
				<span v-if="ans.playerId === myUserId" class="vote-card__tag">&gt; vous</span>
			</div>

			<p class="vote-card__text">{{ ans.text }}</p>

			<div class="vote-card__footer">
				<button
					v-if="amIAlive && !hasVoted && ans.playerId !== myUserId"
					class="gButton important vote-card__btn"
					@click="onVote(ans.playerId)"
				>
					Voter
				</button>
			</div>
		</div>
	</div>
</template>
