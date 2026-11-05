"use client";

import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import { useCallback, useEffect, useState } from "react";
import { PupMascot } from "@/components/mascot/pup-mascot";
import { ResponseChart } from "@/components/response-chart";
import { api, ApiRequestError, type MonitorChartPoint, type MonitorCheck } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import { MOOD_LABEL, moodForMonitor } from "@/lib/mood";
import type { Incident, Monitor, NotificationChannel, UptimeSummary } from "@/lib/types";

type Range = "24h" | "7d" | "30d";

const RANGES: { value: Range; label: string }[] = [
  { value: "24h", label: "24 hours" },
  { value: "7d", label: "7 days" },
  { value: "30d", label: "30 days" },
];

const STATUS_STYLES: Record<string, string> = {
  up: "bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300",
  down: "bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300",
  paused: "bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300",
  pending: "bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300",
};

function formatDuration(seconds: number | null): string {
  if (seconds === null) return "ongoing";
  if (seconds < 60) return `${seconds}s`;
  const minutes = Math.floor(seconds / 60);
  if (minutes < 60) return `${minutes}m`;
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours}h ${minutes % 60}m`;
  return `${Math.floor(hours / 24)}d ${hours % 24}h`;
}

function uptimeLabel(summary: UptimeSummary | undefined): string {
  if (!summary || summary.total === 0 || summary.uptime === null) return "—";
  return `${summary.uptime.toFixed(summary.uptime === 100 ? 0 : 2)}%`;
}

export function MonitorDetailClient() {
  const params = useParams<{ id: string }>();
  const id = Number(params.id);
  const router = useRouter();
  const { user, loading } = useAuth();

  const [monitor, setMonitor] = useState<Monitor | null>(null);
  const [checks, setChecks] = useState<MonitorCheck[]>([]);
  const [points, setPoints] = useState<MonitorChartPoint[]>([]);
  const [incidents, setIncidents] = useState<Incident[]>([]);
  const [channels, setChannels] = useState<NotificationChannel[]>([]);
  const [range, setRange] = useState<Range>("24h");
  const [ready, setReady] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    try {
      const [monitorRes, checksRes, resultsRes, incidentsRes, channelsRes] = await Promise.all([
        api.getMonitor(id),
        api.monitorChecks(id),
        api.monitorResults(id, range),
        api.listIncidents({ monitor_id: id }),
        api.listChannels(),
      ]);
      setMonitor(monitorRes.monitor);
      setChecks(checksRes.data);
      setPoints(resultsRes.points);
      setIncidents(incidentsRes.data);
      setChannels(channelsRes.data);
      setLoadError(null);
    } catch (err) {
      if (err instanceof ApiRequestError && err.status === 401) {
        router.push("/login");
        return;
      }
      setLoadError(
        err instanceof ApiRequestError && err.status === 404
          ? "This monitor does not exist or was deleted."
          : "Could not load this monitor.",
      );
    } finally {
      setReady(true);
    }
  }, [id, range, router]);

  useEffect(() => {
    if (loading) return;
    if (!user) {
      router.push("/login");
      return;
    }
    Promise.resolve().then(() => load());
  }, [loading, user, router, load]);

  useEffect(() => {
    const timer = setInterval(() => {
      void load();
    }, 30_000);

    return () => clearInterval(timer);
  }, [load]);

  async function runAction(action: "check" | "pause" | "resume" | "delete") {
    setBusy(true);
    setActionError(null);
    try {
      if (action === "check") await api.checkNow(id);
      if (action === "pause") await api.pauseMonitor(id);
      if (action === "resume") await api.resumeMonitor(id);
      if (action === "delete") {
        await api.deleteMonitor(id);
        router.push("/dashboard");
        return;
      }
      await load();
    } catch (err) {
      setActionError(
        err instanceof ApiRequestError ? err.message : "That action could not be completed.",
      );
    } finally {
      setBusy(false);
    }
  }

  async function toggleChannel(channelId: number) {
    if (!monitor) return;
    const current = monitor.channel_ids ?? [];
    const next = current.includes(channelId)
      ? current.filter((value) => value !== channelId)
      : [...current, channelId];

    setActionError(null);
    try {
      const res = await api.setMonitorChannels(id, next);
      setMonitor(res.monitor);
    } catch (err) {
      setActionError(
        err instanceof ApiRequestError ? err.message : "Could not update alert channels.",
      );
    }
  }

  if (loading || !user) {
    return (
      <div className="flex flex-1 items-center justify-center text-zinc-500">Loading…</div>
    );
  }

  if (!ready) {
    return (
      <div className="mx-auto w-full max-w-5xl flex-1 px-6 py-8">
        <p className="text-zinc-500">Loading monitor…</p>
      </div>
    );
  }

  if (loadError || !monitor) {
    return (
      <div className="mx-auto w-full max-w-5xl flex-1 px-6 py-8">
        <p className="text-red-600">{loadError ?? "Monitor unavailable."}</p>
        <Link href="/dashboard" className="mt-4 inline-block text-sm font-medium underline">
          ← Back to dashboard
        </Link>
      </div>
    );
  }

  const mood = moodForMonitor(monitor);
  const ranges = monitor.uptime_ranges ?? {};

  return (
    <div className="mx-auto w-full max-w-5xl flex-1 px-6 py-8">
      <header className="flex flex-wrap items-start justify-between gap-4">
        <div className="flex items-center gap-4">
          <PupMascot mood={mood} size={104} />
          <div>
            <div className="flex flex-wrap items-center gap-3">
              <h1 className="text-2xl font-semibold">{monitor.name}</h1>
              <span
                className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${
                  STATUS_STYLES[monitor.current_status] ?? STATUS_STYLES.pending
                }`}
              >
                {monitor.current_status}
              </span>
            </div>
            <a
              href={monitor.url}
              target="_blank"
              rel="noreferrer"
              className="text-sm text-zinc-500 underline-offset-2 hover:underline"
            >
              {monitor.url}
            </a>
            <p className="mt-1 text-sm text-zinc-500">
              {MOOD_LABEL[mood]} · checks every {Math.round(monitor.interval_seconds / 60)} min ·
              alerts after {monitor.alert_after_failures}{" "}
              {monitor.alert_after_failures === 1 ? "failure" : "failures"}
            </p>
          </div>
        </div>

        <div className="flex flex-wrap gap-2 text-sm">
          <button
            disabled={busy}
            onClick={() => runAction("check")}
            className="rounded-full border border-black/[.08] px-4 py-2 font-medium transition-colors hover:bg-black/[.04] disabled:opacity-50 dark:border-white/[.145] dark:hover:bg-[#1a1a1a]"
          >
            Check now
          </button>
          <button
            disabled={busy}
            onClick={() => runAction(monitor.is_paused ? "resume" : "pause")}
            className="rounded-full border border-black/[.08] px-4 py-2 font-medium transition-colors hover:bg-black/[.04] disabled:opacity-50 dark:border-white/[.145] dark:hover:bg-[#1a1a1a]"
          >
            {monitor.is_paused ? "Resume" : "Pause"}
          </button>
          <Link
            href={`/monitors/${monitor.id}/edit`}
            className="rounded-full border border-black/[.08] px-4 py-2 font-medium transition-colors hover:bg-black/[.04] dark:border-white/[.145] dark:hover:bg-[#1a1a1a]"
          >
            Edit
          </Link>
          <button
            disabled={busy}
            onClick={() => {
              if (confirm(`Delete "${monitor.name}" and all of its history?`)) runAction("delete");
            }}
            className="rounded-full border border-red-200 px-4 py-2 font-medium text-red-600 transition-colors hover:bg-red-50 disabled:opacity-50 dark:border-red-900 dark:hover:bg-red-950"
          >
            Delete
          </button>
        </div>
      </header>

      {actionError && (
        <div className="mt-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">
          {actionError}
        </div>
      )}

      <section className="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
        {(["24h", "7d", "30d", "90d"] as const).map((key) => (
          <div
            key={key}
            className="rounded-xl border border-black/[.08] bg-white p-4 dark:border-white/[.145] dark:bg-black"
          >
            <p className="text-xs uppercase tracking-wide text-zinc-500">{key} uptime</p>
            <p className="mt-1 text-xl font-semibold">{uptimeLabel(ranges[key])}</p>
            <p className="text-xs text-zinc-400">
              {ranges[key]?.total ?? 0} {(ranges[key]?.total ?? 0) === 1 ? "check" : "checks"}
            </p>
          </div>
        ))}
      </section>

      <section className="mt-6 rounded-xl border border-black/[.08] bg-white p-6 dark:border-white/[.145] dark:bg-black">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 className="font-semibold">Response time</h2>
            <p className="text-sm text-zinc-500">
              Last check{" "}
              {monitor.last_checked_at
                ? new Date(monitor.last_checked_at).toLocaleString()
                : "never"}{" "}
              · {monitor.last_response_ms !== null ? `${monitor.last_response_ms} ms` : "no timing"}
            </p>
          </div>
          <div className="flex rounded-full bg-black/[.04] p-1 text-sm dark:bg-white/[.06]">
            {RANGES.map((option) => (
              <button
                key={option.value}
                onClick={() => setRange(option.value)}
                className={`rounded-full px-3 py-1 transition-colors ${
                  range === option.value
                    ? "bg-white font-medium shadow-sm dark:bg-zinc-800"
                    : "text-zinc-500"
                }`}
              >
                {option.label}
              </button>
            ))}
          </div>
        </div>

        <div className="mt-4 text-zinc-700 dark:text-zinc-200">
          <ResponseChart points={points} />
        </div>
      </section>

      <div className="mt-6 grid gap-6 lg:grid-cols-2">
        <section className="rounded-xl border border-black/[.08] bg-white p-6 dark:border-white/[.145] dark:bg-black">
          <h2 className="font-semibold">Recent checks</h2>
          {checks.length === 0 ? (
            <p className="mt-2 text-sm text-zinc-500">No checks recorded yet.</p>
          ) : (
            <div className="mt-3 overflow-x-auto">
              <table className="w-full text-left text-sm">
                <thead className="text-xs uppercase tracking-wide text-zinc-400">
                  <tr>
                    <th className="pb-2 pr-3 font-medium">Time</th>
                    <th className="pb-2 pr-3 font-medium">Status</th>
                    <th className="pb-2 pr-3 font-medium">Response</th>
                    <th className="pb-2 font-medium">Detail</th>
                  </tr>
                </thead>
                <tbody>
                  {checks.map((check) => (
                    <tr key={check.id} className="border-t border-black/[.06] dark:border-white/[.08]">
                      <td className="py-2 pr-3 whitespace-nowrap text-zinc-500">
                        {new Date(check.checked_at).toLocaleTimeString()}
                      </td>
                      <td className="py-2 pr-3">
                        {check.is_success ? (
                          <span className="text-green-700 dark:text-green-400">
                            {check.status_code}
                          </span>
                        ) : (
                          <span className="text-red-600">{check.error_type ?? "failed"}</span>
                        )}
                      </td>
                      <td className="py-2 pr-3 whitespace-nowrap">
                        {check.response_time_ms !== null ? `${check.response_time_ms} ms` : "—"}
                      </td>
                      <td
                        className="max-w-[14rem] truncate py-2 text-zinc-500"
                        title={check.error_message ?? ""}
                      >
                        {check.error_message ?? "—"}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </section>

        <section className="rounded-xl border border-black/[.08] bg-white p-6 dark:border-white/[.145] dark:bg-black">
          <div className="flex items-center justify-between">
            <h2 className="font-semibold">Incidents</h2>
            <Link href="/incidents" className="text-sm text-zinc-500 underline-offset-2 hover:underline">
              All incidents
            </Link>
          </div>
          {incidents.length === 0 ? (
            <p className="mt-2 text-sm text-zinc-500">No incidents. The pup is napping happily.</p>
          ) : (
            <ul className="mt-3 space-y-3 text-sm">
              {incidents.slice(0, 10).map((incident) => (
                <li
                  key={incident.id}
                  className="rounded-lg border border-black/[.06] p-3 dark:border-white/[.08]"
                >
                  <div className="flex items-center justify-between gap-2">
                    <span
                      className={`rounded-full px-2 py-0.5 text-xs font-medium ${
                        incident.status === "open"
                          ? "bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300"
                          : "bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300"
                      }`}
                    >
                      {incident.status === "open" ? "ongoing" : "resolved"}
                    </span>
                    <span className="text-xs text-zinc-400">
                      {new Date(incident.started_at).toLocaleString()}
                    </span>
                  </div>
                  <p className="mt-1 text-zinc-600 dark:text-zinc-300">
                    Lasted {formatDuration(incident.duration_seconds)}
                    {incident.first_error_type ? ` · ${incident.first_error_type}` : ""}
                  </p>
                  {incident.first_error_message && (
                    <p className="mt-0.5 truncate text-xs text-zinc-400" title={incident.first_error_message}>
                      {incident.first_error_message}
                    </p>
                  )}
                </li>
              ))}
            </ul>
          )}
        </section>
      </div>

      <section className="mt-6 rounded-xl border border-black/[.08] bg-white p-6 dark:border-white/[.145] dark:bg-black">
        <div className="flex items-center justify-between">
          <h2 className="font-semibold">Alert channels</h2>
          <Link href="/channels" className="text-sm text-zinc-500 underline-offset-2 hover:underline">
            Manage channels
          </Link>
        </div>
        {channels.length === 0 ? (
          <p className="mt-2 text-sm text-zinc-500">
            No channels yet. Add one to be woken up when this site goes down.
          </p>
        ) : (
          <ul className="mt-3 grid gap-2 sm:grid-cols-2">
            {channels.map((channel) => {
              const linked = (monitor.channel_ids ?? []).includes(channel.id);
              return (
                <li key={channel.id}>
                  <label className="flex cursor-pointer items-center gap-3 rounded-lg border border-black/[.06] px-3 py-2 text-sm dark:border-white/[.08]">
                    <input
                      type="checkbox"
                      checked={linked}
                      onChange={() => toggleChannel(channel.id)}
                      className="h-4 w-4"
                    />
                    <span className="font-medium">{channel.name}</span>
                    <span className="text-xs text-zinc-400">{channel.type}</span>
                    {channel.status === "needs_attention" && (
                      <span className="ml-auto text-xs text-amber-600">needs attention</span>
                    )}
                  </label>
                </li>
              );
            })}
          </ul>
        )}
      </section>

      <div className="mt-6">
        <Link href="/dashboard" className="text-sm font-medium underline">
          ← Back to dashboard
        </Link>
      </div>
    </div>
  );
}
