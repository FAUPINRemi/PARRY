<script setup lang="ts">
  const { emitEvent } = useTerminal();

  const handleClick = () => {
    emitEvent({
      message: "User clicked the button! - ",
      type: "info",
    });
  };
</script>

<template>
  <main class="gRow gRow--main">
    <Panel label="Parry">
      <button @click="handleClick">test</button>
    </Panel>
  </main>
  <aside class="gRow gRow--aside">
    <PanelTerminal />
    <Panel label="Parry">
      <button @click="handleClick">test</button>
    </Panel>
  </aside>
</template>

<style scoped lang="scss"></style>
