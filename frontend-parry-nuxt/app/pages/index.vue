<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'

const { emitEvent } = useTerminal()
const { apiFetch } = useApi()
const router = useRouter()

const gameInfo = ref<{ code?: string; id?: string; isPrivate?: boolean; status?: string } | null>(null)
const loading = ref(false)
const error = ref<string | null>(null)

const joinCode = ref('')
const joinLoading = ref(false)
const joinError = ref<string | null>(null)
const joinSuccess = ref(false)

async function createGame() {
	loading.value = true
	error.value = null

	try {
		const jwt = process.client ? localStorage.getItem('jwt') : null

		const res = await apiFetch('/api/game/create', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				...(jwt ? { Authorization: `Bearer ${jwt}` } : {})
			},
			body: JSON.stringify({ isPrivate: false })
		})

		const data = await res.json()

		if (data.success) {
			gameInfo.value = data.game
			emitEvent({ message: 'Salon créé !', type: 'success' })

			if (data.game && (data.game.code || data.game.id)) {
				router.push({ path: '/game', query: { code: data.game.code || data.game.id } })
			}
		} else {
			error.value = data.error || 'Erreur inconnue'
			emitEvent({ message: error.value, type: 'error' })
		}
	} catch {
		error.value = 'Erreur réseau'
		emitEvent({ message: error.value, type: 'error' })
	} finally {
		loading.value = false
	}
}

async function joinGame() {
	joinLoading.value = true
	joinError.value = null
	joinSuccess.value = false

	try {
		const userId = (process.client ? localStorage.getItem('userId') : null) || 'demo-user-id'
		const jwt = process.client ? localStorage.getItem('jwt') : null

		const res = await apiFetch(`/api/game/${joinCode.value}/join`, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				...(jwt ? { Authorization: `Bearer ${jwt}` } : {})
			},
			body: JSON.stringify({ userId })
		})

		const data = await res.json()

		if (res.ok && data.success) {
			joinSuccess.value = true
			emitEvent({ message: 'Salon rejoint !', type: 'success' })

			if (joinCode.value) {
				router.push({ path: '/game', query: { code: joinCode.value } })
			}
		} else {
			joinError.value = data.error || 'Erreur lors de la jonction'
			emitEvent({ message: joinError.value, type: 'error' })
		}
	} catch {
		joinError.value = 'Erreur réseau'
		emitEvent({ message: joinError.value, type: 'error' })
	} finally {
		joinLoading.value = false
	}
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

				<div class="cardsRow">
					<div class="card">
						<h2>Créer un salon</h2>

						<button class="gButton important" :disabled="loading" @click.prevent="createGame">
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
							<input v-model="joinCode" type="text" placeholder="ABC123" required />
							<button class="gButton important" :disabled="joinLoading">
								<Icon name="pixelarticons:search" />
								 {{joinLoading ? 'Recherche...' : 'Rejoindre' }} 
							</button>
						</div>

						<div v-if="joinSuccess" style="color: green">Salon rejoint !</div>
						<div v-if="joinError" style="color: red">{{ joinError }}</div>
					</form>
				</div>
			</div>

			<div class="rightCol"></div>
		</div>
	</div>
</template>

<style scoped lang="scss">
/* Optionnel: si tu veux aussi rapprocher du bord global, baisse ce padding */
.homepageWrap {
	position: relative;
	min-height: 100vh;
	width: 100%;
	max-width: 1100px;
	margin: 0 auto;
	padding: 1.25rem; /* tu peux mettre 0.75rem si tu veux + près du cadre */
	box-sizing: border-box;
}

.homepageGrid {
	display: grid;
	gap: 1rem;
	grid-template-columns: 1fr;
	align-items: start;
}

@media (min-width: 1024px) {
	.homepageGrid {
		grid-template-columns: 1fr 1fr;
		align-items: stretch;
	}
}

.leftCol {
	display: flex;
	flex-direction: column;
	justify-content: flex-start;
	min-height: 260px;
	min-width: 0;

	/* MODIF: on enlève le padding qui décale tout (ASCII + cards) du bord du cadre */
	padding-left: 0;
	padding-top: 0;
}

.asciiPre {
	font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace !important;
	font-style: normal !important;
	font-weight: 400 !important;
	letter-spacing: 0 !important;
	transform: none !important;
	color: #fff !important;
	line-height: 1.05;
	white-space: pre;
	margin: 0;
	padding: 0;
	text-align: left;
	font-size: clamp(14px, 1.35vw, 22px);
	-webkit-font-smoothing: none;
	text-rendering: optimizeSpeed;

	margin-top: 5rem;
	margin-bottom: 5rem;
}



.cardsRow {
	display: grid;
	grid-template-columns: 1fr;
	gap: 1rem;
}

@media (min-width: 900px) {
	.cardsRow {
		grid-template-columns: 1fr 1fr;
	}
}


.joinRow {
	display: flex;
	flex-direction: row;
	align-items: center;
	gap: 0.6rem;
	width: 100%;
}

@media (max-width: 520px) {
	.joinRow {
		flex-direction: column;
		align-items: stretch;
		gap: 0.5rem;
	}
}

.joinRow input {
	flex: 1 1 auto;
	width: 100%;
	min-width: 0;
	padding: 0.55rem 0.8rem;
	border-radius: 6px;
	border: 1px solid rgba(255, 255, 255, 0.15);
	background: rgba(255, 255, 255, 0.06);
	color: #fff;
	box-sizing: border-box;
}

.rightCol {
	min-height: 1px;
}
</style>