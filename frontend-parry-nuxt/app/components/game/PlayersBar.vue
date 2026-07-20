<script setup lang="ts">
import { computed } from 'vue'

type GameStatus = 'waiting' | 'in_progress' | 'finished'

interface Player {
	id: string
	nickname: string
	isAlive: boolean
	isAI: boolean
}

interface Spectator {
	id: string
	nickname: string
}

const props = defineProps<{
	gameStatus: GameStatus
	players: Player[]
	roundNumber: number
	spectators?: Spectator[]
}>()

const alivePlayers = computed(() => props.players.filter(p => p.isAlive))
const deadPlayers = computed(() => props.players.filter(p => !p.isAlive))
</script>

<template>
	<div v-if="gameStatus !== 'waiting'" class="players-bar">
		<span class="players-bar--alive">
			<Icon name="pixelarticons:user" />
			 {{ alivePlayers.length }}  en vie
		</span>

		<span class="players-bar--dead">
			<Icon name="pixelarticons:close" />
			 {{ deadPlayers.length }}  éliminé{{ deadPlayers.length > 1 ? 's' : '' }} 
		</span>

		<span class="players-bar--round" v-if="roundNumber > 0">
			Round  {{ roundNumber }}
		</span>

		<span v-if="spectators && spectators.length" class="players-bar--spectators">
			<Icon name="pixelarticons:eye" />
			 {{ spectators.length }}  spectateur{{ spectators.length > 1 ? 's' : '' }}
		</span>
	</div>
</template>