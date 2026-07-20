<script setup lang="ts">
import { playerAlias } from '@/components/game/utils'
import type { Player } from '@/components/game/types'
import { useAuth } from '@/composables/auth/useAuth'

const { gameInfo } = useGameInfo()
const { connectedPseudo, connectedAvatarDataUrl, logout } = useAuth()

function aliasForPanel(playerId: string, players: Player[], myUserId: string | null) {
	return playerAlias(playerId, players, myUserId)
}

const statusLabel = (status: string) =>
	status === 'waiting' ? 'Attente' : status === 'in_progress' ? 'En cours' : 'Terminée'

const { playClick, muted, musicVolume, setMuted, setMusicVolume, setSfxVolume } = useGameAudio()

function onLogout() {
	playClick()
	logout()
}

function onToggleMuted(e: Event) {
	setMuted((e.target as HTMLInputElement).checked)
}

function onVolumeInput(e: Event) {
	const value = Number((e.target as HTMLInputElement).value)
	setMusicVolume(value)
	setSfxVolume(value)
}
</script>

<template>
	<div class="panelProfil--connected">
		<div v-if="connectedPseudo" class="profil-connected-user">
			<img
				v-if="connectedAvatarDataUrl"
				:src="connectedAvatarDataUrl"
				alt="Mon avatar"
				class="player-avatar"
			/>
			<Icon v-else name="pixelarticons:user" class="player-avatar-fallback" />
			<p class="profil-connected-email">{{ connectedPseudo }}</p>
		</div>

		<div class="profil-audio">
			<label class="audio-toggle">
				<input type="checkbox" :checked="muted" @change="onToggleMuted" />
				<Icon :name="muted ? 'pixelarticons:volume-x' : 'pixelarticons:volume'" />
				Couper le son
			</label>
			<input
				v-if="!muted"
				class="audio-volume"
				type="range"
				min="0"
				max="1"
				step="0.05"
				:value="musicVolume"
				@input="onVolumeInput"
			/>
		</div>

		<button class="gButton important profil-btn" @click="onLogout">
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
					</li>
				</ul>
			</div>
		</div>
	</div>
</template>

<style lang="scss">
@use "@/assets/style/components/panelProfilNotConnected";
</style>
