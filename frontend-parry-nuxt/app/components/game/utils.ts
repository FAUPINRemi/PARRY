import type { Player } from '@/components/game/types'

export const ALIASES = [
	'Renard', 'Loup', 'Corbeau', 'Serpent', 'Tigre',
	'Faucon', 'Ours', 'Vipère', 'Lynx', 'Puma',
	'Aigle', 'Requin', 'Panthère', 'Scorpion', 'Coyote',
	'Hibou', 'Jaguar', 'Raton', 'Baleine', 'Vautour'
] as const

export function playerAlias(
	playerId: string,
	players: Player[],
	myUserId: string | null
): string {
	if (!playerId) return '???'

	const visibleIds = players
		.map(p => p.id)
		.sort()

	const idx = visibleIds.indexOf(playerId)
	return idx >= 0 ? (ALIASES[idx % ALIASES.length] ?? '???') : '???'
}

export function jwt() {
	if (typeof window === 'undefined') return null
	return localStorage.getItem('jwt')
}

export function authHeaders(): Record<string, string> {
	const t = jwt()
	return t ? { Authorization: `Bearer ${t}` } : {}
}