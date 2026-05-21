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

export function playerSpriteUrl(
	playerId: string,
	spriteType: 'response' | 'question' | 'elimination',
	players: Player[]
): string | undefined {
	if (!playerId) return undefined

	const player = players.find(p => p.id === playerId)
	if (!player || !player.sprites) return undefined

	return player.sprites[spriteType]
}

export function jwt() {
	if (typeof window === 'undefined') return null
	return localStorage.getItem('jwt')
}

export function authHeaders(): Record<string, string> {
	const t = jwt()
	return t ? { Authorization: `Bearer ${t}` } : {}
}