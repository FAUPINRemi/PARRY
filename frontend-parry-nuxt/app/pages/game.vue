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

				<Transition name="phase-flicker" mode="out-in">
					<div v-if="gameStatus === 'waiting'" key="waiting" class="phaseSlot">
						<WaitingHub
							:gameCode="gameCode"
							:players="players"
							:myUserId="myUserId"
							:isCreator="isCreator"
							:enableProAI="enableProAI"
							@update:enableProAI="enableProAI = $event"
							@startGame="startGame"
						/>
					</div>

					<div
						v-else-if="gameStatus === 'in_progress' && roundStatus === 'en_attente_question'"
						key="question"
						class="phaseSlot"
					>
						<PhaseQuestion
							:roundNumber="roundNumber"
							:isMyTurnToAsk="isMyTurnToAsk"
							:questionMasterId="questionMasterId"
							:playerAlias="playerAlias"
						/>
					</div>

					<div
						v-else-if="gameStatus === 'in_progress' && roundStatus === 'en_attente_reponses'"
						key="responses"
						class="phaseSlot"
					>
						<PhaseResponses
							:roundNumber="roundNumber"
							:question="question"
							:hasAnswered="hasAnswered"
							:answeredCount="answeredCount"
							:totalAlive="totalAlive"
						/>
					</div>

					<div
						v-else-if="gameStatus === 'in_progress' && roundStatus === 'en_attente_votes'"
						key="vote"
						class="phaseSlot"
					>
						<PhaseVote
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
					</div>

					<div
						v-else-if="gameStatus === 'in_progress' && roundStatus === 'termine'"
						key="elimination"
						class="phaseSlot"
					>
						<PhaseElimination
							:players="players"
							:eliminatedPlayerId="eliminatedPlayerId"
						/>
					</div>

					<div v-else-if="gameStatus === 'finished'" key="finished" class="phaseSlot">
						<EndGame
							:winner="winner"
							:myRole="myRole"
							:isCreator="isCreator"
							:startNewGame="startNewGame"
							:goToMenu="goToMenu"
						/>
					</div>
				</Transition>
			</div>
		</div>
	</div>
</template>

<style scoped lang="scss">
@use "@/assets/style/components/panelGame";
</style>