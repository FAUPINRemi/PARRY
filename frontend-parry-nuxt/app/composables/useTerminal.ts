import type { EventData } from "../types/terminal";

export const useTerminal = () => {
  const nuxtApp = useNuxtApp();
  // Create a shared state that persists across components
  const terminalHistory = useState<EventData[]>("terminal-history", () => []);

  const emitEvent = (data: EventData) => {
    // Add to history so new listeners can see past events
    terminalHistory.value.push(data);
    // Trigger the hook for real-time updates
    return nuxtApp.hooks.callHook("terminal:event", data);
  };

  const onEvent = (callback: (data: EventData) => void) => {
    // Immediately play back history to the new subscriber
    terminalHistory.value.forEach(callback);

    // Register for future events
    return nuxtApp.hooks.hook("terminal:event", callback);
  };

  return {
    emitEvent,
    onEvent,
    terminalHistory, // Exporting this gives you a direct reactive array too
  };
};
