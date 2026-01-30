<script setup lang="ts">
  useHead({
    bodyAttrs: {
      class: "gBody",
    },
  });
</script>

<template>
  <slot />
</template>

<style lang="scss">
  @use "@/assets/style/layout/default";
</style>
