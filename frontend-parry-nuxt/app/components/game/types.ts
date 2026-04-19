export type GameStatus = 'waiting' | 'in_progress' | 'finished'

export type RoundStatus =
	| 'en_attente_question'
	| 'en_attente_reponses'
	| 'en_attente_votes'
	| 'termine'
	| null

export interface Player {
	id: string
	nickname: string
	isAlive: boolean
	isAI: boolean
}

export interface Answer {
	playerId: string
	text: string
}

export type Role = 'player' | 'proai'
export type Winner = 'PLAYERS_WIN' | 'AI_WINS' | string | null