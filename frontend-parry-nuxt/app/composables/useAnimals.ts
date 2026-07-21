import { ref, onMounted } from 'vue'
import type { AnimalConfig } from '@/components/game/types'

export const useAnimals = () => {
  const animals = ref<AnimalConfig[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  onMounted(async () => {
    try {
      loading.value = true
      error.value = null
      const response = await $fetch('/api/game/animals', {
        credentials: 'include',
      })

      if (response.success && response.animals) {
        animals.value = response.animals
      } else {
        error.value = 'Failed to load animals'
      }
    } catch (e) {
      console.error('Error loading animals:', e)
      error.value = 'Failed to load animals'
    } finally {
      loading.value = false
    }
  })

  return { animals, loading, error }
}
