<script setup lang="ts">
  const { emitEvent } = useTerminal();

  const handleClick = () => {
    emitEvent({
      message: "User clicked the button! - ",
      type: "info",
    });
  };
</script>

<template>test</template>

<style scoped lang="scss"></style>
