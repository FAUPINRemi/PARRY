import { watch, type Ref } from 'vue'

import type { GameStatus, RoundStatus, Winner } from '@/components/game/types'

interface GameAudioRefs {
	gameStatus: Ref<GameStatus>
	roundStatus: Ref<RoundStatus>
	eliminatedPlayerId: Ref<string | null>
	winner: Ref<Winner>
	proAiActive: Ref<boolean>
}

/** Câble les transitions de musique du jeu sur l'état de la partie (useGame). */
export function useGameMusic(game: GameAudioRefs) {
	const { currentTrack, playMusic, playEliminationSequence } = useGameAudio()

	function playEndMusic() {
		const track = game.winner.value === 'AI_WINS' ? 'defeat' : 'victory'
		if (currentTrack.value !== track) playMusic(track, { loop: true })
	}

	// Salon d'attente : musique de menu tant que la partie n'a pas démarré
	watch(game.gameStatus, (status, prevStatus) => {
		if (status === 'waiting' && currentTrack.value !== 'menu') {
			playMusic('menu', { loop: true, loopDip: true })
		} else if (status === 'in_progress' && prevStatus !== 'in_progress') {
			startInProgressMusic()
		} else if (status === 'finished') {
			playEndMusic()
		}
	}, { immediate: true })

	// Le vainqueur peut être confirmé après le passage à 'finished' (revote / vérif tardive)
	watch(game.winner, (w) => {
		if (game.gameStatus.value !== 'finished' || !w) return
		playEndMusic()
	})

	async function startInProgressMusic() {
		if (game.proAiActive.value) {
			await playMusic('role', { loop: false, fadeMs: 500 })
			// on ne repasse à l'ambiance que si rien d'autre n'a pris la main pendant la révélation du rôle
			if (game.gameStatus.value === 'in_progress') {
				playMusic('ambient', { loop: true })
			}
		} else {
			playMusic('ambient', { loop: true })
		}
	}

	// Élimination : on tamise l'ambiance, on joue le stinger, puis on remonte le son
	// (sauf si la partie est déjà terminée entre-temps — le watcher ci-dessus prend alors le relais)
	watch(game.eliminatedPlayerId, async (id, prevId) => {
		if (!id || prevId || game.roundStatus.value !== 'termine') return
		await playEliminationSequence()
	})
}
