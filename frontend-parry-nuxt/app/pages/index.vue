<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '@/composables/auth/useAuth'

const { emitEvent } = useTerminal()
const { apiFetch } = useApi()
const { isLoggedIn } = useAuth()
const router = useRouter()

const { unlocked, currentTrack, playMusic } = useGameAudio()

watch(unlocked, (isUnlocked) => {
	if (isUnlocked && currentTrack.value !== 'menu') {
		playMusic('menu', { loop: true, loopDip: true })
	}
}, { immediate: true })

onMounted(async () => {
	try {
		const res = await apiFetch('/api/game/active')
		if (res.ok) {
			const data = await res.json()
			if (data.success && data.code) {
				router.push({ path: '/game', query: { code: data.code } })
			}
		}
	} catch { /* pas de partie active */ }
})

const gameInfo = ref<{ code?: string; id?: string; isPrivate?: boolean; status?: string } | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)

const joinCode = ref('')
const joinError = ref<string | null>(null)

watch(joinCode, (value) => {
	const upper = value.toUpperCase()
	if (upper !== value) joinCode.value = upper
})

const LOGIN_REQUIRED_MESSAGE = 'Vous devez vous connecter pour jouer.'

async function createGame() {
	if (!isLoggedIn.value) {
		error.value = LOGIN_REQUIRED_MESSAGE
		emitEvent({ message: LOGIN_REQUIRED_MESSAGE, type: 'error' })
		return
	}

	loading.value = true
	error.value = null

	try {
		const res = await apiFetch('/api/game/create', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ isPrivate: true })
		})

		const data = await res.json()

		if (data.success) {
			gameInfo.value = data.game
			const code = data.game.code || data.game.id
			if (code) {
				emitEvent({ message: `Salon créé ! Code : ${code}`, type: 'success' })
				router.push({ path: '/game', query: { code } })
			}
		} else {
			error.value = data.error || 'Erreur inconnue'
			emitEvent({ message: error.value ?? 'Erreur', type: 'error' })
		}
	} catch {
		error.value = 'Erreur réseau'
		emitEvent({ message: error.value ?? 'Erreur', type: 'error' })
	} finally {
		loading.value = false
	}
}

async function joinGame() {
	if (!isLoggedIn.value) {
		joinError.value = LOGIN_REQUIRED_MESSAGE
		emitEvent({ message: LOGIN_REQUIRED_MESSAGE, type: 'error' })
		return
	}

	if (!joinCode.value) return
	router.push({ path: '/game', query: { code: joinCode.value } })
}
</script>

<template>
	<div class="homepageWrap">
		<div class="homepageGrid">
			<div class="leftCol">
				<pre class="asciiPre" aria-hidden="true">
ooooooooo.         .o.       ooooooooo.   ooooooooo.   oooooo   oooo
`888   `Y88.      .888.      `888   `Y88. `888   `Y88.  `888.   .8'
 888   .d88'     .8"888.      888   .d88'  888   .d88'   `888. .8'
 888ooo88P'     .8' `888.     888ooo88P'   888ooo88P'     `888.8'
 888           .88ooo8888.    888`88b.     888`88b.        `888'
 888          .8'     `888.   888  `88b.   888  `88b.       888
o888o        o88o     o8888o o888o  o888o o888o  o888o     o888o
</pre>

				<div v-if="!isLoggedIn" class="loginRequiredNotice" style="color: red; margin-bottom: 1em">
					Vous devez vous connecter pour jouer.
				</div>

				<div class="cardsRow">
					<div class="card">
						<h2>Créer un salon</h2>

						<button class="gButton important" :disabled="loading || !isLoggedIn" @click.prevent="createGame">
							<Icon name="pixelarticons:plus" />
							 {{loading ? 'Création...' : 'Créer un salon' }}
						</button>

						<div v-if="gameInfo" style="margin-top: 1em">
							<div><b>Salon créé !</b></div>
							<div v-if="gameInfo.code">Code : <b>{{ gameInfo.code }}</b></div>
							<div v-else>ID : <b>{{ gameInfo.id }}</b></div>
							<div>Status : {{ gameInfo.status }}</div>
						</div>

						<div v-if="error" style="color: red">{{ error }}</div>
					</div>

					<form class="card" @submit.prevent="joinGame">
						<h2>Rejoindre le salon</h2>

						<label>Code du salon:</label>

						<div class="joinRow">
							<input v-model="joinCode" type="text" placeholder="ABC123" required style="text-transform: uppercase" />
							<button class="gButton important" :disabled="!isLoggedIn">
								<Icon name="pixelarticons:search" /> Rejoindre
							</button>
						</div>

						<div v-if="joinError" style="color: red">{{ joinError }}</div>
					</form>
				</div>
			</div>
		</div>
	</div>
</template>

<style scoped lang="scss">
@use "@/assets/style/components/index";
</style>