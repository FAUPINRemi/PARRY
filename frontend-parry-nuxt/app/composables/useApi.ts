export function useApi() {
	const config = useRuntimeConfig()
	const apiBase = config.public.apiBase as string

	function apiFetch(path: string, options: RequestInit = {}): Promise<Response> {
		return fetch(`${apiBase}${path}`, {
			...options,
			credentials: 'include',
		})
	}

	return { apiBase, apiFetch }
}