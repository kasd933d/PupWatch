export interface User {
  id: number;
  name: string;
  email: string;
  timezone: string;
  created_at: string;
}

export type MonitorStatus = "pending" | "up" | "down" | "paused";

export interface Monitor {
  id: number;
  name: string;
  url: string;
  host: string;
  method: "GET" | "HEAD";
  interval_seconds: number;
  timeout_seconds: number;
  expected_status: string;
  keyword: string | null;
  keyword_mode: "must_contain" | "must_not_contain" | null;
  follow_redirects: boolean;
  custom_headers: Record<string, string> | null;
  custom_header_keys: string[];
  alert_after_failures: number;
  is_paused: boolean;
  current_status: MonitorStatus;
  consecutive_failures: number;
  last_checked_at: string | null;
  last_response_ms: number | null;
  next_check_at: string | null;
  created_at: string;
}

export type ChannelType = "telegram" | "discord" | "email";

export interface NotificationChannel {
  id: number;
  type: ChannelType;
  name: string;
  status: "active" | "needs_attention";
  last_used_at: string | null;
  monitor_ids?: number[];
  created_at: string;
}

export interface TelegramLink {
  code: string;
  expires_at: string;
  bot_username: string;
  deep_link: string | null;
  instructions: string;
}

export interface Incident {
  id: number;
  monitor_id: number;
  monitor_name?: string;
  monitor_host?: string;
  started_at: string;
  ended_at: string | null;
  duration_seconds: number | null;
  first_error_type: string | null;
  first_error_message: string | null;
  status: "open" | "resolved";
}

export interface ApiError {
  message: string;
  errors?: Record<string, string[]>;
}