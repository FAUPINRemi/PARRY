<script setup lang="ts">
interface Player {
	id: string
	nickname: string
	isAlive: boolean
	isAI: boolean
	avatarDataUrl?: string | null
}

const props = defineProps<{
	gameCode: string
	players: Player[]
	myUserId: string | null
	isCreator: boolean
	enableProAI: boolean
}>()

const emit = defineEmits<{
	(e: 'update:enableProAI', value: boolean): void
	(e: 'startGame'): void
}>()

function onToggle(e: Event) {
	const target = e.target as HTMLInputElement
	emit('update:enableProAI', target.checked)
}
</script>

<template>
	<h1 class="game-screen-title">Salon d'attente</h1>
	<p class="game-code-display">Code : <strong> {{ gameCode }} </strong></p>

	<div class="hub-players">
		<p class="hub-players--label">Joueurs connectés ( {{ players.length }} ) :</p>
		<ul class="hub-players--list">
			<li v-for="p in players" :key="p.id">
				<img
					v-if="p.avatarDataUrl"
					:src="p.avatarDataUrl"
					alt="Avatar"
					class="player-avatar"
				/>
				<Icon v-else name="pixelarticons:user" class="player-avatar-fallback" />
				 {{ p.nickname }} 
				<span v-if="p.id === myUserId" class="tag-me">(moi)</span>
			</li>
		</ul>
	</div>

	<div v-if="isCreator" class="hub-controls">
		<label class="pro-ai-toggle">
			<input type="checkbox" :checked="enableProAI" @change="onToggle" />
			Activer le rôle Pro-IA
			<span class="pro-ai-hint">(un joueur aide secrètement l'IA)</span>
		</label>

		<button
			class="gButton important"
			:disabled="players.length < 3"
			@click="$emit('startGame')"
		>
			<Icon name="pixelarticons:play" />
			Lancer la partie
		</button>

		<p v-if="players.length < 3" class="hub-hint">
			 {{ players.length }}  joueur(s) supplémentaire(s) requis
		</p>
	</div>

	<div v-else class="hub-waiting">
		<p>En attente du créateur pour démarrer…</p>
	</div>
</template>