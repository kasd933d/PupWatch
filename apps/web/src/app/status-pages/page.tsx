"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useCallback, useEffect, useState } from "react";
import { StatusPageForm } from "@/components/status-page-form";
import { api, ApiRequestError } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import type { StatusPage } from "@/lib/types";

function StatusPageCard({ page, onChanged }: { page: StatusPage; onChanged: () => void }) {
  const [busy, setBusy] = useState(false);
  const [copied, setCopied] = useState(false);

  async function copyLink() {
    try {
      await navigator.clipboard.writeText(window.location.origin + page.public_path);
      setCopied(true);
      window.setTimeout(() => setCopied(false), 1500);
    } catch {
      setCopied(false);
    }
  }

  async function remove() {
    if (!confirm(`Delete "${page.title}"? The public link will stop working.`)) return;
    setBusy(true);
    try {
      await api.deleteStatusPage(page.id);
      onChanged();
    } finally {
      setBusy(false);
    }
  }

  return (
    <div className="rounded-xl border border-black/[.08] bg-white p-5 dark:border-white/[.145] dark:bg-black">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p className="font-semibold">{page.title}</p>
          <Link
            href={page.public_path}
            target="_blank"
            className="text-sm text-zinc-500 underline-offset-2 hover:underline"
          >
            {page.public_path}
          </Link>
          <p className="mt-1 text-xs text-zinc-400">
            {page.monitors?.length ?? 0} monitor{(page.monitors?.length ?? 0) === 1 ? "" : "s"}
            {page.show_error_details ? " · error details public" : " · error details hidden"}
            {!page.is_indexable && " · noindex"}
          </p>
        </div>

        <div className="flex flex-wrap gap-2 text-sm">
          <button
            onClick={copyLink}
            className="rounded-full border border-black/[.08] px-3 py-1.5 transition-colors hover:bg-black/[.04] dark:border-white/[.145] dark:hover:bg-[#1a1a1a]"
          >
            {copied ? "Copied!" : "Copy link"}
          </button>
          <Link
            href={`/status-pages/${page.id}/edit`}
            className="rounded-full border border-black/[.08] px-3 py-1.5 transition-colors hover:bg-black/[.04] dark:border-white/[.145] dark:hover:bg-[#1a1a1a]"
          >
            Edit
          </Link>
          <button
            disabled={busy}
            onClick={remove}
            className="rounded-full border border-red-200 px-3 py-1.5 text-red-600 transition-colors hover:bg-red-50 disabled:opacity-50 dark:border-red-900 dark:hover:bg-red-950"
          >
            Delete
          </button>
        </div>
      </div>
    </div>
  );
}

export default function StatusPagesPage() {
  const router = useRouter();
  const { user, loading } = useAuth();
  const [pages, setPages] = useState<StatusPage[]>([]);
  const [listLoading, setListLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    try {
      const res = await api.listStatusPages();
      setPages(res.data);
      setError(null);
    } catch (err) {
      if (err instanceof ApiRequestError && err.status === 401) {
        router.push("/login");
        return;
      }
      setError("Could not load your status pages.");
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
    Promise.resolve().then(() => load());
  }, [loading, user, router, load]);

  if (loading || !user) {
    return <div className="flex flex-1 items-center justify-center text-zinc-500">Loading…</div>;
  }

  return (
    <div className="mx-auto w-full max-w-4xl flex-1 px-6 py-8">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold">Status pages</h1>
          <p className="text-sm text-zinc-500">
            A shareable, SEO-friendly page for your clients. Updates every minute.
          </p>
        </div>
        <div className="flex gap-2 text-sm">
          <Link
            href="/dashboard"
            className="rounded-full border border-black/[.08] px-4 py-2 font-medium transition-colors hover:bg-black/[.04] dark:border-white/[.145] dark:hover:bg-[#1a1a1a]"
          >
            ← Dashboard
          </Link>
          <button
            onClick={() => setShowForm((value) => !value)}
            className="rounded-full bg-foreground px-4 py-2 font-medium text-background transition-colors hover:bg-[#383838] dark:hover:bg-[#ccc]"
          >
            {showForm ? "Close" : "+ New status page"}
          </button>
        </div>
      </header>

      <div className="mt-6 space-y-4">
        {showForm && (
          <StatusPageForm
            onSaved={(saved) => {
              setShowForm(false);
              router.push(`/status-pages/${saved.id}/edit`);
            }}
            onCancel={() => setShowForm(false)}
          />
        )}

        {listLoading && <p className="text-zinc-500">Loading status pages…</p>}
        {error && <p className="text-red-600">{error}</p>}

        {!listLoading && !error && pages.length === 0 && !showForm && (
          <div className="rounded-xl border border-dashed border-black/[.15] p-10 text-center dark:border-white/[.2]">
            <p className="font-medium">No status pages yet</p>
            <p className="mt-1 text-sm text-zinc-500">
              Pick a few monitors and share one link with your clients.
            </p>
          </div>
        )}

        {pages.map((page) => (
          <StatusPageCard key={page.id} page={page} onChanged={load} />
        ))}
      </div>
    </div>
  );
}
