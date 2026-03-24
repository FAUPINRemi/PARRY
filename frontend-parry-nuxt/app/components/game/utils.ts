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

	const p = players.find(p => p.id === playerId)
	if (p?.isAI) return 'IA'
	if (playerId === myUserId) return 'Vous'

	const humanIds = players
		.filter(p => !p.isAI)
		.map(p => p.id)
		.sort()

	const idx = humanIds.indexOf(playerId)
	return idx >= 0 ? ALIASES[idx % ALIASES.length] : '???'
}

export function jwt() {
	return process.client ? localStorage.getItem('jwt') : null
}

export function authHeaders() {
	const t = jwt()
	return t ? { Authorization: `Bearer ${t}` } : {}
}