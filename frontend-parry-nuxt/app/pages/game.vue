<script setup lang="ts">
import PlayersBar from '@/components/game/PlayersBar.vue'
import WaitingHub from '@/components/game/WaitingHub.vue'
import PhaseQuestion from '@/components/game/PhaseQuestion.vue'
import PhaseResponses from '@/components/game/PhaseResponses.vue'
import PhaseVote from '@/components/game/PhaseVote.vue'
import PhaseElimination from '@/components/game/PhaseElimination.vue'
import EndGame from '@/components/game/EndGame.vue'

import { useGame } from '@/composables/game/useGame'
import { useGameMusic } from '@/composables/game/useGameMusic'

const {
	gameCode,
	gameStatus,
	players,

	roundNumber,
	roundStatus,

	question,
	questionMasterId,

	answeredCount,
	votedCount,
	totalAlive,

	answers,
	revoteCandidates,
	eliminatedPlayerId,

	winner,
	proAiActive,

	myUserId,
	isCreator,

	myRole,
	hasAnswered,
	hasVoted,
	enableProAI,

	isMyTurnToAsk,
	amIAlive,
	playerAlias,

	startGame,
	submitVote,
	goToMenu,
	startNewGame,
} = useGame()

useGameMusic({ gameStatus, roundStatus, eliminatedPlayerId, winner, proAiActive })
</script>

<template>
	<div class="gameWrap">
		<div class="gameGrid">
			<div class="leftCol">
				<PlayersBar
					:gameStatus="gameStatus"
					:players="players"
					:roundNumber="roundNumber"
				/>

				<template v-if="gameStatus === 'waiting'">
					<WaitingHub
						:gameCode="gameCode"
						:players="players"
						:myUserId="myUserId"
						:isCreator="isCreator"
						:enableProAI="enableProAI"
						@update:enableProAI="enableProAI = $event"
						@startGame="startGame"
					/>
				</template>

				<template v-else-if="gameStatus === 'in_progress'">
					<PhaseQuestion
						v-if="roundStatus === 'en_attente_question'"
						:roundNumber="roundNumber"
						:isMyTurnToAsk="isMyTurnToAsk"
						:questionMasterId="questionMasterId"
						:playerAlias="playerAlias"
					/>

					<PhaseResponses
						v-else-if="roundStatus === 'en_attente_reponses'"
						:roundNumber="roundNumber"
						:question="question"
						:hasAnswered="hasAnswered"
						:answeredCount="answeredCount"
						:totalAlive="totalAlive"
					/>

					<PhaseVote
						v-else-if="roundStatus === 'en_attente_votes'"
						:roundNumber="roundNumber"
						:question="question"
						:answers="answers"
						:revoteCandidates="revoteCandidates"
						:hasVoted="hasVoted"
						:amIAlive="amIAlive"
						:myUserId="myUserId"
						:votedCount="votedCount"
						:totalAlive="totalAlive"
						@vote="submitVote"
					/>

					<PhaseElimination
						v-else-if="roundStatus === 'termine'"
						:players="players"
						:eliminatedPlayerId="eliminatedPlayerId"
					/>
				</template>

				<template v-else-if="gameStatus === 'finished'">
					<EndGame
						:winner="winner"
						:myRole="myRole"
						:isCreator="isCreator"
						:startNewGame="startNewGame"
						:goToMenu="goToMenu"
					/>
				</template>
			</div>
		</div>
	</div>
</template>

<style scoped lang="scss">
@use "@/assets/style/components/panelGame";
</style>