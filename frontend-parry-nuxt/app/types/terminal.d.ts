import type { HookResult } from "@nuxt/schema";

export interface EventData {
  message: string;
  type: "info" | "error" | "success";
}

declare module "#app" {
  interface RuntimeNuxtHooks {
    "terminal:event": (data: EventData) => HookResult;
  }
}
