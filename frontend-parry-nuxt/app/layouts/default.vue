<script setup lang="ts">
  useHead({
    bodyAttrs: {
      class: "gBody",
    },
  });
</script>

<template>
  <main class="gRow gRow--main">
    <Panel label="PARRY V1.0" icon="pixelarticons:mood-happy">
      <slot />
    </Panel>
    <PanelProfile />
  </main>
  <aside class="gRow gRow--aside">
    <PanelTerminal />
  </aside>
</template>

<style lang="scss">
  @use "@/assets/style/layout/default";
</style>
