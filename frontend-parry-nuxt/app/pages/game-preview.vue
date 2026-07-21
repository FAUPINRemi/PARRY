<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import PlayersBar from '@/components/game/PlayersBar.vue'
import WaitingHub from '@/components/game/WaitingHub.vue'
import PhaseQuestion from '@/components/game/PhaseQuestion.vue'
import PhaseResponses from '@/components/game/PhaseResponses.vue'
import PhaseVote from '@/components/game/PhaseVote.vue'
import PhaseElimination from '@/components/game/PhaseElimination.vue'
import EndGame from '@/components/game/EndGame.vue'

// Outil de dev uniquement : rejoue chaque écran de partie avec des données
// factices (pas besoin de vrais joueurs / IA / Mercure) pour vérifier le
// responsive. Inaccessible en production.
if (!import.meta.dev) {
	throw createError({ statusCode: 404, statusMessage: 'Page Not Found' })
}

const route = useRoute()

const mockPlayers = [
	{ id: 'p1', nickname: 'Alice', isAlive: true, isAI: false },
	{ id: 'p2', nickname: 'Bob', isAlive: true, isAI: false },
	{ id: 'p3', nickname: 'Carla-Pseudo-Un-Peu-Long', isAlive: true, isAI: false },
	{ id: 'p4', nickname: 'IA-Suspecte', isAlive: false, isAI: true },
]

const mockAnswers = [
	{ playerId: 'p1', text: 'Les tacos, sans hésiter, avec beaucoup de fromage et de sauce piquante !' },
	{ playerId: 'p2', text: 'Sushis.' },
	{ playerId: 'p3', text: "Honnêtement je ne sais pas trop, peut-être des pâtes carbonara maison faites par ma grand-mère les dimanches d'hiver." },
]

const mockQuestion = 'Quel est ton plat préféré et pourquoi (donne un maximum de détails) ?'

function playerAlias(id: string) {
	return mockPlayers.find(p => p.id === id)?.nickname ?? id
}

const phase = computed(() => (route.query.phase as string) || 'waiting')

const phases = [
	'waiting', 'question', 'responses', 'vote', 'elimination',
	'finished-win', 'finished-lose', 'finished-proai-win', 'finished-proai-lose',
]
</script>

<template>
	<div class="gameWrap">
		<div class="gameGrid">
			<div class="leftCol">
				<PlayersBar
					:gameStatus="phase === 'waiting' ? 'waiting' : (phase.startsWith('finished') ? 'finished' : 'in_progress')"
					:players="mockPlayers"
					:roundNumber="2"
				/>

				<WaitingHub
					v-if="phase === 'waiting'"
					gameCode="ABC123"
					:players="mockPlayers"
					myUserId="p1"
					:isCreator="true"
					:enableProAI="false"
				/>

				<PhaseQuestion
					v-else-if="phase === 'question'"
					:roundNumber="2"
					:isMyTurnToAsk="false"
					questionMasterId="p2"
					:playerAlias="playerAlias"
				/>

				<PhaseResponses
					v-else-if="phase === 'responses'"
					:roundNumber="2"
					:question="mockQuestion"
					:hasAnswered="false"
					:answeredCount="1"
					:totalAlive="3"
				/>

				<PhaseVote
					v-else-if="phase === 'vote'"
					:roundNumber="2"
					:question="mockQuestion"
					:answers="mockAnswers"
					:revoteCandidates="null"
					:hasVoted="false"
					:amIAlive="true"
					myUserId="p1"
					:votedCount="1"
					:totalAlive="3"
				/>

				<PhaseElimination
					v-else-if="phase === 'elimination'"
					:players="mockPlayers"
					eliminatedPlayerId="p4"
				/>

				<EndGame
					v-else-if="phase === 'finished-win'"
					winner="PLAYERS_WIN"
					myRole="player"
					:isCreator="true"
					:startNewGame="async () => {}"
					:goToMenu="async () => {}"
				/>

				<EndGame
					v-else-if="phase === 'finished-lose'"
					winner="AI_WINS"
					myRole="player"
					:isCreator="true"
					:startNewGame="async () => {}"
					:goToMenu="async () => {}"
				/>

				<EndGame
					v-else-if="phase === 'finished-proai-win'"
					winner="PRO_IA_WINS"
					myRole="proai"
					:isCreator="true"
					:startNewGame="async () => {}"
					:goToMenu="async () => {}"
				/>

				<EndGame
					v-else-if="phase === 'finished-proai-lose'"
					winner="PRO_IA_WINS"
					myRole="player"
					:isCreator="true"
					:startNewGame="async () => {}"
					:goToMenu="async () => {}"
				/>
			</div>
		</div>
	</div>

	<nav class="devPreviewNav">
		<NuxtLink v-for="p in phases" :key="p" :to="`?phase=${p}`" :class="{ active: phase === p }">
			{{ p }}
		</NuxtLink>
	</nav>
</template>

<style lang="scss">
@use "@/assets/style/components/panelGame";
</style>

<style scoped lang="scss">
.devPreviewNav {
	position: fixed;
	bottom: 0;
	left: 0;
	right: 0;
	display: flex;
	flex-wrap: wrap;
	gap: 0.4rem;
	padding: 0.5rem;
	background: rgba(0, 0, 0, 0.92);
	border-top: 1px solid #333;
	z-index: 500;

	a {
		color: #aaa;
		font-size: 0.75rem;
		padding: 0.25rem 0.5rem;
		border: 1px solid #333;
		text-decoration: none;

		&.active {
			color: #fff;
			border-color: #666;
		}
	}
}
</style>
