<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, nextTick, watch } from 'vue'

const { onEvent, terminalAction, submitTerminalInput, emitEvent } = useTerminal()
const { micEnabled, isRecording, isTranscribing, startRecording, stopAndTranscribe } = useMicrophone()

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

async function handleMic() {
  if (isTranscribing.value) return

  if (isRecording.value) {
    try {
      const text = await stopAndTranscribe()
      if (text) inputValue.value = text
    } catch {
      emitEvent({ message: 'Transcription échouée, réessayez.', type: 'error' })
    }
  } else {
    await startRecording()
  }
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
        :placeholder="isTranscribing ? 'Transcription...' : terminalAction.placeholder"
        :disabled="isTranscribing"
        autocomplete="off"
        @keyup.enter="handleSubmit"
      />
      <button
        v-if="micEnabled"
        class="gButton mic-btn"
        :class="{ 'mic-btn--recording': isRecording, 'mic-btn--loading': isTranscribing }"
        :disabled="isTranscribing"
        @click="handleMic"
      >
        <Icon v-if="isTranscribing" name="pixelarticons:refresh" />
        <Icon v-else-if="isRecording" name="pixelarticons:close" />
        <Icon v-else name="pixelarticons:mic" />
      </button>
      <button class="gButton important" :disabled="isTranscribing" @click="handleSubmit">Envoyer</button>
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
