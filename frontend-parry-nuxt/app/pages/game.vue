<script setup lang="ts">
import PlayersBar from '@/components/game/PlayersBar.vue'
import WaitingHub from '@/components/game/WaitingHub.vue'
import RoleReveal from '@/components/game/RoleReveal.vue'
import PhaseQuestion from '@/components/game/PhaseQuestion.vue'
import PhaseResponses from '@/components/game/PhaseResponses.vue'
import PhaseVote from '@/components/game/PhaseVote.vue'
import PhaseElimination from '@/components/game/PhaseElimination.vue'
import EndGame from '@/components/game/EndGame.vue'

import { useGame } from '@/composables/game/useGame'

const {
	gameCode,
	gameStatus,
	players,
	isSpectator,
	spectators,

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

	myUserId,
	isCreator,

	myRole,
	hasAnswered,
	hasVoted,
	enableProAI,
	showRoleReveal,

	isMyTurnToAsk,
	amIAlive,
	playerAlias,
	playerSpriteUrl,

	startGame,
	submitVote,
	goToMenu,
	startNewGame,
	quitGame,
} = useGame()
</script>

<template>
	<div class="gameWrap">
		<div class="gameGrid">
			<div class="leftCol">
				<PlayersBar
					:gameStatus="gameStatus"
					:players="players"
					:roundNumber="roundNumber"
					:spectators="spectators"
				/>

				<button v-if="gameStatus !== 'finished'" class="gButton quitGameButton" @click="quitGame">
					<Icon name="pixelarticons:close" />
					Quitter la partie
				</button>

				<div v-if="isSpectator && gameStatus !== 'waiting'" class="spectatorBanner">
					<Icon name="pixelarticons:eye" />
					Mode spectateur — vous rejoindrez la partie en tant que joueur au prochain lancement.
				</div>

				<template v-if="gameStatus === 'waiting'">
					<WaitingHub
						:gameCode="gameCode"
						:players="players"
						:spectators="spectators"
						:myUserId="myUserId"
						:isCreator="isCreator"
						:enableProAI="enableProAI"
						@update:enableProAI="enableProAI = $event"
						@startGame="startGame"
					/>
				</template>

				<template v-else-if="gameStatus === 'in_progress'">
					<RoleReveal v-if="showRoleReveal" :myRole="myRole" />

					<template v-else>
						<PhaseQuestion
							v-if="roundStatus === 'en_attente_question'"
							:roundNumber="roundNumber"
							:isMyTurnToAsk="isMyTurnToAsk"
							:questionMasterId="questionMasterId"
							:playerAlias="playerAlias"
							:questionSpriteUrl="questionMasterId ? playerSpriteUrl(questionMasterId, 'question') : undefined"
						/>

						<PhaseResponses
							v-else-if="roundStatus === 'en_attente_reponses'"
							:roundNumber="roundNumber"
							:question="question"
							:hasAnswered="hasAnswered"
							:answeredCount="answeredCount"
							:totalAlive="totalAlive"
							:playerAlias="myUserId ? playerAlias(myUserId) : undefined"
							:playerSpriteUrl="myUserId ? playerSpriteUrl(myUserId, 'response') : undefined"
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
							:eliminationSpriteUrl="eliminatedPlayerId ? playerSpriteUrl(eliminatedPlayerId, 'elimination') : undefined"
						/>
					</template>
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