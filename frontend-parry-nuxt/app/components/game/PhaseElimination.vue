<script setup lang="ts">
interface Player {
	id: string
	nickname: string
	isAlive: boolean
	isAI: boolean
}

const props = defineProps<{
	players: Player[]
	eliminatedPlayerId: string | null
}>()

function eliminatedPlayer() {
	if (!props.eliminatedPlayerId) return null
	return props.players.find(p => p.id === props.eliminatedPlayerId) ?? null
}
</script>

<template>
	<h1 class="game-screen-title">Élimination</h1>

	<div v-if="eliminatedPlayerId" class="elimination-box">
		<p class="elimination-box--name">
			 {{ eliminatedPlayer()?.nickname ?? 'Un joueur' }} 
			a été éliminé !
		</p>

		<p v-if="eliminatedPlayer()?.isAI" class="elimination-box--ai">
			C'était l'IA ! Les joueurs ont gagné !
		</p>
		<p v-else class="elimination-box--human">
			Ce n'était pas l'IA…
		</p>
	</div>

	<div v-else class="phase-box">
		<p>Calcul des votes…</p>
		<div class="loading-dots"><span></span><span></span><span></span></div>
	</div>
</template>