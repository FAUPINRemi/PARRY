<script setup lang="ts">
import { ref } from 'vue'

interface Player {
	id: string
	nickname: string
	isAlive: boolean
	isAI: boolean
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

const { micEnabled, requestPermission, disableMic } = useMicrophone()
const micError = ref('')

const { ttsEnabled } = useTTS()

const isSecureContext = import.meta.client ? window.isSecureContext : true

async function onMicToggle(e: Event) {
	const checked = (e.target as HTMLInputElement).checked
	micError.value = ''

	if (checked) {
		if (!isSecureContext) {
			micError.value = 'Le micro nécessite HTTPS. Disponible une fois le SSL activé sur le serveur.'
			;(e.target as HTMLInputElement).checked = false
			return
		}
		const granted = await requestPermission()
		if (!granted) {
			micError.value = 'Permission refusée. Autorisez le micro dans les paramètres du navigateur.'
		}
	} else {
		disableMic()
	}
}
</script>

<template>
	<h1 class="game-screen-title">Salon d'attente</h1>
	<p class="game-code-display">Code : <strong> {{ gameCode }} </strong></p>

	<div class="hub-players">
		<p class="hub-players--label">Joueurs connectés ( {{ players.length }} ) :</p>
		<ul class="hub-players--list">
			<li v-for="p in players" :key="p.id">
				<Icon name="pixelarticons:user" />
				 {{ p.nickname }} 
				<span v-if="p.id === myUserId" class="tag-me">(moi)</span>
			</li>
		</ul>
	</div>

	<div class="hub-mic-section">
		<label class="mic-toggle">
			<input type="checkbox" :checked="micEnabled" @change="onMicToggle" />
			Répondre à l'oral
			<span class="mic-hint">(utiliser le micro pour vos réponses)</span>
		</label>
		<p v-if="micError" class="mic-error">{{ micError }}</p>

		<label class="tts-toggle">
			<input type="checkbox" v-model="ttsEnabled" />
			Lire les questions à voix haute
			<span class="tts-hint">(synthèse vocale des questions)</span>
		</label>
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