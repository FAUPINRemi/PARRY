import type { EventData } from '../types/terminal'

export type TerminalAction = {
  type: 'question' | 'response'
  label: string
  placeholder: string
} | null

export const useTerminal = () => {
  const nuxtApp = useNuxtApp()
  const terminalHistory = useState<EventData[]>('terminal-history', () => [])
  const terminalAction = useState<TerminalAction>('terminal-action', () => null)

  const emitEvent = (data: EventData) => {
    terminalHistory.value.push(data)
    return nuxtApp.hooks.callHook('terminal:event', data)
  }

  const onEvent = (callback: (data: EventData) => void) => {
    terminalHistory.value.forEach(callback)
    return nuxtApp.hooks.hook('terminal:event', callback)
  }

  const setTerminalAction = (action: TerminalAction) => {
    terminalAction.value = action
  }

  const submitTerminalInput = (type: string, value: string) => {
    nuxtApp.hooks.callHook('terminal:submit', { type, value })
  }

  const onTerminalSubmit = (callback: (data: { type: string; value: string }) => void) => {
    return nuxtApp.hooks.hook('terminal:submit', callback)
  }

  return {
    emitEvent,
    onEvent,
    terminalHistory,
    terminalAction,
    setTerminalAction,
    submitTerminalInput,
    onTerminalSubmit,
  }
}
