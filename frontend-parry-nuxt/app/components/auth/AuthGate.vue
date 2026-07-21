<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import ProfileNotConnected from '@/components/panel/ProfileNotConnected.vue'
import { useAuth } from '@/composables/auth/useAuth'

const { isLoggedIn, guestMode, initFromStorage, continueAsGuest } = useAuth()

// Tant qu'on n'a pas relu le localStorage côté client, on ne sait pas encore
// si l'utilisateur est déjà connecté / a déjà choisi le mode invité.
const ready = ref(false)

onMounted(() => {
	initFromStorage()
	ready.value = true
})

const visible = computed(() => ready.value && !isLoggedIn.value && !guestMode.value)
</script>

<template>
	<div v-if="visible" class="authGateOverlay">
		<div class="authGate">
			<Icon name="pixelarticons:user" class="authGate-icon" />
			<h2 class="authGate-title">Connexion requise</h2>
			<p class="authGate-text">
				Connecte-toi ou crée un compte pour retrouver ton profil et ton historique de parties.
			</p>

			<ProfileNotConnected />

			<div class="authGate-guest">
				<button class="gButton" @click="continueAsGuest">
					<Icon name="pixelarticons:close-box" />
					Continuer sans compte
				</button>
			</div>
		</div>
	</div>
</template>

<style scoped lang="scss">
@use "@/assets/style/components/authGate";
</style>
