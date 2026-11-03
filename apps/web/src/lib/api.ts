import type { ApiError, Monitor, User } from "./types";

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
  meta: unknown;
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

  listMonitors: () => request<Paginated<Monitor>>(`${API_ROOT}/monitors`),

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
};
