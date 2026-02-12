<script setup lang="ts">
  const { emitEvent } = useTerminal();
  let isHidden = ref(false);

  function openPanel() {
    isHidden.value = false;
  }
  function closePanel() {
    isHidden.value = true;
  }

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
  <Panel
    v-if="!isHidden"
    label="Profil"
    icon="pixelarticons:user"
    class="profil">
    <button @click="closePanel" class="gButton transparent profilClose">
      <Icon name="pixelarticons:close-box" />
      Close
    </button>
    <button @click="handleClick" class="gButton">Log</button>
  </Panel>
  <button v-else @click="openPanel" class="gButton profilOpen">
    <Icon name="pixelarticons:user" />
    Profil
  </button>
</template>

<style lang="scss">
  @use "@/assets/style/components/panelProfil";
</style>
