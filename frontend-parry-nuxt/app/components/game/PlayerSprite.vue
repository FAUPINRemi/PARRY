<script setup lang="ts">
const props = defineProps<{
  alias: string
  spriteUrl: string
}>()
</script>

<template>
  <div v-if="props.alias" class="playerSprite" :style="{ backgroundImage: `url('${props.spriteUrl}')` }" />
</template>

<style lang="scss">
@use "@/assets/style/components/panelGame";
</style>
