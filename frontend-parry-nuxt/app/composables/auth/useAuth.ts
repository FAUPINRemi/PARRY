import { computed } from 'vue'
import { useRouter } from 'vue-router'

export function useAuth() {
	const { emitEvent } = useTerminal()
	const { apiFetch } = useApi()
	const router = useRouter()

	const connectedPseudo = useState<string | null>('auth:pseudo', () => null)
	const connectedAvatarDataUrl = useState<string | null>('auth:avatar', () => null)
	const loading = useState<boolean>('auth:loading', () => false)
	const error = useState<string | null>('auth:error', () => null)
	const success = useState<boolean>('auth:success', () => false)
	const guestMode = useState<boolean>('auth:guestMode', () => false)

	const isLoggedIn = computed(() => !!connectedPseudo.value)

	function initFromStorage() {
		if (!process.client) return
		const pseudo = localStorage.getItem('userPseudo')
		if (pseudo) connectedPseudo.value = pseudo
		const avatarDataUrl = localStorage.getItem('userAvatarDataUrl')
		if (avatarDataUrl) connectedAvatarDataUrl.value = avatarDataUrl
		guestMode.value = localStorage.getItem('parry:guestMode') === '1'
	}

	/** L'utilisateur a explicitement choisi de jouer sans créer de compte. */
	function continueAsGuest() {
		guestMode.value = true
		if (process.client) localStorage.setItem('parry:guestMode', '1')
	}

	async function login(params: { username: string; password: string }) {
		loading.value = true
		error.value = null
		success.value = false

		try {
			const res = await apiFetch('/api/login', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify(params),
			})
			const data = await res.json().catch(() => ({}))

			if (res.ok && data.success) {
				connectedPseudo.value = data.user?.pseudo ?? data.user?.username ?? params.username
				connectedAvatarDataUrl.value = data.user?.avatarDataUrl ?? null
				if (process.client) {
					localStorage.setItem('userPseudo', connectedPseudo.value!)
					if (data.user?.id) localStorage.setItem('userId', data.user.id)
					if (connectedAvatarDataUrl.value) localStorage.setItem('userAvatarDataUrl', connectedAvatarDataUrl.value)
					else localStorage.removeItem('userAvatarDataUrl')
				}
				emitEvent({ message: 'Connexion réussie !', type: 'success' })
			} else {
				error.value = data.message || 'Identifiants invalides'
				emitEvent({ message: error.value, type: 'error' })
			}
		} catch {
			error.value = 'Erreur réseau'
			emitEvent({ message: error.value, type: 'error' })
		} finally {
			loading.value = false
		}
	}

	async function register(params: { email: string; password: string; pseudo: string }) {
		loading.value = true
		error.value = null
		success.value = false

		try {
			const res = await apiFetch('/api/register', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify(params),
			})
			const data = await res.json().catch(() => ({}))

			if (res.ok && data.status === 'success') {
				success.value = true
				connectedAvatarDataUrl.value = data.user?.avatarDataUrl ?? null
				if (process.client) {
					if (connectedAvatarDataUrl.value) localStorage.setItem('userAvatarDataUrl', connectedAvatarDataUrl.value)
					else localStorage.removeItem('userAvatarDataUrl')
				}
				emitEvent({ message: 'Inscription réussie !', type: 'success' })
			} else {
				error.value = data.message || "Erreur lors de l'inscription"
				emitEvent({ message: error.value, type: 'error' })
			}
		} catch {
			error.value = 'Erreur réseau'
			emitEvent({ message: error.value, type: 'error' })
		} finally {
			loading.value = false
		}
	}

	function forceLogout() {
		connectedPseudo.value = null
		connectedAvatarDataUrl.value = null
		if (process.client) {
			localStorage.removeItem('userPseudo')
			localStorage.removeItem('userId')
			localStorage.removeItem('userAvatarDataUrl')
		}
	}

	async function logout() {
		loading.value = true
		error.value = null

		try {
			const { gameInfo, setGameInfo } = useGameInfo()
			const gameCode = gameInfo.value?.code
			if (gameCode) {
				const leaveHeaders: Record<string, string> = { 'Content-Type': 'application/json' }
				if (typeof window !== 'undefined') {
					const token = localStorage.getItem('jwt')
					if (token) {
						leaveHeaders.Authorization = `Bearer ${token}`
					}
				}

				await apiFetch(`/api/game/${gameCode}/leave`, {
					method: 'POST',
					headers: leaveHeaders,
				}).catch(() => {})
				setGameInfo(null)
			}

			const res = await apiFetch('/api/logout', { method: 'POST' })
			if (res.ok || res.status === 401) {
				connectedPseudo.value = null
				connectedAvatarDataUrl.value = null
				if (process.client) {
					localStorage.removeItem('userPseudo')
					localStorage.removeItem('userId')
					localStorage.removeItem('userAvatarDataUrl')
				}
				emitEvent({ message: 'Déconnexion réussie.', type: 'info' })
				await router.push('/')
			} else {
				error.value = 'Erreur logout'
				emitEvent({ message: error.value, type: 'error' })
			}
		} catch {
			connectedPseudo.value = null
			connectedAvatarDataUrl.value = null
			if (process.client) {
				localStorage.removeItem('userPseudo')
				localStorage.removeItem('userId')
				localStorage.removeItem('userAvatarDataUrl')
			}
			await router.push('/')
		} finally {
			loading.value = false
		}
	}

	return {
		loading,
		error,
		success,
		connectedPseudo,
		connectedAvatarDataUrl,
		isLoggedIn,
		guestMode,
		initFromStorage,
		continueAsGuest,
		forceLogout,
		login,
		register,
		logout,
	}
}
