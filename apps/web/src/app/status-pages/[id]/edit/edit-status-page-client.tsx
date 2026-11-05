"use client";

import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import { useCallback, useEffect, useState } from "react";
import { StatusPageForm } from "@/components/status-page-form";
import { api, ApiRequestError } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import type { StatusPage } from "@/lib/types";

export function EditStatusPageClient() {
  const params = useParams<{ id: string }>();
  const id = Number(params.id);
  const router = useRouter();
  const { user, loading } = useAuth();
  const [page, setPage] = useState<StatusPage | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    try {
      const res = await api.listStatusPages();
      const found = res.data.find((candidate) => candidate.id === id) ?? null;
      setPage(found);
      if (!found) setError("This status page does not exist or was deleted.");
    } catch (err) {
      if (err instanceof ApiRequestError && err.status === 401) {
        router.push("/login");
        return;
      }
      setError("Could not load this status page.");
    }
  }, [id, router]);

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
    <div className="mx-auto w-full max-w-3xl flex-1 px-6 py-8">
      <header className="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold">Edit status page</h1>
          <p className="text-sm text-zinc-500">Changes appear within a minute.</p>
        </div>
        {page && (
          <Link
            href={page.public_path}
            target="_blank"
            className="rounded-full border border-black/[.08] px-4 py-2 text-sm font-medium transition-colors hover:bg-black/[.04] dark:border-white/[.145] dark:hover:bg-[#1a1a1a]"
          >
            View public page ↗
          </Link>
        )}
      </header>

      {error && <p className="mb-4 text-red-600">{error}</p>}
      {!page && !error && <p className="text-zinc-500">Loading status page…</p>}

      {page && (
        <StatusPageForm
          page={page}
          onSaved={() => router.push("/status-pages")}
          onCancel={() => router.push("/status-pages")}
        />
      )}

      <div className="mt-6">
        <Link href="/status-pages" className="text-sm font-medium underline">
          ← Back to status pages
        </Link>
      </div>
    </div>
  );
}
