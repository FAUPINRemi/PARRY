<script setup lang="ts">
import { ref, onMounted, watch, computed } from 'vue'
import { useRoute } from 'vue-router'

const { emitEvent } = useTerminal()
const { apiFetch } = useApi()
const route = useRoute()

type Step = 'question' | 'reponse' | 'vote' | 'result'


const step = ref<Step>('question')
const question = ref('')
const questionLoading = ref(false)
const questionError = ref<string | null>(null)

// Détermine si c'est l'IA ou un joueur qui doit poser la question
const questionMasterId = ref<string | null>(null)
const isMyTurnToAsk = ref(false)

const response = ref('')
const responseLoading = ref(false)
const responseError = ref<string | null>(null)

const voteTarget = ref('')
const voteLoading = ref(false)
const voteError = ref<string | null>(null)

const result = ref<any>(null)

const players = ref<any[]>([])
const myUserId = ref<string | null>(null)
const gameCode = ref<string>('')

// stocker l'ID du round courant
const roundId = ref<string | null>(null)

// Ajoute la logique pour détecter le créateur et lancer la partie
const isCreator = ref(false)
const gameStarted = ref(false)
const gameStatus = ref<'waiting' | 'starting' | 'started'>('waiting')
const startCountdown = ref(3)

// Historique du déroulé de la partie
const gameHistory = ref<any[]>([])

const questionTimer = ref(30)
const responseTimer = ref(30)
let questionInterval: any = null
let responseInterval: any = null

async function fetchPlayersAndCreator() {
  await fetchPlayers()
  // On suppose que le premier joueur est le créateur (à adapter selon backend)
  if (players.value.length > 0 && myUserId.value) {
    isCreator.value = players.value[0].id === myUserId.value
  }
}

async function startGame() {
  if (!isCreator.value) return
  gameStatus.value = 'starting'
  startCountdown.value = 3
  // Timer de 3 secondes avant le début
  const interval = setInterval(() => {
    startCountdown.value--
    if (startCountdown.value <= 0) {
      clearInterval(interval)
      gameStatus.value = 'started'
      // Ici, tu pourrais appeler une API pour notifier le backend
      step.value = 'question'
    }
  }, 1000)
}

onMounted(() => {
	myUserId.value = localStorage.getItem('userId')
	gameCode.value = (route.query.code as string) || ''
	fetchPlayersAndCreator()
})

// Surveille le step pour automatiser la génération de question
watch(step, async (newStep) => {
	if (newStep === 'question') {
		// Choix aléatoire du question master (IA ou joueur)
		await fetchPlayers()
		let allIds = players.value.map(p => p.id)
		// Ajoute l'IA comme possible question master (id: 'AI')
		allIds.push('AI')
		const randomId = allIds[Math.floor(Math.random() * allIds.length)]
		questionMasterId.value = randomId
		isMyTurnToAsk.value = (randomId === myUserId.value)
		if (randomId === 'AI') {
			await fetchQuestion()
		} else if (isMyTurnToAsk.value) {
			// Affiche un champ pour que le joueur pose la question (à faire dans le template)
			question.value = ''
		} else {
			questionLoading.value = true
			questionError.value = null
			// Attend que le question master pose la question (polling simplifié)
			let tries = 0
			while (!question.value && tries < 30 && step.value === 'question') {
				await new Promise(r => setTimeout(r, 1000))
				await fetchPlayers()
				// Ici, il faudrait idéalement une API pour récupérer la question posée
				// Pour l'instant, on suppose qu'elle sera mise à jour côté backend
				tries++
			}
			questionLoading.value = false
		}
	}
})

// Charger la liste des joueurs de la partie (anonymisée)
async function fetchPlayers() {
	const jwt = process.client ? localStorage.getItem('jwt') : null

	try {
		const res = await apiFetch(`/api/game/${gameCode.value}/status`, {
			method: 'GET',
			headers: {
				'Content-Type': 'application/json',
				...(jwt ? { Authorization: `Bearer ${jwt}` } : {})
			}
		})

		const data = await res.json()

		if (res.ok && data?.game?.players) {
			players.value = data.game.players
			roundId.value = data.game.currentRoundId || null
		} else {
			players.value = []
			roundId.value = null
		}
	} catch {
		players.value = []
		roundId.value = null
	}
}

// Générer une question via l'IA (Gemini) + modération
async function fetchQuestion() {
	questionLoading.value = true
	questionError.value = null

	try {
		const res = await apiFetch('/api/ai/question', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify({ context: '' })
		})

		const data = await res.json()

		if (res.ok && data?.question) {
			// Moderation IA temporairement désactivée
			// const modRes = await apiFetch('/api/moderation', {
			//     method: 'POST',
			//     headers: { 'Content-Type': 'application/json' },
			//     body: JSON.stringify({ text: data.question })
			// })
			// const modData = await modRes.json()
			// if (modRes.ok && modData?.moderated === true) {
			//     question.value = data.question
			//     step.value = 'reponse'
			// } else {
			//     questionError.value = modData?.error || 'Question refusée par la modération IA.'
			// }
			question.value = data.question
			step.value = 'reponse'
		} else {
			questionError.value = data?.error || 'Erreur lors de la génération de la question'
		}
	} catch {
		questionError.value = 'Erreur réseau'
	} finally {
		questionLoading.value = false
	}
}

function goToResponse() {
	if (question.value) step.value = 'reponse'
}

// Soumettre la réponse du joueur
async function submitResponse() {
	responseLoading.value = true
	responseError.value = null

	try {
		let finalResponse = response.value

		if (!finalResponse) {
			const aiRes = await apiFetch('/api/ai/answer', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ question: question.value, context: '' })
			})

			const aiData = await aiRes.json()

			if (aiRes.ok && aiData?.answer) {
				finalResponse = aiData.answer
			} else {
				responseError.value = aiData?.error || 'Erreur IA lors de la génération de la réponse'
				return
			}
		}

		// Moderation IA temporairement désactivée
		// const modRes = await apiFetch('/api/moderation', {
		//     method: 'POST',
		//     headers: { 'Content-Type': 'application/json' },
		//     body: JSON.stringify({ text: finalResponse })
		// })
		// const modData = await modRes.json()
		// if (!(modRes.ok && modData?.moderated === true)) {
		//     responseError.value = modData?.error || 'Réponse refusée par la modération IA.'
		//     return
		// }

		const jwt = process.client ? localStorage.getItem('jwt') : null
		const userId = process.client ? localStorage.getItem('userId') : null

		if (!roundId.value) {
			responseError.value = 'Aucun round actif.'
			return
		}

		const res = await apiFetch(`/api/round/${roundId.value}/response`, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				...(jwt ? { Authorization: `Bearer ${jwt}` } : {})
			},
			body: JSON.stringify({ userId, response: finalResponse })
		})

		const data = await res.json()

		if (res.ok && data?.success) {
			step.value = 'vote'
			await fetchPlayers()
		} else {
			responseError.value = data?.error || "Erreur lors de l'envoi de la réponse"
		}
	} catch {
		responseError.value = 'Erreur réseau'
	} finally {
		responseLoading.value = false
	}
}

// Soumettre le vote du joueur
async function submitVote() {
	voteLoading.value = true
	voteError.value = null

	try {
		const jwt = process.client ? localStorage.getItem('jwt') : null
		const userId = process.client ? localStorage.getItem('userId') : null

		if (!roundId.value) {
			voteError.value = 'Aucun round actif.'
			return
		}

		const res = await apiFetch(`/api/round/${roundId.value}/vote`, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				...(jwt ? { Authorization: `Bearer ${jwt}` } : {})
			},
			body: JSON.stringify({ voterId: userId, targetPlayerId: voteTarget.value })
		})

		const data = await res.json()

		if (res.ok && data?.success) {
			result.value = data.result
			step.value = 'result'
		} else {
			voteError.value = data?.error || 'Erreur lors du vote'
		}
	} catch {
		voteError.value = 'Erreur réseau'
	} finally {
		voteLoading.value = false
	}
}

function nextRound() {
	question.value = ''
	response.value = ''
	voteTarget.value = ''
	result.value = null
	step.value = 'question'
	fetchPlayers()
}

function getPlayerNumberById(id: string) {
	const idx = players.value.findIndex((p: any) => p.id === id)
	return idx >= 0 ? idx + 1 : '?'
}

// Ajoute les événements au fil du jeu
watch(step, (newStep, oldStep) => {
  if (newStep === 'reponse' && question.value) {
    addHistoryItem({ type: 'question', round: gameHistory.value.length + 1, text: question.value, author: questionMasterId.value === 'AI' ? 'IA' : 'Joueur ' + getPlayerNumberById(questionMasterId.value) })
  }
  if (newStep === 'result' && result.value) {
    if (result.value.votes) {
      result.value.votes.forEach((vote: any) => {
        addHistoryItem({ type: 'vote', text: `Joueur ${getPlayerNumberById(vote.target)} a reçu ${vote.count} vote(s)` })
      })
    }
    if (result.value.eliminated) {
      addHistoryItem({ type: 'vote', text: `Joueur ${getPlayerNumberById(result.value.eliminated)} éliminé` })
    }
  }
})

function addHistoryItem(item: any) {
  gameHistory.value.push(item)
}

// Contrôle dynamique du terminal
const canRespond = computed(() => step.value === 'reponse' && !responseLoading && myUserId.value)
const canVote = computed(() => step.value === 'vote' && !voteLoading && myUserId.value)
</script>



<template>
	<div class="homepageWrap">
		<div class="homepageGrid">
			<div class="leftCol">
				<h1 class="homepage--title">Partie en cours</h1>

				<div v-if="step === 'question'">
					<h2>Étape 1 : Question</h2>
					<div v-if="questionLoading">Chargement de la question...</div>
					<div v-if="questionError" style="color: red">{{ questionError }}</div>
					<template v-if="question">
						<div>
							Question : <b>{{ question }}</b>
							<button @click="goToResponse">Répondre</button>
						</div>
					</template>
					<template v-else>
						<div v-if="isMyTurnToAsk">
							<input v-model="question" type="text" placeholder="Écris ta question..." class="terminal-input" @keyup.enter="submitPlayerQuestion" />
							<button class="gButton important" @click="submitPlayerQuestion" :disabled="!question">Envoyer la question</button>
						</div>
					</template>
				</div>

				<div v-else-if="step === 'result'">
					<h2>Résultat du round</h2>
					<div v-if="result">
						<div v-if="result.eliminated">
							Joueur éliminé : Joueur {{ getPlayerNumberById(result.eliminated) }}
						</div>
						<div v-else>Aucun joueur éliminé ce round.</div>
						<div v-if="result.votes">
							<h3>Votes :</h3>
							<ul>
								<li v-for="(vote, idx) in result.votes" :key="idx">
									Joueur {{ getPlayerNumberById(vote.target) }} a reçu {{ vote.count }} vote(s)
								</li>
							</ul>
						</div>
					</div>
					<button class="gButton" @click="nextRound">Round suivant</button>
				</div>
			</div>
			<div class="rightCol">
        <section class="panel hub-panel">
          <div class="panel--header">
            <NuxtIcon name="pixelarticons:users" />
            <h2 class="panel--header--title">Hub d’attente</h2>
          </div>
          <div class="panel--content">
            <div><b>Code du salon :</b> {{ gameCode }}</div>
            <div style="margin: 1rem 0 0.5rem 0;"><b>Joueurs connectés :</b></div>
            <ul>
              <li v-for="player in players" :key="player.id">
                <span v-if="player.id === myUserId">Moi</span>
                <span v-else>Joueur {{ getPlayerNumberById(player.id) }}</span>
                <span v-if="player.isAI"> (IA)</span>
              </li>
            </ul>
            <div v-if="isCreator">
              <button class="gButton important" @click="startGame" :disabled="gameStatus !== 'waiting'">Lancer la partie</button>
              <div v-if="gameStatus === 'starting'" style="margin-top: 1rem; color: #4caf50;">Début dans {{ startCountdown }}…</div>
            </div>
            <div v-else>
              <span v-if="gameStatus === 'waiting'" style="color: #aaa;">En attente du créateur pour démarrer…</span>
              <span v-else-if="gameStatus === 'starting'" style="color: #4caf50;">Début dans {{ startCountdown }}…</span>
            </div>
          </div>
        </section>
      </div>
		</div>

		<!-- Terminal dynamique en bas de page -->
		<div class="game-terminal">
			<template v-if="step === 'reponse'">
				<div>Question : <b>{{ question }}</b></div>
				<div style="color: #4caf50; margin-bottom: 0.5rem;">Temps restant : {{ responseTimer }}s</div>
				<input v-model="response" type="text" placeholder="Votre réponse" class="terminal-input" @keyup.enter="submitResponse" :disabled="responseLoading || !canRespond" />
				<button class="gButton important" @click="submitResponse" :disabled="responseLoading || !canRespond">
					Envoyer
				</button>
				<div v-if="!canRespond" style="color: #aaa;">En attente des autres joueurs…</div>
				<div v-if="responseError" style="color: red">{{ responseError }}</div>
			</template>
			<template v-else-if="step === 'vote'">
				<div>Question : <b>{{ question }}</b></div>
				<div>Liste des joueurs (anonymisés) :</div>
				<ul>
					<li v-for="(player, idx) in players" :key="player.id">
						<label>
							<input
								type="radio"
								:value="player.id"
								v-model="voteTarget"
								:disabled="player.id === myUserId || !canVote"
							/>
							Joueur {{ idx + 1 }}
							<span v-if="player.id === myUserId">(Moi)</span>
						</label>
					</li>
				</ul>
				<div style="color: #4caf50; margin-bottom: 0.5rem;">Temps restant : {{ responseTimer }}s</div>
				<button class="gButton important" @click="submitVote" :disabled="voteLoading || !voteTarget || !canVote">
					Voter
				</button>
				<div v-if="!canVote" style="color: #aaa;">En attente des autres joueurs…</div>
				<div v-if="voteError" style="color: red">{{ voteError }}</div>
			</template>
			<template v-else-if="step === 'question'">
				<div v-if="isMyTurnToAsk">
					<div style="color: #4caf50; margin-bottom: 0.5rem;">Temps restant : {{ questionTimer }}s</div>
					<input v-model="question" type="text" placeholder="Écris ta question..." class="terminal-input" @keyup.enter="submitPlayerQuestion" />
					<button class="gButton important" @click="submitPlayerQuestion" :disabled="!question">Envoyer la question</button>
				</div>
				<div v-else style="color: #aaa;">En attente de la question…</div>
			</template>
			<template v-else>
				<div style="color: #aaa;">Aucune action requise pour l’instant…</div>
			</template>
		</div>
	</div>
</template>

<style scoped lang="scss">
.homepageWrap {
	position: relative;
	min-height: 100vh;
	width: 100%;
	max-width: 1100px;
	margin: 0 auto;
	padding: 1.25rem;
	box-sizing: border-box;
	padding-bottom: 180px;
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
	gap: 1rem;
	min-width: 0;
}

.rightCol {
	min-height: 1px;
	min-width: 0;
}

.game-terminal {
	position: fixed;
	left: 0;
	right: 0;
	bottom: 0;
	background: #181818;
	padding: 1.5rem 2rem;
	border-top: 2px solid #333;
	display: flex;
	flex-direction: column;
	gap: 1rem;
	z-index: 100;
}

.terminal-input {
	width: 100%;
	padding: 0.7rem 1rem;
	border-radius: 6px;
	border: 1px solid #444;
	background: #222;
	color: #fff;
	font-size: 1.1rem;
}
</style>