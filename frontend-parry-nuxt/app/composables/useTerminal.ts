import type { EventData } from "../types/terminal";

export const useTerminal = () => {
  const nuxtApp = useNuxtApp();

  const emitEvent = (data: EventData) => {
    return nuxtApp.hooks.callHook("terminal:event", data);
  };

  const onEvent = (callback: (data: EventData) => void) => {
    return nuxtApp.hooks.hook("terminal:event", callback);
  };

  return {
    emitEvent,
    onEvent,
  };
};
