"use client";

import { FormEvent, useState } from "react";
import { api, ApiRequestError } from "@/lib/api";
import type { Monitor } from "@/lib/types";

interface HeaderRow {
  key: string;
  value: string;
}

const INTERVALS = [
  { value: 60, label: "Every minute" },
  { value: 300, label: "Every 5 minutes" },
  { value: 900, label: "Every 15 minutes" },
];

/**
 * Create/edit form shared by /monitors/new and /monitors/[id]/edit (PRD §4.2).
 */
export function MonitorForm({
  monitor,
  onSaved,
  onCancel,
}: {
  monitor?: Monitor;
  onSaved: (monitor: Monitor) => void;
  onCancel?: () => void;
}) {
  const [name, setName] = useState(monitor?.name ?? "");
  const [url, setUrl] = useState(monitor?.url ?? "");
  const [method, setMethod] = useState<"GET" | "HEAD">(monitor?.method ?? "GET");
  const [interval, setInterval] = useState(monitor?.interval_seconds ?? 300);
  const [timeout, setTimeoutSeconds] = useState(monitor?.timeout_seconds ?? 10);
  const [expectedStatus, setExpectedStatus] = useState(monitor?.expected_status ?? "200-399");
  const [keyword, setKeyword] = useState(monitor?.keyword ?? "");
  const [keywordMode, setKeywordMode] = useState<"must_contain" | "must_not_contain">(
    monitor?.keyword_mode ?? "must_contain",
  );
  const [followRedirects, setFollowRedirects] = useState(monitor?.follow_redirects ?? true);
  const [alertAfter, setAlertAfter] = useState(monitor?.alert_after_failures ?? 2);
  const [headers, setHeaders] = useState<HeaderRow[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [busy, setBusy] = useState(false);

  function addHeader() {
    if (headers.length >= 5) return;
    setHeaders((rows) => [...rows, { key: "", value: "" }]);
  }

  function updateHeader(index: number, patch: Partial<HeaderRow>) {
    setHeaders((rows) => rows.map((row, i) => (i === index ? { ...row, ...patch } : row)));
  }

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    setFieldErrors({});

    const customHeaders: Record<string, string> = {};
    for (const row of headers) {
      if (row.key.trim() !== "") customHeaders[row.key.trim()] = row.value;
    }

    const payload: Record<string, unknown> = {
      name,
      url: url.trim(),
      method,
      interval_seconds: interval,
      timeout_seconds: timeout,
      expected_status: expectedStatus,
      keyword: keyword.trim() === "" ? null : keyword.trim(),
      keyword_mode: keyword.trim() === "" ? null : keywordMode,
      follow_redirects: followRedirects,
      alert_after_failures: alertAfter,
    };

    if (Object.keys(customHeaders).length > 0) {
      payload.custom_headers = customHeaders;
    }

    setBusy(true);
    try {
      const res = monitor
        ? await api.updateMonitor(monitor.id, payload)
        : await api.createMonitor(payload);
      onSaved(res.monitor);
    } catch (err) {
      if (err instanceof ApiRequestError) {
        setError(err.message);
        setFieldErrors(err.errors);
      } else {
        setError(monitor ? "Could not save the monitor." : "Could not create the monitor.");
      }
    } finally {
      setBusy(false);
    }
  }

  const fieldError = (key: string) => fieldErrors[key]?.[0];

  return (
    <form
      onSubmit={onSubmit}
      className="rounded-xl border border-black/[.08] bg-white p-6 dark:border-white/[.145] dark:bg-black"
    >
      {error && (
        <div role="alert" className="mb-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950 dark:text-red-300">
          {error}
        </div>
      )}

      <div className="grid gap-4 sm:grid-cols-2">
        <label className="block text-sm font-medium">
          Name
          <input
            required
            maxLength={100}
            value={name}
            onChange={(e) => setName(e.target.value)}
            className="mt-1 w-full rounded-md border border-black/[.08] bg-transparent px-3 py-2 outline-none focus:border-black/30 dark:border-white/[.145]"
          />
          {fieldError("name") && <span className="mt-1 block text-xs text-red-600">{fieldError("name")}</span>}
        </label>

        <label className="block text-sm font-medium">
          URL
          <input
            required
            type="url"
            value={url}
            onChange={(e) => setUrl(e.target.value)}
            placeholder="https://example.com/health"
            className="mt-1 w-full rounded-md border border-black/[.08] bg-transparent px-3 py-2 outline-none focus:border-black/30 dark:border-white/[.145]"
          />
          {fieldError("url") && <span className="mt-1 block text-xs text-red-600">{fieldError("url")}</span>}
        </label>

        <label className="block text-sm font-medium">
          Method
          <select
            value={method}
            onChange={(e) => setMethod(e.target.value as "GET" | "HEAD")}
            className="mt-1 w-full rounded-md border border-black/[.08] bg-transparent px-3 py-2 outline-none focus:border-black/30 dark:border-white/[.145]"
          >
            <option value="GET">GET</option>
            <option value="HEAD">HEAD (headers only)</option>
          </select>
        </label>

        <label className="block text-sm font-medium">
          Check interval
          <select
            value={interval}
            onChange={(e) => setInterval(Number(e.target.value))}
            className="mt-1 w-full rounded-md border border-black/[.08] bg-transparent px-3 py-2 outline-none focus:border-black/30 dark:border-white/[.145]"
          >
            {INTERVALS.map((option) => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
        </label>

        <label className="block text-sm font-medium">
          Timeout (5–30s)
          <input
            type="number"
            min={5}
            max={30}
            value={timeout}
            onChange={(e) => setTimeoutSeconds(Number(e.target.value))}
            className="mt-1 w-full rounded-md border border-black/[.08] bg-transparent px-3 py-2 outline-none focus:border-black/30 dark:border-white/[.145]"
          />
          {fieldError("timeout_seconds") && (
            <span className="mt-1 block text-xs text-red-600">{fieldError("timeout_seconds")}</span>
          )}
        </label>

        <label className="block text-sm font-medium">
          Expected status
          <input
            required
            value={expectedStatus}
            onChange={(e) => setExpectedStatus(e.target.value)}
            placeholder="200-399"
            className="mt-1 w-full rounded-md border border-black/[.08] bg-transparent px-3 py-2 outline-none focus:border-black/30 dark:border-white/[.145]"
          />
          {fieldError("expected_status") && (
            <span className="mt-1 block text-xs text-red-600">{fieldError("expected_status")}</span>
          )}
        </label>

        <label className="block text-sm font-medium">
          Keyword (optional)
          <input
            value={keyword}
            maxLength={255}
            onChange={(e) => setKeyword(e.target.value)}
            placeholder="e.g. Sign in"
            className="mt-1 w-full rounded-md border border-black/[.08] bg-transparent px-3 py-2 outline-none focus:border-black/30 dark:border-white/[.145]"
          />
        </label>

        <label className="block text-sm font-medium">
          Keyword mode
          <select
            value={keywordMode}
            onChange={(e) => setKeywordMode(e.target.value as "must_contain" | "must_not_contain")}
            disabled={keyword.trim() === ""}
            className="mt-1 w-full rounded-md border border-black/[.08] bg-transparent px-3 py-2 outline-none focus:border-black/30 disabled:opacity-50 dark:border-white/[.145]"
          >
            <option value="must_contain">Must contain</option>
            <option value="must_not_contain">Must not contain</option>
          </select>
          {fieldError("keyword_mode") && (
            <span className="mt-1 block text-xs text-red-600">{fieldError("keyword_mode")}</span>
          )}
        </label>

        <label className="block text-sm font-medium">
          Alert after (failures)
          <select
            value={alertAfter}
            onChange={(e) => setAlertAfter(Number(e.target.value))}
            className="mt-1 w-full rounded-md border border-black/[.08] bg-transparent px-3 py-2 outline-none focus:border-black/30 dark:border-white/[.145]"
          >
            {[1, 2, 3, 4, 5].map((n) => (
              <option key={n} value={n}>
                {n} {n === 1 ? "failure" : "failures"}
              </option>
            ))}
          </select>
        </label>

        <label className="flex items-end gap-2 pb-2 text-sm font-medium">
          <input
            type="checkbox"
            checked={followRedirects}
            onChange={(e) => setFollowRedirects(e.target.checked)}
            className="h-4 w-4"
          />
          Follow redirects (max 5)
        </label>
      </div>

      <div className="mt-6">
        <div className="flex items-center justify-between">
          <h3 className="text-sm font-semibold">Custom headers (optional, max 5)</h3>
          <button
            type="button"
            onClick={addHeader}
            disabled={headers.length >= 5}
            className="text-sm font-medium underline disabled:opacity-50"
          >
            Add header
          </button>
        </div>

        {monitor && monitor.custom_header_keys.length > 0 && headers.length === 0 && (
          <p className="mt-1 text-xs text-zinc-400">
            Existing header values are hidden. Adding rows replaces the current set.
          </p>
        )}

        {headers.map((row, index) => (
          <div key={index} className="mt-2 flex gap-2">
            <input
              value={row.key}
              onChange={(e) => updateHeader(index, { key: e.target.value })}
              placeholder="X-Api-Key"
              className="w-1/2 rounded-md border border-black/[.08] bg-transparent px-3 py-1.5 text-sm outline-none focus:border-black/30 dark:border-white/[.145]"
            />
            <input
              value={row.value}
              onChange={(e) => updateHeader(index, { value: e.target.value })}
              placeholder="value"
              className="w-1/2 rounded-md border border-black/[.08] bg-transparent px-3 py-1.5 text-sm outline-none focus:border-black/30 dark:border-white/[.145]"
            />
            <button
              type="button"
              onClick={() => setHeaders((rows) => rows.filter((_, i) => i !== index))}
              className="text-sm text-red-600"
            >
              Remove
            </button>
          </div>
        ))}
      </div>

      <div className="mt-6 flex gap-3">
        <button
          type="submit"
          disabled={busy}
          className="rounded-full bg-foreground px-5 py-2 text-sm font-medium text-background transition-colors hover:bg-[#383838] disabled:opacity-60 dark:hover:bg-[#ccc]"
        >
          {busy ? "Saving…" : monitor ? "Save changes" : "Add monitor"}
        </button>
        {onCancel && (
          <button
            type="button"
            onClick={onCancel}
            className="rounded-full border border-black/[.08] px-5 py-2 text-sm font-medium transition-colors hover:bg-black/[.04] dark:border-white/[.145] dark:hover:bg-[#1a1a1a]"
          >
            Cancel
          </button>
        )}
      </div>
    </form>
  );
}
