"use client";

import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import { useCallback, useEffect, useState } from "react";
import { MonitorForm } from "@/components/monitor-form";
import { api, ApiRequestError } from "@/lib/api";
import { useAuth } from "@/lib/auth";
import type { Monitor } from "@/lib/types";

export function EditMonitorClient() {
  const params = useParams<{ id: string }>();
  const id = Number(params.id);
  const router = useRouter();
  const { user, loading } = useAuth();
  const [monitor, setMonitor] = useState<Monitor | null>(null);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    try {
      const res = await api.getMonitor(id);
      setMonitor(res.monitor);
    } catch (err) {
      if (err instanceof ApiRequestError && err.status === 401) {
        router.push("/login");
        return;
      }
      setError("This monitor does not exist or was deleted.");
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
      <header className="mb-6">
        <h1 className="text-2xl font-semibold">Edit monitor</h1>
        <p className="text-sm text-zinc-500">
          Changes apply from the next scheduled check. History is kept.
        </p>
      </header>

      {error && <p role="alert" className="mb-4 text-red-600">{error}</p>}

      {!monitor && !error && <p className="text-zinc-500">Loading monitor…</p>}

      {monitor && (
        <MonitorForm
          monitor={monitor}
          onSaved={(saved) => router.push(`/monitors/${saved.id}`)}
          onCancel={() => router.push(`/monitors/${monitor.id}`)}
        />
      )}

      <div className="mt-6">
        <Link href="/dashboard" className="text-sm font-medium underline">
          ← Back to dashboard
        </Link>
      </div>
    </div>
  );
}
