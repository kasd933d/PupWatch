"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useCallback, useEffect, useState } from "react";
import { PupMascot } from "@/components/mascot/pup-mascot";
import { api, ApiRequestError } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import { moodForDashboard } from "@/lib/mood";
import type { Monitor, MonitorStatus } from "@/lib/types";

const STATUS_STYLES: Record<MonitorStatus, string> = {
  up: "bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300",
  down: "bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300",
  pending: "bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400",
  paused: "bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300",
};

const FILTERS = ["all", "up", "down", "paused"] as const;
type Filter = (typeof FILTERS)[number];

function StatusBadge({ status }: { status: MonitorStatus }) {
  return (
    <span
      className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ${STATUS_STYLES[status]}`}
    >
      <span className="h-1.5 w-1.5 rounded-full bg-current opacity-70" />
      {status}
    </span>
  );
}

function uptimeLabel(monitor: Monitor): string {
  const summary = monitor.uptime;
  if (!summary || summary.total === 0 || summary.uptime === null) return "—";
  return `${summary.uptime.toFixed(summary.uptime === 100 ? 0 : 1)}%`;
}

function MonitorCard({ monitor, onChanged }: { monitor: Monitor; onChanged: () => void }) {
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function action(fn: () => Promise<unknown>) {
    setBusy(true);
    setError(null);
    try {
      await fn();
      onChanged();
    } catch (err) {
      setError(err instanceof ApiRequestError ? err.message : "That action failed.");
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="flex flex-wrap items-center gap-4 rounded-xl border border-black/[.08] bg-white p-4 dark:border-white/[.145] dark:bg-black">
      <PupMascot mood={moodForDashboard([monitor])} size={56} />

      <div className="min-w-0 flex-1">
        <div className="flex items-center gap-3">
          <Link
            href={`/monitors/${monitor.id}`}
            className="truncate font-medium underline-offset-2 hover:underline"
          >
            {monitor.name}
          </Link>
          <StatusBadge status={monitor.current_status} />
        </div>
        <a
          href={monitor.url}
          target="_blank"
          rel="noreferrer"
          className="mt-0.5 block truncate text-sm text-zinc-500 hover:underline"
        >
          {monitor.host}
        </a>
        <div className="mt-1 text-xs text-zinc-400">
          24h uptime {uptimeLabel(monitor)}
          {monitor.last_response_ms !== null && ` · ${monitor.last_response_ms}ms`}
          {monitor.last_checked_at &&
            ` · checked ${new Date(monitor.last_checked_at).toLocaleTimeString()}`}
          {monitor.is_paused && " · paused"}
        </div>
        {error && <p role="alert" className="mt-1 text-xs text-red-600">{error}</p>}
      </div>

      <div className="flex gap-2 text-sm">
        <Link
          href={`/monitors/${monitor.id}`}
          className="rounded-full border border-black/[.08] px-3 py-1.5 transition-colors hover:bg-black/[.04] dark:border-white/[.145] dark:hover:bg-[#1a1a1a]"
        >
          Details
        </Link>
        <button
          disabled={busy}
          onClick={() => action(() => api.checkNow(monitor.id))}
          className="rounded-full border border-black/[.08] px-3 py-1.5 transition-colors hover:bg-black/[.04] disabled:opacity-50 dark:border-white/[.145] dark:hover:bg-[#1a1a1a]"
        >
          Check now
        </button>
        <button
          disabled={busy}
          onClick={() =>
            action(() =>
              monitor.is_paused ? api.resumeMonitor(monitor.id) : api.pauseMonitor(monitor.id),
            )
          }
          className="rounded-full border border-black/[.08] px-3 py-1.5 transition-colors hover:bg-black/[.04] disabled:opacity-50 dark:border-white/[.145] dark:hover:bg-[#1a1a1a]"
        >
          {monitor.is_paused ? "Resume" : "Pause"}
        </button>
      </div>
    </div>
  );
}

export default function DashboardPage() {
  const router = useRouter();
  const { user, loading, signOut } = useAuth();
  const [monitors, setMonitors] = useState<Monitor[]>([]);
  const [openIncidents, setOpenIncidents] = useState(0);
  const [listLoading, setListLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [filter, setFilter] = useState<Filter>("all");
  const [search, setSearch] = useState("");

  const load = useCallback(async () => {
    try {
      const [monitorsRes, incidentsRes] = await Promise.all([
        api.listMonitors(),
        api.listIncidents({ status: "open" }),
      ]);
      setLoadError(null);
      setMonitors(monitorsRes.data);
      setOpenIncidents(incidentsRes.meta?.total ?? incidentsRes.data.length);
    } catch (err) {
      if (err instanceof ApiRequestError && err.status === 401) {
        router.push("/login");
        return;
      }
      setLoadError("Could not load monitors.");
    } finally {
      setListLoading(false);
    }
  }, [router]);

  useEffect(() => {
    if (loading) return;
    if (!user) {
      router.push("/login");
      return;
    }
    // Kick off the fetch on a microtask so the effect body itself never
    // sets state synchronously (react-hooks/set-state-in-effect).
    Promise.resolve().then(() => load());
  }, [loading, user, router, load]);

  useEffect(() => {
    const timer = setInterval(() => {
      void load();
    }, 30_000);

    return () => clearInterval(timer);
  }, [load]);

  if (loading || !user) {
    return (
      <div className="flex flex-1 items-center justify-center text-zinc-500">Loading…</div>
    );
  }

  const up = monitors.filter((m) => m.current_status === "up").length;
  const down = monitors.filter((m) => m.current_status === "down").length;
  const paused = monitors.filter((m) => m.is_paused).length;
  const mood = moodForDashboard(monitors);

  const query = search.trim().toLowerCase();
  const visible = monitors.filter((monitor) => {
    const matchesFilter = filter === "all" || monitor.current_status === filter;
    const matchesSearch =
      query === "" ||
      monitor.name.toLowerCase().includes(query) ||
      monitor.host.toLowerCase().includes(query);

    return matchesFilter && matchesSearch;
  });

  return (
    <div className="mx-auto w-full max-w-5xl flex-1 px-6 py-8">
      <header className="flex flex-wrap items-start justify-between gap-4">
        <div className="flex items-center gap-3">
          <PupMascot mood={mood} size={88} />
          <div>
            <h1 className="text-2xl font-semibold">
              {down > 0 ? "Some sites need you" : "Your monitors"}
            </h1>
            <p className="text-sm text-zinc-500">
              {user.email} · {monitors.length}/10 monitors
              {openIncidents > 0 && ` · ${openIncidents} open incident${openIncidents === 1 ? "" : "s"}`}
            </p>
          </div>
        </div>
        <div className="flex flex-wrap gap-2 text-sm">
          <Link
            href="/incidents"
            className="rounded-full border border-black/[.08] px-4 py-2 font-medium transition-colors hover:bg-black/[.04] dark:border-white/[.145] dark:hover:bg-[#1a1a1a]"
          >
            Incidents
          </Link>
          <Link
            href="/status-pages"
            className="rounded-full border border-black/[.08] px-4 py-2 font-medium transition-colors hover:bg-black/[.04] dark:border-white/[.145] dark:hover:bg-[#1a1a1a]"
          >
            Status pages
          </Link>
          <Link
            href="/channels"
            className="rounded-full border border-black/[.08] px-4 py-2 font-medium transition-colors hover:bg-black/[.04] dark:border-white/[.145] dark:hover:bg-[#1a1a1a]"
          >
            Channels
          </Link>
          <Link
            href="/monitors/new"
            className="rounded-full bg-foreground px-4 py-2 font-medium text-background transition-colors hover:bg-[#383838] dark:hover:bg-[#ccc]"
          >
            + New monitor
          </Link>
          <Link
            href="/settings"
            className="rounded-full border border-black/[.08] px-4 py-2 font-medium transition-colors hover:bg-black/[.04] dark:border-white/[.145] dark:hover:bg-[#1a1a1a]"
          >
            Settings
          </Link>
          <button
            onClick={async () => {
              await signOut();
              router.push("/login");
            }}
            className="rounded-full border border-black/[.08] px-4 py-2 font-medium transition-colors hover:bg-black/[.04] dark:border-white/[.145] dark:hover:bg-[#1a1a1a]"
          >
            Sign out
          </button>
        </div>
      </header>

      <section className="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
        {[
          { label: "Monitors", value: monitors.length },
          { label: "Up", value: up },
          { label: "Down", value: down },
          { label: "Paused", value: paused },
        ].map((stat) => (
          <div
            key={stat.label}
            className="rounded-xl border border-black/[.08] bg-white p-4 dark:border-white/[.145] dark:bg-black"
          >
            <p className="text-xs uppercase tracking-wide text-zinc-500">{stat.label}</p>
            <p className="mt-1 text-2xl font-semibold">{stat.value}</p>
          </div>
        ))}
      </section>

      <div className="mt-6 flex flex-wrap items-center gap-3">
        <input
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          placeholder="Search name or host…"
          className="w-full max-w-xs rounded-full border border-black/[.08] bg-transparent px-4 py-2 text-sm outline-none focus:border-black/30 dark:border-white/[.145]"
        />
        <div className="flex rounded-full bg-black/[.04] p-1 text-sm dark:bg-white/[.06]">
          {FILTERS.map((option) => (
            <button
              key={option}
              onClick={() => setFilter(option)}
              className={`rounded-full px-3 py-1 capitalize transition-colors ${
                filter === option
                  ? "bg-white font-medium shadow-sm dark:bg-zinc-800"
                  : "text-zinc-500"
              }`}
            >
              {option}
            </button>
          ))}
        </div>
      </div>

      <div className="mt-4 space-y-4">
        {listLoading && <p className="text-zinc-500">Loading monitors…</p>}
        {loadError && <p className="text-red-600">{loadError}</p>}

        {!listLoading && !loadError && monitors.length === 0 && (
          <div className="flex flex-col items-center rounded-xl border border-dashed border-black/[.15] p-10 text-center dark:border-white/[.2]">
            <PupMascot mood="curious" size={120} />
            <p className="mt-3 font-medium">No monitors yet</p>
            <p className="mt-1 text-sm text-zinc-500">
              Add your first site and the pup starts watching it within a minute.
            </p>
            <Link
              href="/monitors/new"
              className="mt-4 rounded-full bg-foreground px-5 py-2 text-sm font-medium text-background transition-colors hover:bg-[#383838] dark:hover:bg-[#ccc]"
            >
              Add a monitor
            </Link>
          </div>
        )}

        {!listLoading && monitors.length > 0 && visible.length === 0 && (
          <p className="py-6 text-center text-sm text-zinc-500">
            No monitors match this filter.
          </p>
        )}

        {visible.map((monitor) => (
          <MonitorCard key={monitor.id} monitor={monitor} onChanged={load} />
        ))}
      </div>

      <footer className="mt-10 text-center text-sm text-zinc-400">
        <Link href="/" className="hover:underline">
          ← PupWatch home
        </Link>
      </footer>
    </div>
  );
}
