import { computed } from 'vue'

export function useAuth() {
	const { emitEvent } = useTerminal()
	const { apiFetch } = useApi()

	// useState → état partagé entre tous les composants (singleton Nuxt)
	const connectedPseudo = useState<string | null>('auth:pseudo', () => null)
	const loading = useState<boolean>('auth:loading', () => false)
	const error = useState<string | null>('auth:error', () => null)
	const success = useState<boolean>('auth:success', () => false)

	const isLoggedIn = computed(() => !!connectedPseudo.value)

	function initFromStorage() {
		if (!process.client) return
		const pseudo = localStorage.getItem('userPseudo')
		if (pseudo) connectedPseudo.value = pseudo
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
				if (process.client) {
					localStorage.setItem('userPseudo', connectedPseudo.value!)
					if (data.user?.id) localStorage.setItem('userId', data.user.id)
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

	// Déconnexion forcée sans appel backend (session expirée côté serveur)
	function forceLogout() {
		connectedPseudo.value = null
		if (process.client) {
			localStorage.removeItem('userPseudo')
			localStorage.removeItem('userId')
		}
	}

	async function logout() {
		loading.value = true
		error.value = null

		try {
			const res = await apiFetch('/api/logout', { method: 'POST' })
			if (res.ok || res.status === 401) {
				connectedPseudo.value = null
				if (process.client) {
					localStorage.removeItem('userPseudo')
					localStorage.removeItem('userId')
				}
				emitEvent({ message: 'Déconnexion réussie.', type: 'info' })
			} else {
				error.value = 'Erreur logout'
				emitEvent({ message: error.value, type: 'error' })
			}
		} catch {
			connectedPseudo.value = null
			if (process.client) {
				localStorage.removeItem('userPseudo')
				localStorage.removeItem('userId')
			}
		} finally {
			loading.value = false
		}
	}

	return {
		loading,
		error,
		success,
		connectedPseudo,
		isLoggedIn,
		initFromStorage,
		forceLogout,
		login,
		register,
		logout,
	}
}
