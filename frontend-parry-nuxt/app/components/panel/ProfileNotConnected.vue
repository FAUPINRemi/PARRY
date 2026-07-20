<script setup lang="ts">
import { ref } from 'vue'
import { useAuth } from '@/composables/auth/useAuth'

const { loading, error, success, login, register } = useAuth()

const showLogin = ref(true)

const emailOrPseudo = ref('')
const email = ref('')
const pseudo = ref('')
const loginPassword = ref('')
const registerPassword = ref('')

async function onLogin() {
	await login({ username: emailOrPseudo.value, password: loginPassword.value })
}

async function onRegister() {
	await register({ email: email.value, password: registerPassword.value, pseudo: pseudo.value })
	if (success.value) {
		emailOrPseudo.value = pseudo.value
		showLogin.value = true
	}
}

function switchToLogin() {
	emailOrPseudo.value = pseudo.value || emailOrPseudo.value
	showLogin.value = true
}
</script>

<template>
	<div class="panelProfilNotConnected">
		<p v-if="showLogin" class="profil-message">Vous n'êtes pas connecté.</p>

		<form v-if="showLogin" class="profil-form" @submit.prevent="onLogin">
			<div class="profil-field">
				<label>Email ou pseudo :</label>
				<input v-model="emailOrPseudo" type="text" placeholder="Votre email ou pseudo" required />
			</div>

			<div class="profil-field">
				<label>Mot de passe :</label>
				<input v-model="loginPassword" type="password" placeholder="Votre mot de passe" required />
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
				<input v-model="registerPassword" type="password" placeholder="Votre mot de passe" required />
			</div>

			<button class="gButton important profil-btn" :disabled="loading">
				<Icon name="pixelarticons:user-plus" />
				{{ loading ? 'Inscription...' : "S'inscrire" }}
			</button>

			<div v-if="success" class="profil-success">Inscription réussie !</div>
			<div v-if="error" class="profil-error">{{ error }}</div>

			<button type="button" class="gButton profil-switch" @click="switchToLogin">
				<Icon name="pixelarticons:login" />
				Se connecter
			</button>
		</form>
	</div>
</template>

<style lang="scss">
@use "@/assets/style/components/panelProfilNotConnected";
</style>
