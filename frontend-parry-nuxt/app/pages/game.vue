<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const { emitEvent, setTerminalAction, onTerminalSubmit } = useTerminal()
const { apiFetch } = useApi()
const { setGameInfo } = useGameInfo()
const route = useRoute()
const router = useRouter()

const ALIASES = [
  'Renard', 'Loup', 'Corbeau', 'Serpent', 'Tigre',
  'Faucon', 'Ours', 'Vipère', 'Lynx', 'Puma',
  'Aigle', 'Requin', 'Panthère', 'Scorpion', 'Coyote',
  'Hibou', 'Jaguar', 'Raton', 'Baleine', 'Vautour'
]

function playerAlias(playerId: string): string {
  if (!playerId) return '???'
  const p = players.value.find(p => p.id === playerId)
  if (p?.isAI) return 'IA'
  if (playerId === myUserId.value) return 'Vous'
  const humanIds = players.value.filter(p => !p.isAI).map(p => p.id).sort()
  const idx = humanIds.indexOf(playerId)
  return idx >= 0 ? ALIASES[idx % ALIASES.length] : '???'
}

type GameStatus = 'waiting' | 'in_progress' | 'finished'
type RoundStatus = 'en_attente_question' | 'en_attente_reponses' | 'en_attente_votes' | 'termine' | null

interface Player {
  id: string
  nickname: string
  isAlive: boolean
  isAI: boolean
}

interface Answer {
  playerId: string
  text: string
}

const gameCode       = ref('')
const gameStatus     = ref<GameStatus>('waiting')
const players        = ref<Player[]>([])
const roundId        = ref<string | null>(null)
const roundNumber    = ref(0)
const roundStatus    = ref<RoundStatus>(null)
const question       = ref('')
const questionMasterId = ref<string | null>(null)
const answeredCount  = ref(0)
const votedCount     = ref(0)
const totalAlive     = ref(0)
const answers        = ref<Answer[]>([])
const revoteCandidates = ref<string[] | null>(null)
const eliminatedPlayerId = ref<string | null>(null)
const winner         = ref<string | null>(null)

const myUserId       = ref<string | null>(null)
const isCreator      = ref(false)
const myRole         = ref<'player' | 'proai'>('player')
const hasAnswered    = ref(false)
const hasVoted       = ref(false)
const enableProAI    = ref(false)
const startCountdown = ref(0)
const lockedAnswers  = ref<Answer[]>([])
const answersLocked  = ref(false)
const lastRoundId    = ref<string | null>(null)

const roundCreating     = ref(false)
const eliminationDone   = ref(false)
const victoryChecked    = ref(false)

let aiTriggerRound = ''
let aiTriggerTime  = 0
const AI_RETRY_INTERVAL = 10_000 

const isMyTurnToAsk = computed(() => questionMasterId.value === myUserId.value)
const alivePlayers  = computed(() => players.value.filter(p => p.isAlive))
const deadPlayers   = computed(() => players.value.filter(p => !p.isAlive))
const amIAlive      = computed(() => {
  if (!myUserId.value) return true
  const me = players.value.find(p => p.id === myUserId.value)
  return me ? me.isAlive : true
})

function jwt() {
  return process.client ? localStorage.getItem('jwt') : null
}

function authHeaders() {
  const t = jwt()
  return t ? { Authorization: `Bearer ${t}` } : {}
}

let pollInterval: ReturnType<typeof setInterval> | null = null

async function fetchGameState() {
  try {
    const res = await apiFetch(`/api/game/${gameCode.value}/state`, { headers: authHeaders() })
    if (!res.ok) return
    const data = await res.json()
    if (!data.success) return

    const prevGameStatus  = gameStatus.value
    const prevRoundStatus = roundStatus.value
    const prevRoundId     = roundId.value

    gameStatus.value = data.game.status
    players.value    = data.game.players || []

    if (data.game.winner === 'players') winner.value = 'PLAYERS_WIN'
    else if (data.game.winner === 'ai') winner.value = 'AI_WINS'

    if (data.game.round) {
      const r = data.game.round
      roundId.value          = r.id
      roundNumber.value      = r.number
      roundStatus.value      = r.status
      question.value         = r.question || ''
      questionMasterId.value = r.questionMasterId
      answeredCount.value    = r.answeredCount
      votedCount.value       = r.votedCount
      totalAlive.value       = r.totalAlive
      revoteCandidates.value = r.revoteCandidates || null
      eliminatedPlayerId.value = r.eliminatedPlayerId || null

      if (prevRoundId !== null && prevRoundId !== r.id) {
        answersLocked.value   = false
        eliminationDone.value = false
        victoryChecked.value  = false
        hasAnswered.value     = false
        hasVoted.value        = false
        question.value        = ''
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
        aiTriggerTime  = now
        triggerAI(isFirstTrigger ? 2500 : 0)  
      }
    }

    if (prevGameStatus !== gameStatus.value) {
      onGameStatusChange(gameStatus.value)
    }
    if (prevRoundStatus !== roundStatus.value || prevRoundId !== roundId.value) {
      onRoundStatusChange(roundStatus.value, prevRoundStatus)
    }
  } catch { /* silent */ }
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
    emitEvent({ message: winner.value === 'PLAYERS_WIN' ? 'Les joueurs ont gagné !' : "L'IA a gagné !", type: 'success' })
  }
}

function onRoundStatusChange(status: RoundStatus, prev: RoundStatus) {
  if (status === 'en_attente_question') {
    answersLocked.value   = false
    eliminationDone.value = false
    victoryChecked.value  = false
    hasAnswered.value     = false
    hasVoted.value        = false
    question.value        = ''

    if (isMyTurnToAsk.value) {
      setTerminalAction({ type: 'question', label: 'Posez votre question :', placeholder: 'Écrivez votre question...' })
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
      setTerminalAction({ type: 'response', label: 'Votre réponse :', placeholder: 'Écrivez votre réponse...' })
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
      emitEvent({ message: `IA erreur (${res.status}): ${data.details ?? data.error ?? ''}`, type: 'error' })
    }
  } catch { /* network error — retry handled by polling */ }
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

/* --- */

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
    const res = await apiFetch(`/api/game/${gameCode.value}/my-role?userId=${myUserId.value}`, { headers: authHeaders() })
    const data = await res.json()
    if (data.success && data.role === 'proai') {
      myRole.value = 'proai'
      emitEvent({ message: 'Vous etes le Pro-IA ! Aidez l\'IA a survivre.', type: 'info' })
    }
  } catch { /* silent */ }
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

/* --- */
let unsubTerminal: (() => void) | undefined

/* --- */
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
  } catch { /* silent */ }
}

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

function votableAnswers() {
  if (!revoteCandidates.value) return answers.value
  return answers.value.filter(a => revoteCandidates.value!.includes(a.playerId))
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
</script>

<template>
  <div class="gameWrap">
    <div class="gameGrid">

      <!-- ═══════════════════ PANNEAU GAUCHE (écran principal) ══════════════════ -->
      <div class="leftCol">

        <!-- Barre joueurs vivants/éliminés -->
        <div v-if="gameStatus !== 'waiting'" class="players-bar">
          <span class="players-bar--alive">
            <Icon name="pixelarticons:user" />
            {{ alivePlayers.length }} en vie
          </span>
          <span class="players-bar--dead">
            <Icon name="pixelarticons:close" />
            {{ deadPlayers.length }} éliminé{{ deadPlayers.length > 1 ? 's' : '' }}
          </span>
          <span class="players-bar--round" v-if="roundNumber > 0">
            Round {{ roundNumber }}
          </span>
        </div>

        <!-- ── Hub d'attente ────────────────────────────────────────────── -->
        <template v-if="gameStatus === 'waiting'">
          <h1 class="game-screen-title">Salon d'attente</h1>
          <p class="game-code-display">Code : <strong>{{ gameCode }}</strong></p>

          <div class="hub-players">
            <p class="hub-players--label">Joueurs connectés ({{ players.length }}) :</p>
            <ul class="hub-players--list">
              <li v-for="p in players" :key="p.id">
                <Icon name="pixelarticons:user" />
                {{ p.nickname }}
                <span v-if="p.id === myUserId" class="tag-me">(moi)</span>
              </li>
            </ul>
          </div>

          <div v-if="isCreator" class="hub-controls">
            <label class="pro-ai-toggle">
              <input type="checkbox" v-model="enableProAI" />
              Activer le rôle Pro-IA
              <span class="pro-ai-hint">(un joueur aide secrètement l'IA)</span>
            </label>
            <button
              class="gButton important"
              :disabled="players.length < 3"
              @click="startGame"
            >
              <Icon name="pixelarticons:play" />
              Lancer la partie
            </button>
            <p v-if="players.length < 3" class="hub-hint">
              {{ 3 - players.length }} joueur(s) supplémentaire(s) requis
            </p>
          </div>
          <div v-else class="hub-waiting">
            <p>En attente du créateur pour démarrer…</p>
          </div>
        </template>

        <!-- ── En partie ─────────────────────────────────────────────────── -->
        <template v-else-if="gameStatus === 'in_progress'">

          <!-- Phase question -->
          <template v-if="roundStatus === 'en_attente_question'">
            <h1 class="game-screen-title">Round {{ roundNumber }} — Question</h1>
            <div v-if="isMyTurnToAsk" class="phase-box phase-box--mine">
              <p>C'est votre tour de poser une question.</p>
              <p class="phase-hint">Écrivez votre question dans le terminal ci-dessous.</p>
            </div>
            <div v-else class="phase-box">
              <p>
                <strong>{{ playerAlias(questionMasterId ?? '') }}</strong>
                est en train de poser la question…
              </p>
              <div class="loading-dots"><span></span><span></span><span></span></div>
            </div>
          </template>

          <!-- Phase réponse -->
          <template v-else-if="roundStatus === 'en_attente_reponses'">
            <h1 class="game-screen-title">Round {{ roundNumber }} — Réponses</h1>
            <div class="question-display">
              <p class="question-display--label">Question :</p>
              <p class="question-display--text">{{ question }}</p>
            </div>
            <div class="phase-box">
              <p v-if="!hasAnswered">
                Répondez dans le terminal ci-dessous.
              </p>
              <p v-else class="phase-success">
                Réponse envoyée ✓
              </p>
              <div class="answers-progress">
                <div
                  class="answers-progress--bar"
                  :style="{ width: totalAlive > 0 ? (answeredCount / totalAlive * 100) + '%' : '0%' }"
                ></div>
              </div>
              <p class="phase-hint">{{ answeredCount }} / {{ totalAlive }} joueurs ont répondu</p>
            </div>
          </template>

          <!-- Phase vote -->
          <template v-else-if="roundStatus === 'en_attente_votes'">
            <h1 class="game-screen-title">
              Round {{ roundNumber }} — Vote
              <span v-if="revoteCandidates" class="revote-badge">Revote !</span>
            </h1>
            <div class="question-display">
              <p class="question-display--label">Question :</p>
              <p class="question-display--text">{{ question }}</p>
            </div>
            <p class="phase-hint">
              Votez pour la réponse qui vous semble la plus suspecte (IA).
            </p>
            <div class="answers-grid">
              <div
                v-for="(ans, idx) in votableAnswers()"
                :key="ans.playerId"
                class="answer-card"
                :class="{ 'answer-card--voted': hasVoted }"
              >
                <p class="answer-card--label">Réponse {{ idx + 1 }}</p>
                <p class="answer-card--text">{{ ans.text }}</p>
                <template v-if="amIAlive">
                  <button
                    v-if="!hasVoted && ans.playerId !== myUserId"
                    class="gButton important answer-card--vote-btn"
                    @click="submitVote(ans.playerId)"
                  >
                    Voter cette réponse
                  </button>
                  <p v-else-if="ans.playerId === myUserId" class="phase-hint">(votre réponse)</p>
                </template>
                <p v-else class="phase-hint">Vous êtes éliminé — observation uniquement</p>
              </div>
            </div>
            <p class="phase-hint">{{ votedCount }} / {{ totalAlive }} votes</p>
          </template>

          <!-- Écran élimination -->
          <template v-else-if="roundStatus === 'termine'">
            <h1 class="game-screen-title">Élimination</h1>
            <div v-if="eliminatedPlayerId" class="elimination-box">
              <p class="elimination-box--name">
                {{ players.find(p => p.id === eliminatedPlayerId)?.nickname ?? 'Un joueur' }}
                a été éliminé !
              </p>
              <p v-if="players.find(p => p.id === eliminatedPlayerId)?.isAI" class="elimination-box--ai">
                🎉 C'était l'IA ! Les joueurs ont gagné !
              </p>
              <p v-else class="elimination-box--human">
                Ce n'était pas l'IA…
              </p>
            </div>
            <div v-else class="phase-box">
              <p>Calcul des votes…</p>
              <div class="loading-dots"><span></span><span></span><span></span></div>
            </div>
          </template>

        </template>

        <!-- ── Fin de partie ──────────────────────────────────────────────── -->
        <template v-else-if="gameStatus === 'finished'">
          <div class="gameover-screen">
            <template v-if="winner === 'PLAYERS_WIN'">
              <template v-if="myRole === 'proai'">
                <h1 class="gameover-screen--title gameover-screen--ai">Vous avez perdu...</h1>
                <p>L'IA a été éliminée. Votre mission a échoué.</p>
              </template>
              <template v-else>
                <h1 class="gameover-screen--title gameover-screen--players">Les joueurs ont gagné !</h1>
                <p>L'IA a été démasquée. Bravo !</p>
              </template>
            </template>
            <template v-else-if="winner === 'AI_WINS'">
              <template v-if="myRole === 'proai'">
                <h1 class="gameover-screen--title gameover-screen--players">Vous avez gagné !</h1>
                <p>L'IA a survécu grâce à vous, Pro-IA !</p>
              </template>
              <template v-else>
                <h1 class="gameover-screen--title gameover-screen--ai">L'IA a gagné !</h1>
                <p>Les humains ont été trompés.</p>
              </template>
            </template>
            <template v-else>
              <h1 class="gameover-screen--title">Partie terminée</h1>
            </template>
            <div class="gameover-actions">
              <button v-if="isCreator" class="gButton important" @click="startNewGame">
                <Icon name="pixelarticons:play" /> Nouvelle partie
              </button>
              <button class="gButton" @click="goToMenu">
                <Icon name="pixelarticons:home" /> Retour au menu
              </button>
            </div>
          </div>
        </template>

      </div>


    </div>
  </div>
</template>

<style scoped lang="scss">
.gameWrap {
  width: 100%;
  max-width: 1100px;
  margin: 0 auto;
  padding: 1rem;
  box-sizing: border-box;
}

.gameGrid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1rem;
  align-items: start;
}

/* --- */
.players-bar {
  display: flex;
  gap: 1.2rem;
  align-items: center;
  padding: 0.4rem 0;
  margin-bottom: 0.8rem;
  border-bottom: 1px solid #333;
  font-size: 0.85rem;

  &--alive { color: #4caf50; }
  &--dead  { color: #f44336; }
  &--round { color: #aaa; margin-left: auto; }
}

/* --- */
.game-screen-title {
  font-size: 1.4rem;
  margin: 0 0 1rem 0;
}

.game-code-display {
  font-size: 1rem;
  margin-bottom: 1rem;
  strong { letter-spacing: 0.15em; }
}

/* --- */
.hub-players {
  margin-bottom: 1.2rem;
  &--label { font-size: 0.85rem; color: #aaa; margin-bottom: 0.4rem; }
  &--list  { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.3rem; }
}

.hub-controls {
  display: flex;
  flex-direction: column;
  gap: 0.8rem;
}

.hub-hint {
  color: #888;
  font-size: 0.8rem;
}

.hub-waiting {
  color: #aaa;
  font-size: 0.9rem;
}

.pro-ai-toggle {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  font-size: 0.9rem;
}

.pro-ai-hint {
  color: #888;
  font-size: 0.75rem;
}

/* --- */
.phase-box {
  padding: 1rem;
  border: 1px solid #333;
  display: flex;
  flex-direction: column;
  gap: 0.6rem;

  &--mine { border-color: #4caf50; }
}

.phase-hint  { color: #aaa; font-size: 0.85rem; }
.phase-success { color: #4caf50; }

/* --- */
.question-display {
  background: #111;
  border-left: 3px solid #4caf50;
  padding: 0.8rem 1rem;
  margin-bottom: 1rem;

  &--label { color: #aaa; font-size: 0.8rem; margin-bottom: 0.3rem; }
  &--text  { font-size: 1.1rem; }
}

/* --- */
.answers-progress {
  height: 4px;
  background: #333;
  border-radius: 2px;
  overflow: hidden;

  &--bar {
    height: 100%;
    background: #4caf50;
    transition: width 0.3s;
  }
}

/* --- */
.answers-grid {
  display: flex;
  flex-direction: column;
  gap: 0.8rem;
  margin: 0.8rem 0;
}

.answer-card {
  border: 1px solid #333;
  padding: 0.8rem;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  min-width: 0;
  overflow: hidden;

  &--voted { opacity: 0.7; }
  &--label { color: #aaa; font-size: 0.8rem; }
  &--text  { font-size: 1rem; word-break: break-word; overflow-wrap: break-word; white-space: pre-wrap; }
  &--vote-btn { align-self: flex-start; }
}

.revote-badge {
  font-size: 0.75rem;
  background: #f44336;
  color: #fff;
  padding: 0.1rem 0.4rem;
  vertical-align: middle;
  margin-left: 0.5rem;
}

/* --- */
.elimination-box {
  padding: 1.5rem;
  border: 2px solid #f44336;
  text-align: center;
  display: flex;
  flex-direction: column;
  gap: 0.8rem;

  &--name  { font-size: 1.2rem; }
  &--ai    { color: #4caf50; font-size: 1rem; }
  &--human { color: #888; font-size: 0.9rem; }
}

/* --- */
.gameover-screen {
  text-align: center;
  padding: 2rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.8rem;

  &--title  { font-size: 1.8rem; margin: 0; }
  &--players { color: #4caf50; }
  &--ai      { color: #f44336; }
}

.gameover-actions {
  display: flex;
  gap: 0.8rem;
  flex-wrap: wrap;
  justify-content: center;
  margin-top: 1rem;
}

/* --- */
.loading-dots {
  display: flex;
  gap: 0.3rem;
  span {
    width: 6px; height: 6px;
    border-radius: 50%;
    background: #aaa;
    animation: blink 1.2s infinite;
    &:nth-child(2) { animation-delay: 0.2s; }
    &:nth-child(3) { animation-delay: 0.4s; }
  }
}
@keyframes blink {
  0%, 80%, 100% { opacity: 0.2; }
  40% { opacity: 1; }
}

li.player--dead  { opacity: 0.45; text-decoration: line-through; }
li.player--me    { color: #fff; }

.player-icon { margin-right: 0.4rem; font-size: 0.8rem; }
.tag-me  { color: #aaa; font-size: 0.75rem; margin-left: 0.3rem; }
.tag-ai  { background: #f44336; color: #fff; font-size: 0.7rem; padding: 0 0.3rem; margin-left: 0.3rem; }
</style>
