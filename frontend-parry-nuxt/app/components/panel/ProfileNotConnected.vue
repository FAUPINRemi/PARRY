<script setup lang="ts">
import { ref } from 'vue'
import { useAuth } from '@/composables/auth/useAuth'
import { playerAlias } from '@/components/game/utils'
import type { Player } from '@/components/game/types'

const { gameInfo } = useGameInfo()
const { isLoggedIn, connectedEmail, loading, error, success, login, register, logout } = useAuth()

const showLogin = ref(true)

const emailOrPseudo = ref('')
const email = ref('')
const pseudo = ref('')
const password = ref('')

function aliasForPanel(playerId: string, players: Player[], myUserId: string | null) {
	return playerAlias(playerId, players, myUserId)
}

async function onLogin() {
	await login({ username: emailOrPseudo.value, password: password.value })
}

async function onRegister() {
	await register({ email: email.value, password: password.value, pseudo: pseudo.value })
}
</script>

<template>
	<div class="panelProfilNotConnected">
		<template v-if="isLoggedIn">
			<p v-if="connectedEmail" class="profil-connected-email">{{ connectedEmail }}</p>

			<button class="gButton important profil-btn" @click="logout">
				<Icon name="pixelarticons:logout" />
				Se déconnecter
			</button>

			<template v-if="gameInfo">
				<div class="profil-game-info">
					<div class="profil-game-info--row">
						<span>Code</span><strong> {{ gameInfo.code }} </strong>
					</div>

					<div v-if="gameInfo.round > 0" class="profil-game-info--row">
						<span>Round</span><strong> {{ gameInfo.round }} </strong>
					</div>

					<div class="profil-game-info--row">
						<span>Statut</span>
						<strong>
							{{ 
								gameInfo.status === 'waiting'
									? 'Attente'
									: gameInfo.status === 'in_progress'
										? 'En cours'
										: 'Terminée' 
							}}
						</strong>
					</div>

					<div v-if="gameInfo.players.length > 0" class="profil-game-info--players">
						<p class="profil-game-info--players-label">Joueurs :</p>
						<ul>
									<li
										v-for="p in gameInfo.players"
										:key="p.id"
										:class="{ 'player--dead': !p.isAlive }"
									>
										<span> {{ p.isAlive ? '●' : '✗' }} </span>
										{{ aliasForPanel(p.id, gameInfo.players, gameInfo.myUserId) }}
										<span v-if="p.id === gameInfo.myUserId" class="tag-me">(moi)</span>
										<span v-if="p.isAI" class="tag-ai">IA</span>
									</li>
						</ul>
					</div>
				</div>
			</template>
		</template>

		<template v-else>
			<p v-if="showLogin" class="profil-message">Vous n'êtes pas connecté.</p>

			<form v-if="showLogin" class="profil-form" @submit.prevent="onLogin">
				<div class="profil-field">
					<label>Email ou pseudo :</label>
					<input v-model="emailOrPseudo" type="text" placeholder="Votre email ou pseudo" required />
				</div>

				<div class="profil-field">
					<label>Mot de passe :</label>
					<input v-model="password" type="password" placeholder="Votre mot de passe" required />
				</div>

				<button class="gButton important profil-btn" :disabled="loading">
					<Icon name="pixelarticons:login" />
					{{ loading ? 'Connexion...' : 'Se connecter' }}
				</button>

				<div v-if="error" class="profil-error"> {{ error }} </div>

				<button type="button" class="gButton profil-switch" @click="showLogin = false">
					<Icon name="pixelarticons:user-plus" />
					S'inscrire
				</button>
			</form>

			<form v-else class="profil-form" @submit.prevent="onRegister">
				<div class="profil-field">
					<label>Email :</label>
					<input v-model="email" type="email" placeholder="Votre email" required />
				</div>

				<div class="profil-field">
					<label>Pseudo :</label>
					<input v-model="pseudo" type="text" placeholder="Votre pseudo" required />
				</div>

				<div class="profil-field">
					<label>Mot de passe :</label>
					<input v-model="password" type="password" placeholder="Votre mot de passe" required />
				</div>

				<button class="gButton important profil-btn" :disabled="loading">
					<Icon name="pixelarticons:user-plus" />
					 {{ loading ? 'Inscription...' : "S'inscrire" }}
				</button>

				<div v-if="success" class="profil-success">Inscription réussie !</div>
				<div v-if="error" class="profil-error"> {{ error }} </div>

				<button type="button" class="gButton profil-switch" @click="showLogin = true">
					<Icon name="pixelarticons:login" />
					Se connecter
				</button>
			</form>
		</template>
	</div>
</template>

<style lang="scss">
@use "@/assets/style/components/panelProfilNotConnected";
</style>