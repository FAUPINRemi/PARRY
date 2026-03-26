import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import type {
	GameStatus,
	RoundStatus,
	Player,
	Answer,
	Role,
	Winner
} from '@/components/game/types'

import { authHeaders, playerAlias as playerAliasUtil } from '@/components/game/utils'

export function useGame() {
	const { emitEvent, setTerminalAction, onTerminalSubmit } = useTerminal()
	const { apiFetch, apiBase } = useApi()
	const { setGameInfo } = useGameInfo()

	const route = useRoute()
	const router = useRouter()

	const gameCode = ref('')
	const gameStatus = ref<GameStatus>('waiting')
	const players = ref<Player[]>([])

	const roundId = ref<string | null>(null)
	const roundNumber = ref(0)
	const roundStatus = ref<RoundStatus>(null)

	const question = ref('')
	const questionMasterId = ref<string | null>(null)

	const answeredCount = ref(0)
	const votedCount = ref(0)
	const totalAlive = ref(0)

	const answers = ref<Answer[]>([])
	const revoteCandidates = ref<string[] | null>(null)
	const eliminatedPlayerId = ref<string | null>(null)

	const winner = ref<Winner>(null)

	const myUserId = ref<string | null>(null)
	const isCreator = ref(false)

	const myRole = ref<Role>('player')
	const hasAnswered = ref(false)
	const hasVoted = ref(false)
	const enableProAI = ref(false)

	const startCountdown = ref(0)

	const lockedAnswers = ref<Answer[]>([])
	const answersLocked = ref(false)
	const lastRoundId = ref<string | null>(null)

	const roundCreating = ref(false)
	const eliminationDone = ref(false)
	const victoryChecked = ref(false)

	let aiTriggerRound = ''
	let aiTriggerTime = 0
	let aiResponseTriggerRound = ''
	let aiResponseTriggerTime = 0
	const AI_RETRY_INTERVAL = 10_000

	const isMyTurnToAsk = computed(() => questionMasterId.value === myUserId.value)

	const alivePlayers = computed(() => players.value.filter(p => p.isAlive))
	const deadPlayers = computed(() => players.value.filter(p => !p.isAlive))

	const amIAlive = computed(() => {
		if (!myUserId.value) return true
		const me = players.value.find(p => p.id === myUserId.value)
		return me ? me.isAlive : true
	})

	function playerAlias(playerId: string): string {
		return playerAliasUtil(playerId, players.value, myUserId.value)
	}

	let pollInterval: ReturnType<typeof setInterval> | null = null

	async function fetchGameState() {
		try {
			const res = await apiFetch(`/api/game/${gameCode.value}/state`, { headers: authHeaders() })
			if (res.status === 401) {
				if (pollInterval) { clearInterval(pollInterval); pollInterval = null }
				await router.push('/')
				return
			}
			if (res.status === 404) {
				if (pollInterval) { clearInterval(pollInterval); pollInterval = null }
				await router.push('/')
				return
			}
			if (!res.ok) return

			const data = await res.json()
			if (!data.success) return

			// Un joueur a quitté en cours de partie → retour accueil pour tous
			if (data.game.abandoned === true) {
				if (pollInterval) { clearInterval(pollInterval); pollInterval = null }
				emitEvent({ message: 'Un joueur a quitté la partie.', type: 'error' })
				setGameInfo(null)
				await router.push('/')
				return
			}

			const prevGameStatus = gameStatus.value
			const prevRoundStatus = roundStatus.value
			const prevRoundId = roundId.value
			const prevAnsweredCount = answeredCount.value

			gameStatus.value = data.game.status
			players.value = data.game.players || []

			if (data.game.isCreator === true) isCreator.value = true
			if (data.game.myUserId && !myUserId.value) myUserId.value = data.game.myUserId

			if (data.game.winner === 'players') winner.value = 'PLAYERS_WIN'
			else if (data.game.winner === 'ai') winner.value = 'AI_WINS'
			else winner.value = null

			if (data.game.round) {
				const r = data.game.round

				roundId.value = r.id
				roundNumber.value = r.number
				roundStatus.value = r.status

				question.value = r.question || ''
				questionMasterId.value = r.questionMasterId

				answeredCount.value = r.answeredCount
				votedCount.value = r.votedCount
				totalAlive.value = r.totalAlive

				revoteCandidates.value = r.revoteCandidates || null
				eliminatedPlayerId.value = r.eliminatedPlayerId || null

				if (prevRoundId !== null && prevRoundId !== r.id) {
					answersLocked.value = false
					eliminationDone.value = false
					victoryChecked.value = false
					hasAnswered.value = false
					hasVoted.value = false
					question.value = ''
				}

				if (r.answers && r.answers.length > 0) {
					if (r.id !== lastRoundId.value || !answersLocked.value) {
						lastRoundId.value = r.id
						lockedAnswers.value = [...r.answers].sort(() => Math.random() - 0.5)
						answersLocked.value = true
					}
					answers.value = lockedAnswers.value
				}
			} else {
				roundStatus.value = null
			}

			setGameInfo({
				code: gameCode.value,
				round: roundNumber.value,
				status: gameStatus.value,
				players: players.value,
				myUserId: myUserId.value,
			})

			if (isCreator.value && roundStatus.value === 'en_attente_question' && roundId.value) {
				const now = Date.now()
				if (roundId.value !== aiTriggerRound || now - aiTriggerTime > AI_RETRY_INTERVAL) {
					const isFirstTrigger = roundId.value !== aiTriggerRound
					aiTriggerRound = roundId.value
					aiTriggerTime = now
					triggerAI(isFirstTrigger ? 2500 : 0)
				}
			}

			if (isCreator.value && roundStatus.value === 'en_attente_reponses' && roundId.value) {
				const now = Date.now()
				const answeredChanged = answeredCount.value !== prevAnsweredCount
				if (answeredChanged || roundId.value !== aiResponseTriggerRound || now - aiResponseTriggerTime > AI_RETRY_INTERVAL) {
					aiResponseTriggerRound = roundId.value
					aiResponseTriggerTime = now
					triggerAI()
				}
			}

			if (prevGameStatus !== gameStatus.value) {
				onGameStatusChange(gameStatus.value)
			}

			if (prevRoundStatus !== roundStatus.value || prevRoundId !== roundId.value) {
				onRoundStatusChange(roundStatus.value, prevRoundStatus)
			}
		} catch {
		}
	}

	function onGameStatusChange(status: GameStatus) {
		if (status === 'in_progress') {
			emitEvent({ message: 'La partie a commencé !', type: 'success' })
			fetchMyRole()

			if (isCreator.value && !roundCreating.value) {
				roundCreating.value = true
				createNewRound()
			}
		} else if (status === 'finished') {
			setTerminalAction(null)
			emitEvent({
				message: winner.value === 'PLAYERS_WIN' ? 'Les joueurs ont gagné !' : "L'IA a gagné !",
				type: 'success'
			})
		}
	}

	function onRoundStatusChange(status: RoundStatus, prev: RoundStatus) {
		if (status === 'en_attente_question') {
			answersLocked.value = false
			eliminationDone.value = false
			victoryChecked.value = false
			hasAnswered.value = false
			hasVoted.value = false
			question.value = ''

			if (isMyTurnToAsk.value) {
				setTerminalAction({
					type: 'question',
					label: 'Posez votre question :',
					placeholder: 'Écrivez votre question...'
				})
				emitEvent({ message: "C'est votre tour de poser une question.", type: 'info' })
			} else {
				setTerminalAction(null)
				emitEvent({ message: `${playerAlias(questionMasterId.value ?? '')} pose la question...`, type: 'info' })
				if (isCreator.value) triggerAI(2500)
			}
		} else if (status === 'en_attente_reponses') {
			setTerminalAction(null)
			emitEvent({ message: `Question : ${question.value}`, type: 'info' })

			if (!hasAnswered.value && amIAlive.value) {
				setTerminalAction({
					type: 'response',
					label: 'Votre réponse :',
					placeholder: 'Écrivez votre réponse...'
				})
			}

			if (isCreator.value) triggerAI()
		} else if (status === 'en_attente_votes') {
			setTerminalAction(null)
			emitEvent({ message: 'Phase de vote : quelle réponse semble la plus suspecte ?', type: 'info' })
			if (isCreator.value) triggerAI()
		} else if (status === 'termine') {
			setTerminalAction(null)
			if (isCreator.value && !eliminationDone.value) {
				eliminationDone.value = true
				runElimination()
			}
		}
	}

	async function createNewRound() {
		try {
			const res = await apiFetch('/api/round/create', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', ...authHeaders() },
				body: JSON.stringify({ gameCode: gameCode.value })
			})

			const data = await res.json()

			if (data.success) {
				roundId.value = data.round.id
				emitEvent({ message: `Round ${data.round.roundNumber} commencé !`, type: 'info' })
				await triggerAI()
			} else {
				emitEvent({ message: data.error || 'Erreur création round', type: 'error' })
			}
		} catch {
			emitEvent({ message: 'Erreur réseau (round)', type: 'error' })
		} finally {
			roundCreating.value = false
		}
	}

	async function triggerAI(delayMs = 0) {
		if (delayMs > 0) await new Promise(resolve => setTimeout(resolve, delayMs))

		try {
			const res = await apiFetch(`/api/ai/play/${gameCode.value}`, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', ...authHeaders() }
			})

			if (!res.ok) {
				const data = await res.json().catch(() => ({}))
				emitEvent({
					message: `IA erreur (${res.status}): ${data.details ?? data.error ?? ''}`,
					type: 'error'
				})
			}
		} catch {
		}
	}

	async function runElimination() {
		if (!roundId.value) return

		try {
			const res = await apiFetch(`/api/round/${roundId.value}/eliminate`, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', ...authHeaders() }
			})

			const data = await res.json()

			if (data.revote) {
				emitEvent({ message: 'Égalité ! Revote en cours...', type: 'info' })
				eliminationDone.value = false
			} else if (data.eliminatedPlayerId) {
				const eliminated = players.value.find(p => p.id === data.eliminatedPlayerId)
				emitEvent({ message: `${eliminated?.nickname ?? 'Un joueur'} a été éliminé !`, type: 'error' })

				await fetchGameState()

				const eliminatedAfterFetch = players.value.find(p => p.id === data.eliminatedPlayerId)
				if (eliminatedAfterFetch?.isAI) {
					try {
						await apiFetch(`/api/game/${gameCode.value}/check-victory`, {
							method: 'POST',
							headers: { 'Content-Type': 'application/json', ...authHeaders() }
						})
					} catch {}

					winner.value = 'PLAYERS_WIN'
					await fetchGameState()
					return
				}

				if (!victoryChecked.value) {
					victoryChecked.value = true
					await checkVictory()
				}
			}
		} catch {
			emitEvent({ message: 'Erreur réseau (élimination)', type: 'error' })
		}
	}

	async function checkVictory() {
		try {
			const res = await apiFetch(`/api/game/${gameCode.value}/check-victory`, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', ...authHeaders() }
			})

			const data = await res.json()

			if (data.gameOver === true) {
				winner.value = data.winner
				await fetchGameState()
			} else if (data.gameOver === false) {
				await apiFetch(`/api/round/${roundId.value}/finish`, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', ...authHeaders() }
				})

				setTimeout(() => {
					roundCreating.value = true
					createNewRound()
				}, 4000)
			}
		} catch {
			emitEvent({ message: 'Erreur réseau (victoire)', type: 'error' })
		}
	}

	async function startGame() {
		if (!isCreator.value) return

		try {
			const res = await apiFetch(`/api/game/${gameCode.value}/start`, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', ...authHeaders() },
				body: JSON.stringify({ proAiEnabled: enableProAI.value })
			})

			const data = await res.json()
			if (!data.success) {
				emitEvent({ message: data.error || 'Impossible de démarrer', type: 'error' })
			}
		} catch {
			emitEvent({ message: 'Erreur réseau (démarrage)', type: 'error' })
		}
	}

	async function fetchMyRole() {
		if (!myUserId.value) return

		try {
			const res = await apiFetch(`/api/game/${gameCode.value}/my-role?userId=${myUserId.value}`, {
				headers: authHeaders()
			})
			const data = await res.json()

			if (data.success && data.role === 'proai') {
				myRole.value = 'proai'
				emitEvent({ message: "Vous etes le Pro-IA ! Aidez l'IA a survivre.", type: 'info' })
			}
		} catch {
		}
	}

	async function submitQuestion(text: string) {
		if (!roundId.value || !myUserId.value) return

		try {
			const res = await apiFetch(`/api/round/${roundId.value}/question`, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ question: text })
			})

			const data = await res.json()

			if (data.success) {
				setTerminalAction(null)
				emitEvent({ message: `Question envoyée : "${text}"`, type: 'success' })
			} else {
				emitEvent({ message: data.error || 'Erreur envoi question', type: 'error' })
			}
		} catch {
			emitEvent({ message: 'Erreur réseau (question)', type: 'error' })
		}
	}

	async function submitResponse(text: string) {
		if (!roundId.value || !myUserId.value) return

		try {
			const res = await apiFetch(`/api/round/${roundId.value}/response`, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ response: text })
			})

			const data = await res.json()

			if (data.success) {
				hasAnswered.value = true
				setTerminalAction(null)
				emitEvent({ message: 'Réponse envoyée !', type: 'success' })
			} else {
				emitEvent({ message: data.error || 'Erreur envoi réponse', type: 'error' })
			}
		} catch {
			emitEvent({ message: 'Erreur réseau (réponse)', type: 'error' })
		}
	}

	async function submitVote(targetPlayerId: string) {
		if (!roundId.value || !myUserId.value || hasVoted.value) return

		try {
			const res = await apiFetch(`/api/round/${roundId.value}/vote`, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ targetPlayerId })
			})

			const data = await res.json()

			if (data.success) {
				hasVoted.value = true
				emitEvent({ message: 'Vote enregistré !', type: 'success' })
			} else {
				emitEvent({ message: data.error || 'Erreur vote', type: 'error' })
			}
		} catch {
			emitEvent({ message: 'Erreur réseau (vote)', type: 'error' })
		}
	}

	let unsubTerminal: (() => void) | undefined

	async function joinGameIfNeeded() {
		try {
			const res = await apiFetch(`/api/game/${gameCode.value}/join`, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', ...authHeaders() },
				body: JSON.stringify({})
			})

			const data = await res.json()

			if (!data.success && data.error !== 'DEJA_REJOINT') {
				emitEvent({ message: data.error || 'Impossible de rejoindre', type: 'error' })
			}
		} catch {
			/* silent */
		}
	}

	async function goToMenu() {
		intentionalLeave = true
		if (pollInterval) { clearInterval(pollInterval); pollInterval = null }
		setGameInfo(null)
		try {
			await apiFetch(`/api/game/${gameCode.value}/delete`, { method: 'POST', headers: { 'Content-Type': 'application/json' } })
		} catch { /* silent — on navigue quand même */ }
		await router.push('/')
	}

	async function startNewGame() {
		if (!isCreator.value) return
		intentionalLeave = true
		try {
			const res = await apiFetch(`/api/game/${gameCode.value}/restart`, { method: 'POST', headers: { 'Content-Type': 'application/json' } })
			const data = await res.json()
			if (!data.success) emitEvent({ message: 'Erreur lors du redémarrage', type: 'error' })
			intentionalLeave = false 
		} catch {
			emitEvent({ message: 'Erreur réseau (restart)', type: 'error' })
		}
	}

	const { connect: mercureConnect, disconnect: mercureDisconnect } = useGameEvents(
		gameCode,
		() => fetchGameState()
	)

	let handleBeforeUnload: (() => void) | null = null

	let intentionalLeave = false

	function sendLeaveBeacon() {
		if (intentionalLeave || !gameCode.value) return
		const config = useRuntimeConfig()
		const apiBase = config.public.apiBase as string
		fetch(`${apiBase}/api/game/${gameCode.value}/leave`, {
			method: 'POST',
			credentials: 'include',
			headers: { 'Content-Type': 'application/json' },
			keepalive: true
		}).catch(() => {})
	}

	onMounted(async () => {
		gameCode.value = (route.query.code as string) || ''

		myUserId.value = localStorage.getItem('userId')

		if (process.client) {
			window.addEventListener('beforeunload', sendLeaveBeacon)
		}

		unsubTerminal = onTerminalSubmit(({ type, value }) => {
			if (type === 'question') submitQuestion(value)
			if (type === 'response') submitResponse(value)
		})

		await joinGameIfNeeded()

		mercureConnect()

		fetchGameState()
		pollInterval = setInterval(fetchGameState, 5000)

		if (isCreator.value) {
			handleBeforeUnload = () => {
				if (gameCode.value) {
					fetch(`${apiBase}/api/game/${gameCode.value}/delete`, {
						method: 'POST',
						keepalive: true,
						credentials: 'include'
					})
				}
			}
			window.addEventListener('beforeunload', handleBeforeUnload)
		}
	})

	onUnmounted(() => {
		if (pollInterval) clearInterval(pollInterval)
		if (unsubTerminal) unsubTerminal()
		mercureDisconnect()
		if (handleBeforeUnload) {
			window.removeEventListener('beforeunload', handleBeforeUnload)
		if (process.client) {
			window.removeEventListener('beforeunload', sendLeaveBeacon)
		}
		setTerminalAction(null)
		setGameInfo(null)
	})

	return {
		gameCode,
		gameStatus,
		players,

		roundId,
		roundNumber,
		roundStatus,

		question,
		questionMasterId,

		answeredCount,
		votedCount,
		totalAlive,

		answers,
		revoteCandidates,
		eliminatedPlayerId,

		winner,

		myUserId,
		isCreator,

		myRole,
		hasAnswered,
		hasVoted,
		enableProAI,
		startCountdown,

		isMyTurnToAsk,
		alivePlayers,
		deadPlayers,
		amIAlive,
		playerAlias,

		startGame,
		submitVote,
		goToMenu,
		startNewGame,
	}
}