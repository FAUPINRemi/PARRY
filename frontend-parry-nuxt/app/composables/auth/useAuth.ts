import { ref, computed } from 'vue'

export function useAuth() {
	const { emitEvent } = useTerminal()
	const { apiFetch } = useApi()

	const loading = ref(false)
	const error = ref<string | null>(null)
	const success = ref(false)

	const connectedEmail = ref<string | null>(null)
	const isLoggedIn = computed(() => !!connectedEmail.value)

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
				connectedEmail.value = params.username // fallback simple
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

	async function logout() {
		loading.value = true
		error.value = null

		try {
			const res = await apiFetch('/api/logout', { method: 'POST' })
			if (res.ok) {
				connectedEmail.value = null
				emitEvent({ message: 'Déconnexion réussie.', type: 'info' })
			} else {
				error.value = 'Erreur logout'
				emitEvent({ message: error.value, type: 'error' })
			}
		} catch {
			error.value = 'Erreur réseau'
			emitEvent({ message: error.value, type: 'error' })
		} finally {
			loading.value = false
		}
	}

	return {
		loading,
		error,
		success,
		connectedEmail,
		isLoggedIn,
		login,
		register,
		logout,
	}
}