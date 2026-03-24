<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'

const { emitEvent } = useTerminal()
const { apiFetch } = useApi()
const { gameInfo } = useGameInfo()

function playerAliasForPanel(playerId: string, players: any[], myUserId: string | null) {
  const ALIASES = [
    'Renard', 'Loup', 'Corbeau', 'Serpent', 'Tigre',
    'Faucon', 'Ours', 'Vipère', 'Lynx', 'Puma',
    'Aigle', 'Requin', 'Panthère', 'Scorpion', 'Coyote',
    'Hibou', 'Jaguar', 'Raton', 'Baleine', 'Vautour'
  ]
  const p = players.find((p: any) => p.id === playerId)
  if (p?.isAI) return 'IA'
  if (playerId === myUserId) return 'Vous'
  const humanIds = players.filter((p: any) => !p.isAI).map((p: any) => p.id).sort()
  const idx = humanIds.indexOf(playerId)
  return idx >= 0 ? ALIASES[idx % ALIASES.length] : '???'
}

const showLogin = ref(true)
const email = ref('')
const password = ref('')
const pseudo = ref('')
const loading = ref(false)
const error = ref<string | null>(null)
const success = ref(false)
const token = ref<string | null>(null)
const connectedEmail = ref<string | null>(null)

const isLoggedIn = computed(() => !!token.value)

onMounted(() => {
	const savedToken = localStorage.getItem('jwt')
	if (savedToken) {
		token.value = savedToken
		try {
			const payload = JSON.parse(atob(savedToken.split('.')[1]))
			connectedEmail.value = payload?.username || null
		} catch {}
	} else {
		emitEvent({ message: 'Pas d\'utilisateur connecté.', type: 'info' })
	}
})

function logout() {
	localStorage.removeItem('jwt')
	localStorage.removeItem('userId')
	token.value = null
	connectedEmail.value = null
	email.value = ''
	password.value = ''
	error.value = null
	emitEvent({ message: 'Déconnexion réussie.', type: 'info' })
}

async function login() {
	loading.value = true
	error.value = null

	try {
		const res = await apiFetch('/api/login', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ username: email.value, password: password.value })
		})

		const data = await res.json()

		if (res.ok && data.token) {
			token.value = data.token
			localStorage.setItem('jwt', data.token)

			try {
				const payload = JSON.parse(atob(data.token.split('.')[1]))
				if (payload?.id) localStorage.setItem('userId', payload.id)
			} catch {}

			emitEvent({ message: 'Connexion réussie !', type: 'success' })
		} else {
			error.value = data.message || 'Identifiants invalides'
			emitEvent({ message: error.value, type: 'error' })
		}
	} catch {
		error.value = 'Erreur réseau'
		emitEvent({ message: error.value, type: 'error' })
	} finally {
		loading.value = false
	}
}

async function register() {
	loading.value = true
	error.value = null
	success.value = false

	try {
		const res = await apiFetch('/api/register', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ email: email.value, password: password.value, pseudo: pseudo.value })
		})

		const data = await res.json()

		if (res.ok && data.status === 'success') {
			success.value = true
			if (data.user?.id) localStorage.setItem('userId', data.user.id)
			emitEvent({ message: 'Inscription réussie !', type: 'success' })
		} else {
			error.value = data.message || "Erreur lors de l'inscription"
			emitEvent({ message: error.value, type: 'error' })
		}
	} catch {
		error.value = 'Erreur réseau'
		emitEvent({ message: error.value, type: 'error' })
	} finally {
		loading.value = false
	}
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
						<span>Code</span><strong>{{ gameInfo.code }}</strong>
					</div>
					<div v-if="gameInfo.round > 0" class="profil-game-info--row">
						<span>Round</span><strong>{{ gameInfo.round }}</strong>
					</div>
					<div class="profil-game-info--row">
						<span>Statut</span>
						<strong>{{ gameInfo.status === 'waiting' ? 'Attente' : gameInfo.status === 'in_progress' ? 'En cours' : 'Terminée' }}</strong>
					</div>
					<div v-if="gameInfo.players.length > 0" class="profil-game-info--players">
						<p class="profil-game-info--players-label">Joueurs :</p>
						<ul>
							<li
								v-for="p in gameInfo.players"
								:key="p.id"
								:class="{ 'player--dead': !p.isAlive }"
							>
								<span>{{ p.isAlive ? '●' : '✗' }}</span>
								{{ playerAliasForPanel(p.id, gameInfo.players, gameInfo.myUserId) }}
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

		<form v-if="showLogin" class="profil-form" @submit.prevent="login">
			<div class="profil-field">
				<label>Email ou pseudo :</label>
				<input v-model="email" type="text" placeholder="Votre email ou pseudo" required />
			</div>
			<div class="profil-field">
				<label>Mot de passe :</label>
				<input v-model="password" type="password" placeholder="Votre mot de passe" required />
			</div>
			<button class="gButton important profil-btn" :disabled="loading">
				<Icon name="pixelarticons:login" />
				{{ loading ? 'Connexion...' : 'Se connecter' }}
			</button>
			<div v-if="error" class="profil-error">{{ error }}</div>
			<button type="button" class="gButton profil-switch" @click="showLogin = false">
				<Icon name="pixelarticons:user-plus" />
				S'inscrire
			</button>
		</form>

		<form v-else class="profil-form" @submit.prevent="register">
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
			<div v-if="error" class="profil-error">{{ error }}</div>
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

.panelProfilNotConnected {
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 2rem;
	width: 100%;
	max-width: 350px;
	margin: 0 auto;
}

.profil-message {
	text-align: center;
	font-size: 1.1rem;
	margin-bottom: 1rem;
}

.profil-form {
	display: flex;
	flex-direction: column;
	gap: 1.2rem;
	width: 100%;
}

.profil-field {
	display: flex;
	flex-direction: column;
	gap: 0.3rem;
}

.profil-btn {
	width: 100%;
	margin-top: 0.5rem;
}

.profil-switch {
	width: 100%;
	margin-top: 0.5rem;
	background: none;
	border: 1px solid #444;
	color: #fff;
}

.profil-error {
	color: #ff4d4f;
	font-size: 0.95rem;
	text-align: center;
}

.profil-success {
	color: #4caf50;
	font-size: 0.95rem;
	text-align: center;
}

.profil-connected-email {
	font-size: 0.85rem;
	color: #aaa;
	text-align: center;
	word-break: break-all;
}

.profil-game-info {
	width: 100%;
	border-top: 1px solid #333;
	padding-top: 0.8rem;
	display: flex;
	flex-direction: column;
	gap: 0.3rem;
	font-size: 0.85rem;

	&--row {
		display: flex;
		justify-content: space-between;
		color: #aaa;
		strong { color: #fff; }
	}

	&--players {
		margin-top: 0.5rem;
		padding-top: 0.5rem;
		border-top: 1px solid #222;
	}

	&--players-label {
		color: #aaa;
		margin-bottom: 0.3rem;
	}

	ul {
		list-style: none;
		padding: 0;
		margin: 0;
		display: flex;
		flex-direction: column;
		gap: 0.25rem;
	}

	li { display: flex; align-items: center; gap: 0.3rem; }
	li.player--dead { opacity: 0.4; text-decoration: line-through; }
}

.tag-me { color: #aaa; font-size: 0.7rem; }
.tag-ai { background: #f44336; color: #fff; font-size: 0.65rem; padding: 0 0.25rem; }
</style>