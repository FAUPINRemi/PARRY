import type { Player } from '@/components/game/types'

export interface GameInfoState {
	code: string
	round: number
	status: string
	players: Player[]
	myUserId: string | null
}

export function useGameInfo() {
	const gameInfo = useState<GameInfoState | null>('game-info', () => null)

	function setGameInfo(info: GameInfoState | null) {
		gameInfo.value = info
	}

	return { gameInfo, setGameInfo }
}