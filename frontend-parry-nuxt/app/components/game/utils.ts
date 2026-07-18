import type { Player } from '@/components/game/types'

export function playerAlias(
	playerId: string,
	players: Player[],
	myUserId: string | null
): string {
	if (!playerId) return '???'

	const player = players.find(p => p.id === playerId)
	if (!player) return '???'

	return player.alias || '???'
}

const FALLBACK_SPRITES: Record<'response' | 'question' | 'elimination', string> = {
	response: '/images/sprites/response/wait_corbeau.png',
	question: '/images/sprites/questions/question_corbeau.png',
	elimination: '/images/sprites/eliminations/dead_corbeau.png',
}

export function playerSpriteUrl(
	playerId: string,
	spriteType: 'response' | 'question' | 'elimination',
	players: Player[]
): string | undefined {
	if (!playerId) return undefined

	const player = players.find(p => p.id === playerId)
	if (!player) return undefined

	return player.sprites?.[spriteType] || FALLBACK_SPRITES[spriteType]
}

export function jwt() {
	if (typeof window === 'undefined') return null
	return localStorage.getItem('jwt')
}

export function authHeaders(): Record<string, string> {
	const t = jwt()
	return t ? { Authorization: `Bearer ${t}` } : {}
}