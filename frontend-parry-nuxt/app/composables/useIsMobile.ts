import { ref, onMounted, onBeforeUnmount } from 'vue'

const MOBILE_QUERY = '(max-width: 768px)'

export function useIsMobile() {
	const isMobile = ref(false)

	onMounted(() => {
		const mql = window.matchMedia(MOBILE_QUERY)
		isMobile.value = mql.matches

		const onChange = (e: MediaQueryListEvent) => { isMobile.value = e.matches }
		mql.addEventListener('change', onChange)
		onBeforeUnmount(() => mql.removeEventListener('change', onChange))
	})

	return { isMobile }
}
