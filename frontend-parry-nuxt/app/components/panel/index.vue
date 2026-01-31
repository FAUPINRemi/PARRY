<script setup lang="ts">
  //TODO : May cause problems when inhereting from use cases
  defineOptions({
    inheritAttrs: false,
  });

  const props = defineProps({
    icon: { type: String, required: false },
    label: { type: String, required: false },
  });
</script>

<template>
  <section class="panel" v-bind="$attrs">
    <div v-if="props.label" class="panel--header">
      <h2 class="panel--header--title">{{ props.label }}</h2>
    </div>
    <div class="panel--content">
      <slot></slot>
    </div>
  </section>
</template>

<style scoped lang="scss">
  @import "@/assets/style/components/panels";
</style>
