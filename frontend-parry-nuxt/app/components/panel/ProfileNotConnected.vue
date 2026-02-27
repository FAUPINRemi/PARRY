<script setup lang="ts">
  const { emitEvent } = useTerminal();
  onMounted(() => {
    emitEvent({ message: "Pas d’utilisateur connecté.", type: "info" });
  });
</script>

<template>
  <div class="panelProfilNotConnected">
    <p>Vous n'êtes pas connecté.</p>
    <p>
      <NuxtLink
        class="gButton important"
        @click="emitEvent({ message: 'TODO : Login', type: 'info' })">
        <Icon name="pixelarticons:user" />
        Se connecter
      </NuxtLink>
      ou
      <NuxtLink
        class="gButton inline"
        @click="emitEvent({ message: 'TODO : Register', type: 'info' })">
        <Icon name="pixelarticons:user-plus" />
        Créer un compte
      </NuxtLink>
    </p>
  </div>
</template>

<style lang="scss">
  @use "@/assets/style/components/panelProfilNotConnected";
</style>
