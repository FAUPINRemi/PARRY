<script setup lang="ts">
import { ref, watch } from 'vue'
import ProfileConnected from './ProfileConnected.vue'
import ProfileNotConnected from './ProfileNotConnected.vue'
import { useAuth } from '@/composables/auth/useAuth'
import { useIsMobile } from '@/composables/useIsMobile'

const { emitEvent } = useTerminal()
const { isLoggedIn } = useAuth()
const { isMobile } = useIsMobile()

const isHidden = ref(false)

// Une fois connecté sur mobile, le panel se replie derrière un petit bouton
// (l'écran est trop petit pour le garder ouvert en permanence) ; en le
// rouvrant, il s'affiche en pop-up plutôt qu'inline (voir .profil--popup).
watch([isLoggedIn, isMobile], ([loggedIn, mobile]) => {
	if (loggedIn && mobile) isHidden.value = true
}, { immediate: true })

function openPanel() {
	isHidden.value = false
	emitEvent({ message: 'Panneau utilisateur ouvert', type: 'info' })
}

function closePanel() {
	isHidden.value = true
	emitEvent({ message: 'Panneau utilisateur fermé', type: 'info' })
}
</script>

<template>
	<div
		v-if="isMobile && isLoggedIn && !isHidden"
		class="profilBackdrop"
		@click="closePanel"
	></div>

	<Panel
		v-if="!isHidden"
		label="Utilisateur"
		icon="pixelarticons:user"
		class="profil"
		:class="{ 'profil--popup': isMobile && isLoggedIn }"
	>
		<button @click="closePanel" class="gButton profilClose transparentBackground iconOnly">
			<Icon name="pixelarticons:close-box" />
			Close
		</button>

		<ProfileConnected v-if="isLoggedIn" />
		<ProfileNotConnected v-else />
	</Panel>

	<button v-else @click="openPanel" class="gButton profilOpen">
		<Icon name="pixelarticons:user" />
		Profil
	</button>
</template>

<style lang="scss">
@use "@/assets/style/components/panelProfil";
</style>
