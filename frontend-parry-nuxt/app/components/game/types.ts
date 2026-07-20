export type GameStatus = 'waiting' | 'in_progress' | 'finished'

export type RoundStatus =
	| 'en_attente_question'
	| 'en_attente_reponses'
	| 'en_attente_votes'
	| 'termine'
	| null

export interface SpriteAssets {
	response: string
	question: string
	elimination: string
}

export interface Player {
	id: string
	nickname: string
	alias?: string
	sprites?: SpriteAssets
	isAlive: boolean
	isAI: boolean
}

export interface Spectator {
	id: string
	nickname: string
}

export interface AnimalConfig {
	alias: string
	animalName: string
	sprites: SpriteAssets
	color?: string
	description?: string
}

export interface Answer {
	playerId: string
	text: string
}

export type Role = 'player' | 'proai'
export type Winner = 'PLAYERS_WIN' | 'AI_WINS' | string | null