<script setup lang="ts">
const props = defineProps<{
  spriteUrl: string
}>()
</script>

<template>
  <div class="eliminationSprite" :style="{ backgroundImage: `url('${props.spriteUrl}')` }" />
</template>

<style lang="scss">
@use "@/assets/style/components/panelGame";
</style>
