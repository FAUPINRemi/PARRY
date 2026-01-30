<script setup lang="ts">
  const props = defineProps({
    icon: { type: String, required: false },
    label: { type: String, required: false },
  });
</script>

<template>
  <section class="gSection">
    <div v-if="props.label" class="gSection--header">
      <h2 class="gSection--header--title">{{ props.label }}</h2>
    </div>
    <div class="gSection--content">
      <slot></slot>
    </div>
  </section>
</template>

<style scoped lang="scss">
  @import "@/assets/style/components/sections";
</style>
