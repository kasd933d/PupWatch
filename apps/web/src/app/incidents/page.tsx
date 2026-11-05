"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useCallback, useEffect, useState } from "react";
import { api, ApiRequestError } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import type { Incident } from "@/lib/types";

const FILTERS = [
  { value: "", label: "All" },
  { value: "open", label: "Ongoing" },
  { value: "resolved", label: "Resolved" },
] as const;

function formatDuration(seconds: number | null): string {
  if (seconds === null) return "ongoing";
  if (seconds < 60) return `${seconds}s`;
  const minutes = Math.floor(seconds / 60);
  if (minutes < 60) return `${minutes}m`;
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours}h ${minutes % 60}m`;
  return `${Math.floor(hours / 24)}d ${hours % 24}h`;
}

export default function IncidentsPage() {
  const router = useRouter();
  const { user, loading } = useAuth();
  const [status, setStatus] = useState<string>("");
  const [incidents, setIncidents] = useState<Incident[]>([]);
  const [listLoading, setListLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setListLoading(true);
    try {
      const res = await api.listIncidents(status ? { status } : {});
      setIncidents(res.data);
      setError(null);
    } catch (err) {
      if (err instanceof ApiRequestError && err.status === 401) {
        router.push("/login");
        return;
      }
      setError("Could not load incidents.");
    } finally {
      setListLoading(false);
    }
  }, [status, router]);

  useEffect(() => {
    if (loading) return;
    if (!user) {
      router.push("/login");
      return;
    }
    Promise.resolve().then(() => load());
  }, [loading, user, router, load]);

  if (loading || !user) {
    return (
      <div className="flex flex-1 items-center justify-center text-zinc-500">Loading…</div>
    );
  }

  return (
    <div className="mx-auto w-full max-w-4xl flex-1 px-6 py-8">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold">Incidents</h1>
          <p className="text-sm text-zinc-500">Every outage PupWatch has seen, newest first.</p>
        </div>
        <Link
          href="/dashboard"
          className="rounded-full border border-black/[.08] px-5 py-2 text-sm font-medium transition-colors hover:bg-black/[.04] dark:border-white/[.145] dark:hover:bg-[#1a1a1a]"
        >
          ← Dashboard
        </Link>
      </header>

      <div className="mt-6 flex rounded-full bg-black/[.04] p-1 text-sm dark:bg-white/[.06]">
        {FILTERS.map((filter) => (
          <button
            key={filter.value}
            onClick={() => setStatus(filter.value)}
            className={`rounded-full px-3 py-1 transition-colors ${
              status === filter.value
                ? "bg-white font-medium shadow-sm dark:bg-zinc-800"
                : "text-zinc-500"
            }`}
          >
            {filter.label}
          </button>
        ))}
      </div>

      <div className="mt-4 space-y-3">
        {listLoading && <p className="text-zinc-500">Loading incidents…</p>}
        {error && <p className="text-red-600">{error}</p>}

        {!listLoading && !error && incidents.length === 0 && (
          <div className="rounded-xl border border-dashed border-black/[.15] p-10 text-center dark:border-white/[.2]">
            <p className="font-medium">No incidents</p>
            <p className="mt-1 text-sm text-zinc-500">
              Nothing has gone down in this view. The pup is proud of you.
            </p>
          </div>
        )}

        {incidents.map((incident) => (
          <div
            key={incident.id}
            className="rounded-xl border border-black/[.08] bg-white p-4 dark:border-white/[.145] dark:bg-black"
          >
            <div className="flex flex-wrap items-center gap-3">
              <Link
                href={`/monitors/${incident.monitor_id}`}
                className="font-medium underline-offset-2 hover:underline"
              >
                {incident.monitor_name ?? incident.monitor_host ?? `Monitor #${incident.monitor_id}`}
              </Link>
              <span
                className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${
                  incident.status === "open"
                    ? "bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300"
                    : "bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300"
                }`}
              >
                {incident.status === "open" ? "ongoing" : "resolved"}
              </span>
              <span className="text-xs text-zinc-400">
                started {new Date(incident.started_at).toLocaleString()}
              </span>
              <span className="ml-auto text-sm text-zinc-500">
                {formatDuration(incident.duration_seconds)}
              </span>
            </div>

            <p className="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
              {incident.first_error_type ?? "unknown error"}
              {incident.first_error_message ? ` — ${incident.first_error_message}` : ""}
            </p>
          </div>
        ))}
      </div>
    </div>
  );
}
