export function useApi() {
	const config = useRuntimeConfig()
	const apiBase = config.public.apiBase as string

	// Signal partagé : une requête API a reçu 401 (session expirée)
	const sessionExpired = useState<boolean>('auth:sessionExpired', () => false)

	function apiFetch(path: string, options: RequestInit = {}): Promise<Response> {
		return fetch(`${apiBase}${path}`, {
			...options,
			credentials: 'include',
		}).then(res => {
			// Ne pas déclencher sur /login ou /logout pour éviter les boucles
			if (res.status === 401 && !path.includes('/login') && !path.includes('/logout')) {
				sessionExpired.value = true
			}
			return res
		})
	}

	return { apiBase, apiFetch, sessionExpired }
}