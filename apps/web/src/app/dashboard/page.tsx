"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { FormEvent, useCallback, useEffect, useState } from "react";
import { api, ApiRequestError } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import type { Monitor, MonitorStatus } from "@/lib/types";

const STATUS_STYLES: Record<MonitorStatus, string> = {
  up: "bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300",
  down: "bg-red-100 text-red-700 dark:bg-red-950 dark:text-red-300",
  pending: "bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400",
  paused: "bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300",
};

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

function MonitorForm({
  onCreated,
  onCancel,
}: {
  onCreated: () => void;
  onCancel: () => void;
}) {
  const [name, setName] = useState("");
  const [url, setUrl] = useState("");
  const [interval, setInterval] = useState(300);
  const [keyword, setKeyword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [busy, setBusy] = useState(false);

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    setFieldErrors({});
    setBusy(true);
    try {
      await api.createMonitor({
        name,
        url,
        method: "GET",
        interval_seconds: interval,
        timeout_seconds: 10,
        expected_status: "200-399",
        alert_after_failures: 2,
        follow_redirects: true,
        ...(keyword
          ? { keyword, keyword_mode: "must_contain" }
          : {}),
      });
      onCreated();
    } catch (err) {
      if (err instanceof ApiRequestError) {
        setError(err.message);
        setFieldErrors(err.errors);
      } else {
        setError("Could not create the monitor.");
      }
    } finally {
      setBusy(false);
    }
  }

  return (
    <form
      onSubmit={onSubmit}
      className="rounded-xl border border-black/[.08] bg-white p-6 dark:border-white/[.145] dark:bg-black"
    >
      <h2 className="font-semibold">New monitor</h2>

      {error && (
        <div className="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">
          {error}
        </div>
      )}

      <div className="mt-4 grid gap-4 sm:grid-cols-2">
        <label className="block text-sm font-medium">
          Name
          <input
            required
            value={name}
            onChange={(e) => setName(e.target.value)}
            className="mt-1 w-full rounded-md border border-black/[.08] bg-transparent px-3 py-2 outline-none focus:border-black/30 dark:border-white/[.145]"
          />
          {fieldErrors.name?.[0] && (
            <span className="mt-1 block text-xs text-red-600">
              {fieldErrors.name[0]}
            </span>
          )}
        </label>

        <label className="block text-sm font-medium">
          URL
          <input
            required
            type="url"
            placeholder="https://example.com/health"
            value={url}
            onChange={(e) => setUrl(e.target.value)}
            className="mt-1 w-full rounded-md border border-black/[.08] bg-transparent px-3 py-2 outline-none focus:border-black/30 dark:border-white/[.145]"
          />
          {fieldErrors.url?.[0] && (
            <span className="mt-1 block text-xs text-red-600">
              {fieldErrors.url[0]}
            </span>
          )}
        </label>

        <label className="block text-sm font-medium">
          Check interval
          <select
            value={interval}
            onChange={(e) => setInterval(Number(e.target.value))}
            className="mt-1 w-full rounded-md border border-black/[.08] bg-transparent px-3 py-2 outline-none focus:border-black/30 dark:border-white/[.145]"
          >
            <option value={60}>Every minute</option>
            <option value={300}>Every 5 minutes</option>
            <option value={900}>Every 15 minutes</option>
          </select>
        </label>

        <label className="block text-sm font-medium">
          Keyword (optional)
          <input
            value={keyword}
            placeholder="must appear in body"
            onChange={(e) => setKeyword(e.target.value)}
            className="mt-1 w-full rounded-md border border-black/[.08] bg-transparent px-3 py-2 outline-none focus:border-black/30 dark:border-white/[.145]"
          />
          {fieldErrors.keyword_mode?.[0] && (
            <span className="mt-1 block text-xs text-red-600">
              {fieldErrors.keyword_mode[0]}
            </span>
          )}
        </label>
      </div>

      <div className="mt-5 flex gap-3">
        <button
          type="submit"
          disabled={busy}
          className="rounded-full bg-foreground px-5 py-2 text-sm font-medium text-background transition-colors hover:bg-[#383838] disabled:opacity-60 dark:hover:bg-[#ccc]"
        >
          {busy ? "Adding…" : "Add monitor"}
        </button>
        <button
          type="button"
          onClick={onCancel}
          className="rounded-full border border-black/[.08] px-5 py-2 text-sm font-medium transition-colors hover:bg-black/[.04] dark:border-white/[.145] dark:hover:bg-[#1a1a1a]"
        >
          Cancel
        </button>
      </div>
    </form>
  );
}

function MonitorRow({
  monitor,
  onChanged,
}: {
  monitor: Monitor;
  onChanged: () => void;
}) {
  const [busy, setBusy] = useState(false);

  async function action(fn: () => Promise<unknown>) {
    setBusy(true);
    try {
      await fn();
      onChanged();
    } catch {
      // The dashboard reload will surface any persistent problem.
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="flex flex-wrap items-center gap-4 rounded-xl border border-black/[.08] bg-white p-4 dark:border-white/[.145] dark:bg-black">
      <div className="min-w-0 flex-1">
        <div className="flex items-center gap-3">
          <span className="truncate font-medium">{monitor.name}</span>
          <StatusBadge status={monitor.current_status} />
        </div>
        <a
          href={monitor.url}
          target="_blank"
          rel="noreferrer"
          className="mt-0.5 block truncate text-sm text-zinc-500 hover:underline"
        >
          {monitor.url}
        </a>
        <div className="mt-1 text-xs text-zinc-400">
          every {Math.round(monitor.interval_seconds / 60)}m
          {monitor.last_response_ms !== null &&
            ` · ${monitor.last_response_ms}ms`}
          {monitor.last_checked_at &&
            ` · checked ${new Date(monitor.last_checked_at).toLocaleTimeString()}`}
        </div>
      </div>

      <div className="flex gap-2 text-sm">
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
              monitor.is_paused
                ? api.resumeMonitor(monitor.id)
                : api.pauseMonitor(monitor.id),
            )
          }
          className="rounded-full border border-black/[.08] px-3 py-1.5 transition-colors hover:bg-black/[.04] disabled:opacity-50 dark:border-white/[.145] dark:hover:bg-[#1a1a1a]"
        >
          {monitor.is_paused ? "Resume" : "Pause"}
        </button>
        <button
          disabled={busy}
          onClick={() => {
            if (confirm(`Delete "${monitor.name}"? This cannot be undone.`)) {
              action(() => api.deleteMonitor(monitor.id));
            }
          }}
          className="rounded-full border border-red-200 px-3 py-1.5 text-red-600 transition-colors hover:bg-red-50 disabled:opacity-50 dark:border-red-900 dark:hover:bg-red-950"
        >
          Delete
        </button>
      </div>
    </div>
  );
}

export default function DashboardPage() {
  const router = useRouter();
  const { user, loading, signOut } = useAuth();
  const [monitors, setMonitors] = useState<Monitor[]>([]);
  const [listLoading, setListLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);

  const load = useCallback(async () => {
    try {
      const res = await api.listMonitors();
      setLoadError(null);
      setMonitors(res.data);
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

  if (loading || !user) {
    return (
      <div className="flex flex-1 items-center justify-center text-zinc-500">
        Loading…
      </div>
    );
  }

  return (
    <div className="mx-auto w-full max-w-4xl flex-1 px-6 py-8">
      <header className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-semibold">Your monitors</h1>
          <p className="text-sm text-zinc-500">
            Signed in as {user.email} · {monitors.length}/10 monitors
          </p>
        </div>
        <div className="flex gap-3">
          <button
            onClick={() => setShowForm((v) => !v)}
            className="rounded-full bg-foreground px-5 py-2 text-sm font-medium text-background transition-colors hover:bg-[#383838] dark:hover:bg-[#ccc]"
          >
            {showForm ? "Close" : "+ New monitor"}
          </button>
          <button
            onClick={async () => {
              await signOut();
              router.push("/login");
            }}
            className="rounded-full border border-black/[.08] px-5 py-2 text-sm font-medium transition-colors hover:bg-black/[.04] dark:border-white/[.145] dark:hover:bg-[#1a1a1a]"
          >
            Sign out
          </button>
        </div>
      </header>

      <div className="mt-6 space-y-4">
        {showForm && (
          <MonitorForm
            onCreated={() => {
              setShowForm(false);
              void load();
            }}
            onCancel={() => setShowForm(false)}
          />
        )}

        {listLoading && <p className="text-zinc-500">Loading monitors…</p>}
        {loadError && <p className="text-red-600">{loadError}</p>}

        {!listLoading && !loadError && monitors.length === 0 && (
          <div className="rounded-xl border border-dashed border-black/[.15] p-10 text-center dark:border-white/[.2]">
            <p className="font-medium">No monitors yet</p>
            <p className="mt-1 text-sm text-zinc-500">
              Add your first site and PupWatch will start checking it within a
              minute.
            </p>
          </div>
        )}

        {monitors.map((m) => (
          <MonitorRow key={m.id} monitor={m} onChanged={load} />
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