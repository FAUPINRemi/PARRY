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
	const { apiFetch } = useApi()
	const { setGameInfo } = useGameInfo()

	const route = useRoute()
	const router = useRouter()

	// --- state
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

	const startCountdown = ref(0) // gardé si utilisé ailleurs

	const lockedAnswers = ref<Answer[]>([])
	const answersLocked = ref(false)
	const lastRoundId = ref<string | null>(null)

	const roundCreating = ref(false)
	const eliminationDone = ref(false)
	const victoryChecked = ref(false)

	let aiTriggerRound = ''
	let aiTriggerTime = 0
	const AI_RETRY_INTERVAL = 10_000

	// --- computed
	const isMyTurnToAsk = computed(() => questionMasterId.value === myUserId.value)

	const alivePlayers = computed(() => players.value.filter(p => p.isAlive))
	const deadPlayers = computed(() => players.value.filter(p => !p.isAlive))

	const amIAlive = computed(() => {
		if (!myUserId.value) return true
		const me = players.value.find(p => p.id === myUserId.value)
		return me ? me.isAlive : true
	})

	// --- helpers
	function playerAlias(playerId: string): string {
		return playerAliasUtil(playerId, players.value, myUserId.value)
	}

	// --- polling
	let pollInterval: ReturnType<typeof setInterval> | null = null

	async function fetchGameState() {
		try {
			const res = await apiFetch(`/api/game/${gameCode.value}/state`, { headers: authHeaders() })
			if (res.status === 401) {
				if (pollInterval) { clearInterval(pollInterval); pollInterval = null }
				return
			}
			if (!res.ok) return

			const data = await res.json()
			if (!data.success) return

			const prevGameStatus = gameStatus.value
			const prevRoundStatus = roundStatus.value
			const prevRoundId = roundId.value

			gameStatus.value = data.game.status
			players.value = data.game.players || []

			if (data.game.winner === 'players') winner.value = 'PLAYERS_WIN'
			else if (data.game.winner === 'ai') winner.value = 'AI_WINS'

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

			// trigger IA (créateur)
			if (isCreator.value && roundStatus.value === 'en_attente_question' && roundId.value) {
				const now = Date.now()
				if (roundId.value !== aiTriggerRound || now - aiTriggerTime > AI_RETRY_INTERVAL) {
					const isFirstTrigger = roundId.value !== aiTriggerRound
					aiTriggerRound = roundId.value
					aiTriggerTime = now
					triggerAI(isFirstTrigger ? 2500 : 0)
				}
			}

			if (prevGameStatus !== gameStatus.value) {
				onGameStatusChange(gameStatus.value)
			}

			if (prevRoundStatus !== roundStatus.value || prevRoundId !== roundId.value) {
				onRoundStatusChange(roundStatus.value, prevRoundStatus)
			}
		} catch {
			/* silent */
		}
	}

	// --- transitions
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

	// --- API actions
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
			/* retry handled by polling */
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
			/* silent */
		}
	}

	async function submitQuestion(text: string) {
		if (!roundId.value || !myUserId.value) return

		try {
			const res = await apiFetch(`/api/round/${roundId.value}/question`, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', ...authHeaders() },
				body: JSON.stringify({ userId: myUserId.value, question: text })
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
				headers: { 'Content-Type': 'application/json', ...authHeaders() },
				body: JSON.stringify({ userId: myUserId.value, response: text })
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
				headers: { 'Content-Type': 'application/json', ...authHeaders() },
				body: JSON.stringify({ voterId: myUserId.value, targetPlayerId })
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

	// --- join / navigation
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
		if (isCreator.value) localStorage.removeItem(`parry_creator_${gameCode.value}`)
		setGameInfo(null)
		await router.push('/')
	}

	async function startNewGame() {
		if (isCreator.value) localStorage.removeItem(`parry_creator_${gameCode.value}`)
		setGameInfo(null)

		const { apiFetch: fetch } = useApi()

		try {
			const res = await fetch('/api/game/create', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', ...authHeaders() },
				body: JSON.stringify({ isPrivate: true })
			})

			const data = await res.json()

			if (data.success) {
				const code = data.game.code || data.game.id
				localStorage.setItem(`parry_creator_${code}`, '1')
				await router.push({ path: '/game', query: { code } })
			}
		} catch {
			await router.push('/')
		}
	}

	// --- lifecycle
	onMounted(async () => {
		myUserId.value = localStorage.getItem('userId')
		gameCode.value = (route.query.code as string) || ''
		isCreator.value = !!localStorage.getItem(`parry_creator_${gameCode.value}`)

		unsubTerminal = onTerminalSubmit(({ type, value }) => {
			if (type === 'question') submitQuestion(value)
			if (type === 'response') submitResponse(value)
		})

		await joinGameIfNeeded()
		fetchGameState()
		pollInterval = setInterval(fetchGameState, 2500)
	})

	onUnmounted(() => {
		if (pollInterval) clearInterval(pollInterval)
		if (unsubTerminal) unsubTerminal()
		setTerminalAction(null)
		setGameInfo(null)
	})

	return {
		// state
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

		// computed / helpers
		isMyTurnToAsk,
		alivePlayers,
		deadPlayers,
		amIAlive,
		playerAlias,

		// actions
		startGame,
		submitVote,
		goToMenu,
		startNewGame,
	}
}