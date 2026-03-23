<script setup lang="ts">
import { ref, onMounted } from 'vue'

const { emitEvent } = useTerminal()
const { apiFetch } = useApi()

const showLogin = ref(true)
const email = ref('')
const password = ref('')
const pseudo = ref('')
const loading = ref(false)
const error = ref<string | null>(null)
const success = ref(false)
const token = ref<string | null>(null)

onMounted(() => {
	emitEvent({ message: 'Pas d’utilisateur connecté.', type: 'info' })
})

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
				<NuxtIcon name="pixelarticons:login" />
				{{ loading ? 'Connexion...' : 'Se connecter' }}
			</button>
			<div v-if="token" class="profil-success">Connecté !</div>
			<div v-if="error" class="profil-error">{{ error }}</div>
			<button type="button" class="gButton profil-switch" @click="showLogin = false">
				<NuxtIcon name="pixelarticons:user-plus" />
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
				<NuxtIcon name="pixelarticons:user-plus" />
				{{ loading ? 'Inscription...' : "S'inscrire" }}
			</button>
			<div v-if="success" class="profil-success">Inscription réussie !</div>
			<div v-if="error" class="profil-error">{{ error }}</div>
			<button type="button" class="gButton profil-switch" @click="showLogin = true">
				<NuxtIcon name="pixelarticons:login" />
				Se connecter
			</button>
		</form>
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
</style>