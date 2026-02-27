<script setup lang="ts">
  const { emitEvent } = useTerminal();
</script>

<template>
  <div class="homepage">
    <h1 class="homepage--title">PARRY</h1>
    <pre class="homepage--ascii" aria-hidden="true">
ooooooooo.         .o.       ooooooooo.   ooooooooo.   oooooo   oooo
`888   `Y88.      .888.      `888   `Y88. `888   `Y88.  `888.   .8' 
 888   .d88'     .8"888.      888   .d88'  888   .d88'   `888. .8'  
 888ooo88P'     .8' `888.     888ooo88P'   888ooo88P'     `888.8'   
 888           .88ooo8888.    888`88b.     888`88b.        `888'    
 888          .8'     `888.   888  `88b.   888  `88b.       888     
o888o        o88o     o8888o o888o  o888o o888o  o888o     o888o
</pre
    >
    <div class="homepage--createRoom">
      <h2>Créer un salon</h2>
      <NuxtLink
        class="gButton important"
        @click="emitEvent({ message: 'TODO : Create game', type: 'info' })">
        <Icon name="pixelarticons:plus" />
        Créer un salon
      </NuxtLink>
    </div>
    <form class="homepage--joinRoom">
      <h2>Rejoindre le salon</h2>
      <label> Code du salon:</label>
      <div class="homepage--joinRoom--search">
        <input type="text" placeholder="ABC123FR" />
        <NuxtLink
          class="gButton important"
          @click="emitEvent({ message: 'TODO : Join game', type: 'info' })">
          <Icon name="pixelarticons:search" />
          Rejoindre
        </NuxtLink>
      </div>
    </form>
  </div>
</template>

<style scoped lang="scss">
  @use "@/assets/style/page/homepage";
</style>
