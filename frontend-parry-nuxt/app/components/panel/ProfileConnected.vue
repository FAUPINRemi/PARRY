<script setup lang="ts">
import { playerAlias } from '@/components/game/utils'
import type { Player } from '@/components/game/types'
import { useAuth } from '@/composables/auth/useAuth'

const { gameInfo } = useGameInfo()
const { connectedPseudo, logout } = useAuth()

function aliasForPanel(playerId: string, players: Player[], myUserId: string | null) {
	return playerAlias(playerId, players, myUserId)
}

const statusLabel = (status: string) =>
	status === 'waiting' ? 'Attente' : status === 'in_progress' ? 'En cours' : 'Terminée'
</script>

<template>
	<div class="panelProfil--connected">
		<p v-if="connectedPseudo" class="profil-connected-email">{{ connectedPseudo }}</p>

		<button class="gButton important profil-btn" @click="logout">
			<Icon name="pixelarticons:logout" />
			Se déconnecter
		</button>

		<div v-if="gameInfo" class="profil-game-info">
			<div class="profil-game-info--row">
				<span>Code</span><strong>{{ gameInfo.code }}</strong>
			</div>

			<div v-if="gameInfo.round > 0" class="profil-game-info--row">
				<span>Round</span><strong>{{ gameInfo.round }}</strong>
			</div>

			<div class="profil-game-info--row">
				<span>Statut</span><strong>{{ statusLabel(gameInfo.status) }}</strong>
			</div>

			<div v-if="gameInfo.players.length > 0" class="profil-game-info--players">
				<p class="profil-game-info--players-label">Joueurs :</p>
				<ul>
					<li
						v-for="p in gameInfo.players"
						:key="p.id"
						:class="{ 'player--dead': !p.isAlive }"
					>
										<span>{{ p.isAlive ? '●' : '○' }}</span>
						{{ aliasForPanel(p.id, gameInfo.players, gameInfo.myUserId) }}
						<span v-if="p.id === gameInfo.myUserId" class="tag-me">(moi)</span>
						<span v-if="p.isAI" class="tag-ai">IA</span>
					</li>
				</ul>
			</div>
		</div>
	</div>
</template>

<style lang="scss">
@use "@/assets/style/components/panelProfilNotConnected";
</style>
