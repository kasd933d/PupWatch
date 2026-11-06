"use client";

import { FormEvent, useEffect, useState } from "react";
import { api, ApiRequestError } from "@/lib/api";
import type { Monitor, StatusPage } from "@/lib/types";

/**
 * Create/edit form for a public status page (PRD §4.8).
 */
export function StatusPageForm({
  page,
  onSaved,
  onCancel,
}: {
  page?: StatusPage;
  onSaved: (page: StatusPage) => void;
  onCancel?: () => void;
}) {
  const [title, setTitle] = useState(page?.title ?? "");
  const [slug, setSlug] = useState(page?.slug ?? "");
  const [description, setDescription] = useState(page?.description ?? "");
  const [showResponseTimes, setShowResponseTimes] = useState(page?.show_response_times ?? false);
  const [showErrorDetails, setShowErrorDetails] = useState(page?.show_error_details ?? false);
  const [isIndexable, setIsIndexable] = useState(page?.is_indexable ?? true);
  const [accentColor, setAccentColor] = useState(page?.accent_color ?? "");
  const [logoUrl, setLogoUrl] = useState(page?.logo_url ?? "");

  const [monitors, setMonitors] = useState<Monitor[]>([]);
  const [selected, setSelected] = useState<Record<number, string>>(() => {
    const initial: Record<number, string> = {};
    for (const monitor of page?.monitors ?? []) {
      initial[monitor.id] = monitor.display_name ?? "";
    }
    return initial;
  });

  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    let active = true;
    api
      .listMonitors()
      .then((res) => {
        if (active) setMonitors(res.data);
      })
      .catch(() => {
        if (active) setError("Could not load your monitors.");
      });

    return () => {
      active = false;
    };
  }, []);

  function toggleMonitor(id: number) {
    setSelected((current) => {
      const next = { ...current };
      if (id in next) {
        delete next[id];
      } else {
        next[id] = "";
      }
      return next;
    });
  }

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setError(null);
    setFieldErrors({});

    const selectedIds = Object.keys(selected).map(Number);
    if (selectedIds.length === 0) {
      setError("Pick at least one monitor for this page.");
      return;
    }

    const payload: Record<string, unknown> = {
      title,
      description: description.trim() === "" ? null : description.trim(),
      show_response_times: showResponseTimes,
      show_error_details: showErrorDetails,
      is_indexable: isIndexable,
      accent_color: accentColor.trim() === "" ? null : accentColor.trim(),
      logo_url: logoUrl.trim() === "" ? null : logoUrl.trim(),
      monitors: selectedIds.map((id) => ({
        id,
        display_name: selected[id].trim() === "" ? null : selected[id].trim(),
      })),
    };

    if (slug.trim() !== "" || page) {
      payload.slug = slug.trim();
    }

    setBusy(true);
    try {
      const res = page
        ? await api.updateStatusPage(page.id, payload)
        : await api.createStatusPage(payload);
      onSaved(res.status_page);
    } catch (err) {
      if (err instanceof ApiRequestError) {
        setError(err.message);
        setFieldErrors(err.errors);
      } else {
        setError("Could not save the status page.");
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
          Title
          <input
            required
            maxLength={100}
            value={title}
            onChange={(e) => setTitle(e.target.value)}
            placeholder="Acme Status"
            className="mt-1 w-full rounded-md border border-black/[.08] bg-transparent px-3 py-2 outline-none focus:border-black/30 dark:border-white/[.145]"
          />
          {fieldError("title") && <span className="mt-1 block text-xs text-red-600">{fieldError("title")}</span>}
        </label>

        <label className="block text-sm font-medium">
          Slug
          <input
            maxLength={80}
            value={slug}
            onChange={(e) => setSlug(e.target.value)}
            placeholder="acme-status"
            className="mt-1 w-full rounded-md border border-black/[.08] bg-transparent px-3 py-2 outline-none focus:border-black/30 dark:border-white/[.145]"
          />
          <span className="mt-1 block text-xs text-zinc-400">/status/{slug || "…"}</span>
          {fieldError("slug") && <span className="mt-1 block text-xs text-red-600">{fieldError("slug")}</span>}
        </label>
      </div>

      <label className="mt-4 block text-sm font-medium">
        Description
        <textarea
          rows={2}
          maxLength={500}
          value={description}
          onChange={(e) => setDescription(e.target.value)}
          placeholder="Live status for our client sites."
          className="mt-1 w-full rounded-md border border-black/[.08] bg-transparent px-3 py-2 outline-none focus:border-black/30 dark:border-white/[.145]"
        />
      </label>

      <fieldset className="mt-6">
        <legend className="text-sm font-semibold">Monitors on this page</legend>
        <p className="mt-1 text-xs text-zinc-400">
          The display name is what visitors see; leave it blank to use the monitor name.
        </p>

        <div className="mt-3 space-y-2">
          {monitors.map((monitor) => {
            const checked = monitor.id in selected;
            return (
              <div
                key={monitor.id}
                className="flex flex-wrap items-center gap-3 rounded-lg border border-black/[.06] px-3 py-2 text-sm dark:border-white/[.08]"
              >
                <label className="flex items-center gap-2">
                  <input
                    type="checkbox"
                    checked={checked}
                    onChange={() => toggleMonitor(monitor.id)}
                    className="h-4 w-4"
                  />
                  <span className="font-medium">{monitor.name}</span>
                </label>
                <span className="truncate text-xs text-zinc-400">{monitor.host}</span>
                {checked && (
                  <input
                    value={selected[monitor.id] ?? ""}
                    onChange={(e) =>
                      setSelected((current) => ({ ...current, [monitor.id]: e.target.value }))
                    }
                    placeholder="Public name (optional)"
                    className="ml-auto w-48 rounded-md border border-black/[.08] bg-transparent px-2 py-1 text-xs outline-none focus:border-black/30 dark:border-white/[.145]"
                  />
                )}
              </div>
            );
          })}
          {monitors.length === 0 && (
            <p className="text-sm text-zinc-500">You need at least one monitor first.</p>
          )}
        </div>
        {fieldError("monitors") && (
          <span className="mt-2 block text-xs text-red-600">{fieldError("monitors")}</span>
        )}
        {fieldError("monitors.0.id") && (
          <span className="mt-2 block text-xs text-red-600">{fieldError("monitors.0.id")}</span>
        )}
      </fieldset>

      <div className="mt-6 grid gap-4 sm:grid-cols-2">
        <label className="block text-sm font-medium">
          Accent color (optional)
          <input
            value={accentColor}
            onChange={(e) => setAccentColor(e.target.value)}
            placeholder="#2563eb"
            className="mt-1 w-full rounded-md border border-black/[.08] bg-transparent px-3 py-2 outline-none focus:border-black/30 dark:border-white/[.145]"
          />
          {fieldError("accent_color") && (
            <span className="mt-1 block text-xs text-red-600">{fieldError("accent_color")}</span>
          )}
        </label>

        <label className="block text-sm font-medium">
          Logo URL (optional)
          <input
            type="url"
            value={logoUrl}
            onChange={(e) => setLogoUrl(e.target.value)}
            placeholder="https://example.com/logo.png"
            className="mt-1 w-full rounded-md border border-black/[.08] bg-transparent px-3 py-2 outline-none focus:border-black/30 dark:border-white/[.145]"
          />
          {fieldError("logo_url") && (
            <span className="mt-1 block text-xs text-red-600">{fieldError("logo_url")}</span>
          )}
        </label>
      </div>

      <div className="mt-6 space-y-2 text-sm">
        <label className="flex items-center gap-2">
          <input
            type="checkbox"
            checked={showResponseTimes}
            onChange={(e) => setShowResponseTimes(e.target.checked)}
            className="h-4 w-4"
          />
          Show response times publicly
        </label>
        <label className="flex items-center gap-2">
          <input
            type="checkbox"
            checked={showErrorDetails}
            onChange={(e) => setShowErrorDetails(e.target.checked)}
            className="h-4 w-4"
          />
          Show error details publicly
        </label>
        <label className="flex items-center gap-2">
          <input
            type="checkbox"
            checked={isIndexable}
            onChange={(e) => setIsIndexable(e.target.checked)}
            className="h-4 w-4"
          />
          Allow search engines to index this page
        </label>
      </div>

      <div className="mt-6 flex gap-3">
        <button
          type="submit"
          disabled={busy}
          className="rounded-full bg-foreground px-5 py-2 text-sm font-medium text-background transition-colors hover:bg-[#383838] disabled:opacity-60 dark:hover:bg-[#ccc]"
        >
          {busy ? "Saving…" : page ? "Save changes" : "Create status page"}
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
