<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, nextTick, watch } from 'vue'

const { onEvent, terminalAction, submitTerminalInput } = useTerminal()

const messages = ref<string[]>([])
const inputValue = ref('')
const listRef = ref<HTMLUListElement | null>(null)
let unhook: (() => void) | undefined

onMounted(() => {
  unhook = onEvent((data: any) => {
    messages.value.push(data.message)
    nextTick(() => {
      if (listRef.value) listRef.value.scrollTop = listRef.value.scrollHeight
    })
  })
})

onBeforeUnmount(() => {
  if (unhook) unhook()
})

watch(terminalAction, () => {
  inputValue.value = ''
})

function handleSubmit() {
  const val = inputValue.value.trim()
  if (!val || !terminalAction.value) return
  submitTerminalInput(terminalAction.value.type, val)
  inputValue.value = ''
}
</script>

<template>
  <Panel label="Terminal" icon="pixelarticons:script" class="terminal">
    <ul ref="listRef" class="terminal-messages">
      <li v-for="(message, i) in messages" :key="i">> {{ message }}</li>
    </ul>
    <div v-if="terminalAction" class="terminal-input-row">
      <span class="terminal-prompt">> {{ terminalAction.label }}</span>
      <input
        v-model="inputValue"
        class="terminal-input"
        :placeholder="terminalAction.placeholder"
        autocomplete="off"
        @keyup.enter="handleSubmit"
      />
      <button class="gButton important" @click="handleSubmit">Envoyer</button>
    </div>
  </Panel>
</template>

<style lang="scss">
@use "@/assets/style/components/panelTerminal";

.terminal-messages {
  max-height: 120px;
  overflow-y: auto;
  list-style: none;
  padding: 0;
  margin: 0 0 0.5rem 0;
}

.terminal-input-row {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  border-top: 1px solid #333;
  padding-top: 0.5rem;
  flex-wrap: wrap;
}

.terminal-prompt {
  color: #4caf50;
  white-space: nowrap;
  font-size: 0.9rem;
}

.terminal-input {
  flex: 1 1 auto;
  min-width: 0;
  padding: 0.4rem 0.6rem;
  background: #111;
  border: 1px solid #444;
  color: #fff;
  font-family: inherit;
  font-size: 0.9rem;

  &:focus { outline: none; border-color: #4caf50; }
}
</style>
