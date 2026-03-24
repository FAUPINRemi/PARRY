<script setup lang="ts">
type Role = 'player' | 'proai'
type Winner = 'PLAYERS_WIN' | 'AI_WINS' | string | null

const props = defineProps<{
	winner: Winner
	myRole: Role
	isCreator: boolean
	startNewGame: () => Promise<void> | void
	goToMenu: () => Promise<void> | void
}>()

function titleText() {
	if (props.winner === 'PLAYERS_WIN') return 'Victoire des joueurs !'
	if (props.winner === 'AI_WINS') return "Victoire de l'IA !"
	return 'Fin de partie'
}

function subtitleText() {
	if (props.myRole === 'proai') {
		return "Rôle : Pro‑IA (vous aidiez secrètement l'IA)."
	}
	return 'Rôle : Joueur.'
}

function winnerDetails() {
	if (props.winner === 'PLAYERS_WIN') {
		return "Les joueurs ont réussi à éliminer l'IA."
	}
	if (props.winner === 'AI_WINS') {
		return "L'IA a réussi à survivre jusqu'à la fin."
	}
	return "La partie est terminée."
}
</script>

<template>
	<div class="endGame">
		<h1 class="game-screen-title"> titleText() </h1>

		<p class="endGame-subtitle"> subtitleText() </p>
		<p class="endGame-details"> winnerDetails() </p>

		<div class="endGame-actions">
			<button class="gButton" @click="goToMenu">
				<Icon name="pixelarticons:arrow-left" />
				Retour au menu
			</button>

			<button
				v-if="isCreator"
				class="gButton important"
				@click="startNewGame"
			>
				<Icon name="pixelarticons:reload" />
				Relancer une partie
			</button>
		</div>
	</div>
</template>

<style scoped lang="scss">
.endGame {
	display: flex;
	flex-direction: column;
	gap: 10px;
}

.endGame-subtitle {
	opacity: 0.85;
}

.endGame-details {
	opacity: 0.95;
}

.endGame-actions {
	display: flex;
	gap: 10px;
	margin-top: 10px;
	flex-wrap: wrap;
}
</style>