<script setup lang="ts">
  const { emitEvent } = useTerminal();

  onMounted(() => {
    emitEvent({
      message: "TODO : Login",
      type: "info",
    });
  });

  function handleClick() {
    emitEvent({ message: "TODO : Login", type: "info" });
  }
</script>

<template>
  <Panel label="Profil" class="utilisateur"> </Panel>
</template>

<style lang="scss">
  @use "@/assets/style/components/panelUtilisateur";
</style>
