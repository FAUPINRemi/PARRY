<script setup lang="ts">
  import ProfileNotConnected from "./ProfileNotConnected.vue";

  const { emitEvent } = useTerminal();
  let isHidden = ref(false);

  function openPanel() {
    isHidden.value = false;
    emitEvent({
      message: "Panneau utilisateur ouvert",
      type: "info",
    });
  }

  function closePanel() {
    isHidden.value = true;
    emitEvent({
      message: "Panneau utilisateur fermé",
      type: "info",
    });
  }
</script>

<template>
  <Panel
    v-if="!isHidden"
    label="Utilisateur"
    icon="pixelarticons:user"
    class="profil">
    <button
      @click="closePanel"
      class="gButton profilClose transparentBackground iconOnly">
      <Icon name="pixelarticons:close-box" />
      Close
    </button>
    <ProfileNotConnected />
  </Panel>
  <button v-else @click="openPanel" class="gButton profilOpen">
    <Icon name="pixelarticons:user" />
    Profil
  </button>
</template>

<style lang="scss">
  @use "@/assets/style/components/panelProfil";
</style>
