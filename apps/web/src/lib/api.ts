import type {
  ApiError,
  Incident,
  Monitor,
  NotificationChannel,
  StatusPage,
  TelegramLink,
  User,
} from "./types";

/**
 * All requests go to this app's own route handlers, which hold the Sanctum
 * token in an httpOnly cookie (PRD §4.1). The Laravel origin never reaches
 * browser JavaScript.
 */
const API_ROOT = "/api/v1";
const AUTH_ROOT = "/api/auth";

export class ApiRequestError extends Error {
  readonly status: number;
  readonly errors: Record<string, string[]>;

  constructor(status: number, body: ApiError) {
    super(body.message || `Request failed (${status})`);
    this.status = status;
    this.errors = body.errors ?? {};
  }
}

async function request<T>(
  path: string,
  options: { method?: string; body?: unknown } = {},
): Promise<T> {
  const headers: Record<string, string> = { Accept: "application/json" };
  if (options.body !== undefined) headers["Content-Type"] = "application/json";

  const res = await fetch(path, {
    method: options.method ?? "GET",
    headers,
    body: options.body === undefined ? undefined : JSON.stringify(options.body),
    credentials: "same-origin",
    cache: "no-store",
  });

  const text = await res.text();
  const data = text ? JSON.parse(text) : {};

  if (!res.ok) {
    throw new ApiRequestError(res.status, data as ApiError);
  }

  return data as T;
}

export interface Paginated<T> {
  data: T[];
  links: unknown;
  meta: { current_page: number; per_page: number; total: number; last_page: number } | null;
}

export interface MonitorChartPoint {
  t: string;
  avg_ms: number | null;
  min_ms: number | null;
  max_ms: number | null;
  checks: number;
  failures: number;
}

export interface MonitorCheck {
  id: number;
  checked_at: string;
  status_code: number | null;
  response_time_ms: number | null;
  is_success: boolean;
  error_type: string | null;
  error_message: string | null;
}

export const api = {
  register: (input: {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
  }) =>
    request<{ user: User }>(`${AUTH_ROOT}/register`, {
      method: "POST",
      body: input,
    }),

  login: (input: { email: string; password: string }) =>
    request<{ user: User }>(`${AUTH_ROOT}/login`, {
      method: "POST",
      body: input,
    }),

  logout: () => request<{ message: string }>(`${AUTH_ROOT}/logout`, { method: "POST" }),

  me: () => request<{ user: User }>(`${AUTH_ROOT}/me`),

  updateProfile: (input: Record<string, unknown>) =>
    request<{ user: User }>(`${AUTH_ROOT}/me`, { method: "PATCH", body: input }),

  deleteAccount: (password: string) =>
    request<{ message: string }>(`${AUTH_ROOT}/me`, { method: "DELETE", body: { password } }),

  listMonitors: () => request<Paginated<Monitor>>(`${API_ROOT}/monitors`),

  getMonitor: (id: number) => request<{ monitor: Monitor }>(`${API_ROOT}/monitors/${id}`),

  createMonitor: (input: Record<string, unknown>) =>
    request<{ monitor: Monitor }>(`${API_ROOT}/monitors`, { method: "POST", body: input }),

  updateMonitor: (id: number, input: Record<string, unknown>) =>
    request<{ monitor: Monitor }>(`${API_ROOT}/monitors/${id}`, { method: "PATCH", body: input }),

  deleteMonitor: (id: number) =>
    request<{ message: string }>(`${API_ROOT}/monitors/${id}`, { method: "DELETE" }),

  pauseMonitor: (id: number) =>
    request<{ monitor: Monitor }>(`${API_ROOT}/monitors/${id}/pause`, { method: "POST" }),

  resumeMonitor: (id: number) =>
    request<{ monitor: Monitor }>(`${API_ROOT}/monitors/${id}/resume`, { method: "POST" }),

  checkNow: (id: number) =>
    request<{ monitor: Monitor }>(`${API_ROOT}/monitors/${id}/check`, { method: "POST" }),

  monitorResults: (id: number, range: "24h" | "7d" | "30d") =>
    request<{ range: string; bucket_seconds: number; points: MonitorChartPoint[] }>(
      `${API_ROOT}/monitors/${id}/results?range=${range}`,
    ),

  monitorChecks: (id: number) =>
    request<{ data: MonitorCheck[] }>(`${API_ROOT}/monitors/${id}/checks`),

  listIncidents: (params: { status?: string; monitor_id?: number } = {}) => {
    const query = new URLSearchParams();
    if (params.status) query.set("status", params.status);
    if (params.monitor_id) query.set("monitor_id", String(params.monitor_id));
    const suffix = query.toString() ? `?${query.toString()}` : "";

    return request<Paginated<Incident>>(`${API_ROOT}/incidents${suffix}`);
  },

  listChannels: () => request<Paginated<NotificationChannel>>(`${API_ROOT}/channels`),

  addDiscordChannel: (input: { name: string; webhook_url: string }) =>
    request<{ channel: NotificationChannel }>(`${API_ROOT}/channels/discord`, {
      method: "POST",
      body: input,
    }),

  telegramLink: () =>
    request<TelegramLink>(`${API_ROOT}/channels/telegram/link`, { method: "POST" }),

  testChannel: (id: number) =>
    request<{ message: string }>(`${API_ROOT}/channels/${id}/test`, { method: "POST" }),

  deleteChannel: (id: number) =>
    request<{ message: string }>(`${API_ROOT}/channels/${id}`, { method: "DELETE" }),

  listStatusPages: () => request<Paginated<StatusPage>>(`${API_ROOT}/status-pages`),

  createStatusPage: (input: Record<string, unknown>) =>
    request<{ status_page: StatusPage }>(`${API_ROOT}/status-pages`, {
      method: "POST",
      body: input,
    }),

  updateStatusPage: (id: number, input: Record<string, unknown>) =>
    request<{ status_page: StatusPage }>(`${API_ROOT}/status-pages/${id}`, {
      method: "PATCH",
      body: input,
    }),

  deleteStatusPage: (id: number) =>
    request<{ message: string }>(`${API_ROOT}/status-pages/${id}`, { method: "DELETE" }),

  setMonitorChannels: (id: number, channelIds: number[]) =>
    request<{ monitor: Monitor }>(`${API_ROOT}/monitors/${id}/channels`, {
      method: "PUT",
      body: { channel_ids: channelIds },
    }),
};
