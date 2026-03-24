<script setup lang="ts">
import { onMounted } from 'vue'
import { useAuth } from '@/composables/auth/useAuth'
const { initFromStorage } = useAuth()
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
