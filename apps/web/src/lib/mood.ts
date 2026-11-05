import type { Monitor, MonitorStatus } from "./types";

/**
 * Mascot moods (PRD §3.1).
 *
 *  happy    — last check succeeded under 1000 ms
 *  panting  — succeeded but >= 1000 ms
 *  sad      — the monitor is down (open incident)
 *  sleeping — paused
 *  curious  — no checks yet / first check pending
 */
export type Mood = "happy" | "panting" | "sad" | "sleeping" | "curious";

/** PRD §3.1 — the "slow" threshold for the panting mood. */
export const SLOW_RESPONSE_MS = 1000;

export interface MoodInput {
  status: MonitorStatus;
  isPaused: boolean;
  lastCheckedAt: string | null;
  lastResponseMs: number | null;
}

/** Shared by the dashboard and the public status page. */
export function moodFromStatus(input: MoodInput): Mood {
  if (input.isPaused) return "sleeping";
  if (input.status === "down") return "sad";
  if (input.status === "pending" || input.lastCheckedAt === null) return "curious";
  if (input.lastResponseMs !== null && input.lastResponseMs >= SLOW_RESPONSE_MS) {
    return "panting";
  }

  return "happy";
}

export function moodForMonitor(monitor: Monitor): Mood {
  return moodFromStatus({
    status: monitor.current_status,
    isPaused: monitor.is_paused,
    lastCheckedAt: monitor.last_checked_at,
    lastResponseMs: monitor.last_response_ms,
  });
}

/**
 * One mascot for the whole dashboard: unhappy if anything is down, else the
 * mood of the most interesting monitor (panting beats happy, etc.).
 */
export function moodForDashboard(monitors: Monitor[]): Mood {
  if (monitors.length === 0) return "curious";
  if (monitors.some((m) => moodForMonitor(m) === "sad")) return "sad";
  if (monitors.every((m) => m.is_paused)) return "sleeping";
  if (monitors.some((m) => moodForMonitor(m) === "curious")) return "curious";
  if (monitors.some((m) => moodForMonitor(m) === "panting")) return "panting";

  return "happy";
}

export const MOOD_LABEL: Record<Mood, string> = {
  happy: "Happy",
  panting: "Panting",
  sad: "Sad",
  sleeping: "Sleeping",
  curious: "Curious",
};
