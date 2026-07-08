<script setup lang="ts">
import { onMounted, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuth } from '@/composables/auth/useAuth'

const { initFromStorage, forceLogout, isLoggedIn } = useAuth()
const { sessionExpired } = useApi()
const router = useRouter()

watch(sessionExpired, (val) => {
	if (val && isLoggedIn.value) {
		forceLogout()
		sessionExpired.value = false
		router.push('/')
	} else if (val) {
		sessionExpired.value = false
	}
})

onMounted(() => initFromStorage())
</script>

<template>
  <NuxtLayout>
    <NuxtPage />
  </NuxtLayout>
</template>

<style lang="scss">
  @use "@/assets/style/global";
</style>
