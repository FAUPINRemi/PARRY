<script setup lang="ts">
  const { onEvent } = useTerminal();
  const messages = ref<string[]>([]);

  let unhook: () => void;

  onMounted(() => {
    // Start listening only when mounted on the client
    unhook = onEvent((data) => {
      messages.value.push(data.message);
    });
  });

  onBeforeUnmount(() => {
    // Clean up to prevent memory leaks
    if (unhook) unhook();
  });
</script>

<template>
  <Panel label="Terminal" class="terminal">
    <ul>
      <li v-for="(message, i) in messages" :key="i">
        {{ message + " - " + i }}
      </li>
    </ul>
  </Panel>
</template>

<style lang="scss">
  @use "@/assets/style/components/panelTerminal";
</style>
